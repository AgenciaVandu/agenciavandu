<?php

namespace App\Support\Facturas;

use App\Models\Cuenta;
use App\Models\TimbreMovimiento;
use App\Models\TimbrePaquete;
use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Support\Facades\DB;

/**
 * Timbres de cada negocio cuando factura con la cuenta de Nerby.
 * Su plan puede incluir N al mes (no se acumulan); además compra paquetes. Se gastan primero los del plan.
 * Cada CFDI timbrado (factura, complemento, nota de crédito, global) gasta uno; cancelar no lo regresa.
 * En modo de pruebas o con su propia cuenta de Facturapi no se cuentan.
 */
class Timbres
{
    public const AVISO = 5;

    /** Solo cuenta con la organización que Nerby le creó y en modo real. La cuenta de la plataforma factura sin límite. */
    public static function aplica(): bool
    {
        if (Cuentas::esPrincipal()) return false;
        $a = Timbrado::ajustes();
        return (bool) $a['org_id'] && ! $a['pruebas'];
    }

    public static function incluidosMes(Cuenta $c): int
    {
        return (int) (rescue(fn () => \App\Support\Licencias\Estado::de($c)->licencia?->plan?->timbres_mes, 0, false) ?? 0);
    }

    private static function inicioMes()
    {
        return now(config('vandu.zona_horaria'))->startOfMonth()->utc();
    }

    public static function delPlanUsados(Cuenta $c): int
    {
        return TimbreMovimiento::where('cuenta_id', $c->id)->where('tipo', 'uso_plan')->where('created_at', '>=', self::inicioMes())->count();
    }

    /** Comprados, regalados y ajustes menos lo usado fuera del plan */
    public static function saldo(Cuenta $c): int
    {
        return (int) TimbreMovimiento::where('cuenta_id', $c->id)->sum('cantidad');
    }

    public static function delPlanRestantes(Cuenta $c): int
    {
        return max(0, self::incluidosMes($c) - self::delPlanUsados($c));
    }

    public static function disponibles(Cuenta $c): int
    {
        return self::delPlanRestantes($c) + max(0, self::saldo($c));
    }

    public static function resumen(Cuenta $c): array
    {
        return ['plan' => self::incluidosMes($c), 'plan_restantes' => self::delPlanRestantes($c), 'comprados' => max(0, self::saldo($c)), 'disponibles' => self::disponibles($c)];
    }

    /** Antes de timbrar: que le quede al menos uno */
    public static function verificar(): void
    {
        $c = Cuentas::actual();
        if (self::aplica() && self::disponibles($c) < 1) {
            throw new TimbradoError('Ya no te quedan timbres. Compra un paquete en Configuración › Facturación para seguir facturando.');
        }
    }

    /** Después de timbrar: gasta uno (del plan del mes si quedan) */
    public static function gastar(string $referencia): void
    {
        if (! self::aplica()) return;
        $c = Cuentas::actual();
        DB::transaction(function () use ($c, $referencia) {
            $delPlan = self::delPlanRestantes($c) > 0;
            TimbreMovimiento::create(['cuenta_id' => $c->id, 'tipo' => $delPlan ? 'uso_plan' : 'uso', 'cantidad' => $delPlan ? 0 : -1,
                'referencia' => mb_substr($referencia, 0, 120), 'user_id' => auth()->id()]);
        });
    }

    /** Entrada de timbres: compra de un paquete (con su pago), regalo o ajuste (puede ser negativo) */
    public static function cargar(Cuenta $c, string $tipo, int $cantidad, ?TimbrePaquete $p = null, ?float $monto = null, ?int $pagoId = null, ?string $nota = null, ?User $u = null): TimbreMovimiento
    {
        return TimbreMovimiento::create(['cuenta_id' => $c->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'paquete_id' => $p?->id,
            'monto' => $monto, 'licencia_pago_id' => $pagoId, 'nota' => $nota, 'user_id' => $u?->id]);
    }

    /**
     * Asignar un paquete a un negocio desde Plataforma › Timbres.
     * Cobrado: registra el pago en su licencia (con recibo) si tiene licencia y el monto es mayor a cero. Regalo: solo carga.
     */
    public static function asignar(Cuenta $c, TimbrePaquete $p, bool $cobrado, ?float $monto = null, ?string $forma = null, ?string $nota = null, ?User $u = null): array
    {
        return DB::transaction(function () use ($c, $p, $cobrado, $monto, $forma, $nota, $u) {
            $pago = null;
            $monto = $cobrado ? ($monto ?? $p->precio) : null;
            if ($cobrado && $monto > 0 && ($l = \App\Models\Licencia::where('cuenta_id', $c->id)->first())) {
                $pago = \App\Support\Licencias\Licencias::pagar($l, ['fecha' => now(config('vandu.zona_horaria'))->toDateString(), 'concepto' => 'timbres',
                    'monto' => $monto, 'periodos' => 0, 'forma' => $forma ?: 'transferencia', 'referencia' => $nota], $u);
            }
            $mov = self::cargar($c, $cobrado ? 'compra' : 'regalo', $p->timbres, $p, $monto, $pago?->id, $nota, $u);
            return [$mov, $pago];
        });
    }

    /** Usados este mes por negocio (del plan y comprados), para la tabla de Plataforma */
    public static function usadosMes(): \Illuminate\Support\Collection
    {
        return TimbreMovimiento::whereIn('tipo', ['uso', 'uso_plan'])->where('created_at', '>=', self::inicioMes())
            ->selectRaw('cuenta_id, count(*) as n')->groupBy('cuenta_id')->pluck('n', 'cuenta_id');
    }

    public static function paquetes()
    {
        return TimbrePaquete::where('activo', true)->orderBy('orden')->orderBy('timbres')->get();
    }
}
