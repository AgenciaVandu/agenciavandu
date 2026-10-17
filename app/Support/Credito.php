<?php

namespace App\Support;

use App\Models\Credito as CreditoModelo;
use App\Models\CreditoLetra;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crédito del lote: mensualidad fija con interés mensual sobre saldo.
 * Pago adelantado: el día que vence la letra (o antes) se paga esa y la última pendiente; a la última se le quitan
 * sus intereses y el descuento fijo del lote. Moratorio por día de atraso o porcentaje de la letra.
 */
class Credito
{
    public const DEFECTO = [
        'tasa' => 2.0, 'plazo' => 24, 'enganche_pct' => 30,
        'moratorio_modo' => 'porcentaje', 'moratorio_valor' => 5, 'dias_gracia' => 0, 'descuento_adelanto' => 0,
        'termometro' => [
            ['nombre' => 'Saludable', 'hasta' => 0,    'color' => '#0a7d4a'],
            ['nombre' => 'Atención',  'hasta' => 1,    'color' => '#b7791f'],
            ['nombre' => 'Cuidado',   'hasta' => 2,    'color' => '#c2410c'],
            ['nombre' => 'Riesgo',    'hasta' => null, 'color' => '#c2372f'],
        ],
    ];

    /** Ajustes de crédito del negocio activo */
    public static function ajustes(): array
    {
        $a = array_merge(self::DEFECTO, (array) (Cuentas::actual()?->ajuste('credito') ?? []));
        if (empty($a['termometro'])) $a['termometro'] = self::DEFECTO['termometro'];
        return $a;
    }

    /** Mensualidad fija (sistema francés). $tasa en % mensual */
    public static function mensualidad(float $monto, float $tasa, int $plazo): float
    {
        if ($plazo < 1 || $monto <= 0) return 0.0;
        $i = $tasa / 100;
        if ($i <= 0) return round($monto / $plazo, 2);
        return round($monto * $i / (1 - (1 + $i) ** -$plazo), 2);
    }

    /**
     * Tabla de pagos: cada letra con su capital e interés sobre el saldo.
     * La última ajusta los centavos para que el capital cuadre exacto.
     * @return array<int, array{numero:int, vence_el:string, capital:float, interes:float, monto:float, saldo:float}>
     */
    public static function tabla(float $monto, float $tasa, int $plazo, Carbon|string $primerVencimiento): array
    {
        $pago = self::mensualidad($monto, $tasa, $plazo);
        $i = $tasa / 100;
        $saldo = round($monto, 2);
        $inicio = Carbon::parse($primerVencimiento)->startOfDay();
        $filas = [];
        for ($k = 1; $k <= $plazo; $k++) {
            $interes = round($saldo * $i, 2);
            $capital = $k === $plazo ? $saldo : round($pago - $interes, 2);
            $saldo = round($saldo - $capital, 2);
            $filas[] = [
                'numero' => $k, 'vence_el' => $inicio->copy()->addMonthsNoOverflow($k - 1)->toDateString(),
                'capital' => $capital, 'interes' => $interes, 'monto' => round($capital + $interes, 2), 'saldo' => max(0.0, $saldo),
            ];
        }
        return $filas;
    }

    /** Moratorio de una letra si se paga en $fecha */
    public static function moratorio(CreditoModelo $c, CreditoLetra $l, Carbon $fecha): float
    {
        $dias = (int) $l->vence_el->copy()->startOfDay()->diffInDays($fecha->copy()->startOfDay(), false);
        if ($dias <= (int) $c->dias_gracia) return 0.0;
        return $c->moratorio_modo === 'dia'
            ? round($dias * (float) $c->moratorio_valor, 2)
            : round($l->monto * (float) $c->moratorio_valor / 100, 2);
    }

    /**
     * Lo que se cobraría hoy: las $cuantas letras que siguen (con su moratorio) y, si se puede, la última adelantada.
     * @return array{letras: array, adelanto: ?array, total: float, puede_adelantar: bool, motivo: ?string}
     */
    public static function cotizarPago(CreditoModelo $c, Carbon $fecha, int $cuantas = 1, bool $adelantar = false): array
    {
        $pend = $c->letras()->whereNull('pagada_el')->orderBy('numero')->get();
        $toca = $pend->take(max(1, $cuantas));
        $letras = $toca->map(fn ($l) => ['id' => $l->id, 'numero' => $l->numero, 'vence_el' => $l->vence_el->toDateString(), 'monto' => $l->monto,
                                         'moratorio' => self::moratorio($c, $l, $fecha)])->values()->all();

        $ultima = $pend->last();
        $motivo = null;
        if ($toca->isEmpty()) $motivo = 'El crédito ya está liquidado.';
        elseif (! $ultima || $toca->contains('id', $ultima->id)) $motivo = 'No quedan letras por adelantar.';
        elseif ($fecha->copy()->startOfDay()->gt($toca->last()->vence_el)) $motivo = 'Solo se puede adelantar el día que vence la letra o antes.';
        $puede = $motivo === null;

        $adelanto = null;
        if ($adelantar && $puede) {
            $adelanto = ['id' => $ultima->id, 'numero' => $ultima->numero, 'capital' => $ultima->capital, 'interes_descontado' => $ultima->interes,
                         'descuento' => min((float) $c->descuento_adelanto, $ultima->capital), 'monto' => round(max(0, $ultima->capital - (float) $c->descuento_adelanto), 2)];
        }
        $total = round(collect($letras)->sum(fn ($l) => $l['monto'] + $l['moratorio']) + ($adelanto['monto'] ?? 0), 2);
        return ['letras' => $letras, 'adelanto' => $adelanto, 'total' => $total, 'puede_adelantar' => $puede, 'motivo' => $motivo];
    }

    /** Registra el pago y marca las letras; si ya no queda nada, el crédito queda liquidado */
    public static function registrarPago(CreditoModelo $c, Carbon $fecha, int $cuantas = 1, bool $adelantar = false, ?string $metodo = null, ?string $nota = null): \App\Models\CreditoPago
    {
        return DB::transaction(function () use ($c, $fecha, $cuantas, $adelantar, $metodo, $nota) {
            $q = self::cotizarPago($c, $fecha, $cuantas, $adelantar);
            if (! $q['letras']) throw new \RuntimeException('El crédito ya está liquidado.');
            if ($adelantar && ! $q['adelanto']) throw new \RuntimeException($q['motivo'] ?? 'No se puede adelantar.');

            $pago = $c->pagos()->create(['fecha' => $fecha->toDateString(), 'monto' => $q['total'], 'metodo' => $metodo, 'nota' => $nota, 'user_id' => auth()->id()]);
            foreach ($q['letras'] as $l) {
                CreditoLetra::whereKey($l['id'])->update(['pago_id' => $pago->id, 'pagada_el' => $fecha->toDateString(),
                    'moratorio' => $l['moratorio'], 'pagado' => round($l['monto'] + $l['moratorio'], 2)]);
            }
            if ($a = $q['adelanto']) {
                CreditoLetra::whereKey($a['id'])->update(['pago_id' => $pago->id, 'pagada_el' => $fecha->toDateString(), 'adelantada' => true,
                    'descuento' => round($a['interes_descontado'] + $a['descuento'], 2), 'pagado' => $a['monto']]);
            }
            if (! $c->letras()->whereNull('pagada_el')->exists()) $c->update(['estado' => 'liquidado']);
            return $pago;
        });
    }

    /** Deshace el último pago (por si se capturó mal) */
    public static function cancelarPago(\App\Models\CreditoPago $pago): void
    {
        DB::transaction(function () use ($pago) {
            CreditoLetra::where('pago_id', $pago->id)->update(['pago_id' => null, 'pagada_el' => null, 'pagado' => null, 'moratorio' => 0, 'descuento' => 0, 'adelantada' => false]);
            $c = $pago->credito;
            $pago->delete();
            if ($c->estado === 'liquidado') $c->update(['estado' => 'activo']);
        });
    }

    /** En qué rango del termómetro cae un número de letras atrasadas */
    public static function termometro(int $atrasadas, ?array $rangos = null): array
    {
        $rangos ??= self::ajustes()['termometro'];
        foreach ($rangos as $r) {
            if ($r['hasta'] === null || $r['hasta'] === '' || $atrasadas <= (int) $r['hasta']) return $r;
        }
        return end($rangos);
    }
}
