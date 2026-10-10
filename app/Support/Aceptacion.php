<?php

namespace App\Support;

use App\Models\Presupuesto;
use App\Models\PresupuestoCodigo;
use App\Models\PresupuestoEvento;
use Illuminate\Support\Facades\Hash;

/**
 * Aceptar o pedir cambios en línea con código de verificación, e historial de la cotización.
 *  - El código es de 6 dígitos, vale 24 horas y se guarda cifrado (hash): ni en la base de datos se puede leer.
 *  - Una cotización aceptada ya no se puede cambiar desde la vista del cliente (solo la agencia, a mano).
 */
class Aceptacion
{
    public const HORAS = 24;
    public const INTENTOS = 5;

    /** Genera un código nuevo y devuelve el texto en claro (solo existe en este momento) */
    public static function generarCodigo(Presupuesto $p, string $canal): array
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $c = PresupuestoCodigo::create([
            'presupuesto_id' => $p->id,
            'codigo_hash'    => Hash::make($codigo),
            'canal'          => $canal,
            'expira_at'      => now()->addHours(self::HORAS),
        ]);
        return ['codigo' => $codigo, 'formateado' => self::formato($codigo), 'expira' => $c->expira_at];
    }

    /** "482913" → "482 913" (más fácil de leer y dictar) */
    public static function formato(string $codigo): string
    {
        return substr($codigo, 0, 3) . ' ' . substr($codigo, 3);
    }

    public static function vigenciaTexto($expira): string
    {
        return $expira->copy()->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D [de] MMMM [a las] H:mm [h]');
    }

    /**
     * Revisa el código contra los vigentes de la cotización.
     * @return true|string  true si es válido; si no, el motivo para mostrar
     */
    public static function verificar(Presupuesto $p, string $codigo): true|string
    {
        $codigo = preg_replace('/\D+/', '', $codigo);
        $vigentes = PresupuestoCodigo::where('presupuesto_id', $p->id)->whereNull('usado_at')
            ->where('expira_at', '>', now())->latest('id')->get();

        if ($vigentes->isEmpty()) {
            return 'No hay un código vigente para esta cotización. Pide uno nuevo con el botón de abajo.';
        }
        if ($vigentes->every(fn ($c) => $c->intentos >= self::INTENTOS)) {
            return 'Se superaron los intentos permitidos. Pide un código nuevo.';
        }
        foreach ($vigentes as $c) {
            if ($c->intentos < self::INTENTOS && strlen($codigo) === 6 && Hash::check($codigo, $c->codigo_hash)) {
                $c->forceFill(['usado_at' => now()])->save();
                return true;
            }
        }
        $vigentes->each(fn ($c) => $c->increment('intentos'));
        $restan = self::INTENTOS - $vigentes->max('intentos');
        if ($restan <= 0) {
            self::registrar($p, 'bloqueo', 'sistema', null, 'Se bloquearon los códigos vigentes por intentos fallidos.', [], false);
            return 'Código incorrecto. Se superaron los intentos; pide un código nuevo.';
        }
        return 'El código no es correcto. Te quedan ' . $restan . ($restan === 1 ? ' intento.' : ' intentos.');
    }

    /** ¿El cliente puede responder en línea? */
    public static function puedeResponder(Presupuesto $p): bool
    {
        return in_array($p->estado, ['borrador', 'enviada', 'negociacion'], true) && ($p->vigente || $p->estado === 'negociacion');
    }

    public static function registrar(Presupuesto $p, string $tipo, string $actor, ?string $autor = null, ?string $detalle = null, array $datos = [], bool $visible = true): PresupuestoEvento
    {
        return PresupuestoEvento::create([
            'presupuesto_id'  => $p->id,
            'tipo'            => $tipo,
            'actor'           => $actor,
            'autor'           => $autor ?? ($actor === 'agencia' ? auth()->user()?->name : null),
            'detalle'         => $detalle,
            'datos'           => $datos ?: null,
            'visible_cliente' => $visible,
            'ip'              => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /** Foto de la cotización para comparar versiones */
    public static function foto(Presupuesto $p): array
    {
        $p->loadMissing('conceptos');
        return [
            'total'     => round($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal, 2),
            'monto'     => $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal),
            'conceptos' => $p->conceptos->map(fn ($c) => ['n' => $c->resumen, 'cant' => (float) $c->cantidad, 'precio' => (float) $c->precio])->values()->all(),
            'vigencia'  => $p->vigente_hasta?->toIso8601String(),
        ];
    }

    /** Qué cambió entre dos fotos, en frases cortas */
    public static function diferencias(array $antes, array $despues, Presupuesto $p): array
    {
        $cambios = [];
        $nombres = fn ($l) => collect($l)->keyBy('n');
        $a = $nombres($antes['conceptos'] ?? []);
        $d = $nombres($despues['conceptos'] ?? []);
        foreach ($d as $n => $c) {
            if (! $a->has($n)) { $cambios[] = 'Se agregó: ' . $n; continue; }
            $o = $a[$n];
            if ($o['cant'] != $c['cant']) $cambios[] = "Cantidad de “{$n}”: " . rtrim(rtrim(number_format($o['cant'], 2), '0'), '.') . ' → ' . rtrim(rtrim(number_format($c['cant'], 2), '0'), '.');
            if ($o['precio'] != $c['precio']) $cambios[] = "Precio de “{$n}”: $" . number_format($o['precio'], 2) . ' → $' . number_format($c['precio'], 2);
        }
        foreach ($a as $n => $c) {
            if (! $d->has($n)) $cambios[] = 'Se quitó: ' . $n;
        }
        if (($antes['total'] ?? null) != ($despues['total'] ?? null)) {
            $cambios[] = 'Importe: ' . ($antes['monto'] ?? '') . ' → ' . ($despues['monto'] ?? '');
        }
        if (($antes['vigencia'] ?? null) !== ($despues['vigencia'] ?? null) && ! empty($despues['vigencia'])) {
            $cambios[] = 'Vigente hasta el ' . self::vigenciaTexto(\Illuminate\Support\Carbon::parse($despues['vigencia']));
        }
        return $cambios;
    }

    /** "ana@hotel.mx" → "a**@hotel.mx" */
    public static function correoOculto(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 1) . str_repeat('*', max(2, mb_strlen($u) - 1)) . '@' . $d;
    }
}
