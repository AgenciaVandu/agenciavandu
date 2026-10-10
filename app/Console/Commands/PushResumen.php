<?php

namespace App\Console\Commands;

use App\Models\Presupuesto;
use App\Models\ProyectoEtapa;
use App\Models\ProyectoPago;
use App\Support\Push\Notificar;
use Illuminate\Console\Command;

/** Resumen de la mañana por notificación push: cobros, etapas del día y cotizaciones por vencer */
class PushResumen extends Command
{
    protected $signature = 'vandu:push-resumen {--mostrar : Solo muestra el texto, no lo envía}';

    protected $description = 'Envía el resumen diario de pendientes a los dispositivos con notificaciones activas';

    public function handle(): int
    {
        [$titulo, $cuerpo] = static::armar();

        if (! $cuerpo) {
            $this->info('Nada pendiente hoy: no se envía nada.');
            return self::SUCCESS;
        }

        $this->line($titulo);
        $this->line($cuerpo);
        if ($this->option('mostrar')) return self::SUCCESS;

        $n = Notificar::ahora('resumen_diario', $titulo, $cuerpo, route('admin.resumen'), 'resumen-' . now(config('vandu.zona_horaria'))->toDateString());
        $this->info("Enviado a $n dispositivo(s).");
        return self::SUCCESS;
    }

    /** @return array{0:string, 1:?string} */
    public static function armar(): array
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $dinero = fn ($v) => '$' . number_format($v, 0);
        $partes = [];
        $cuantos = 0;

        // Cobros
        $pendientes = ProyectoPago::with('proyecto.cliente')->whereNull('pagado_el')
            ->whereHas('proyecto', fn ($q) => $q->where('estado', '!=', 'pausado'))->whereNotNull('vence_el')->get();
        $vencidos = $pendientes->filter(fn ($p) => $p->vence_el->toDateString() < $hoy->toDateString());
        $proximos = $pendientes->filter(fn ($p) => $p->vence_el->toDateString() >= $hoy->toDateString() && $p->vence_el->toDateString() <= $hoy->copy()->addDays(3)->toDateString());
        if ($vencidos->count()) {
            $partes[] = ($vencidos->count() === 1 ? '1 cobro vencido' : $vencidos->count() . ' cobros vencidos') . ' (' . $dinero($vencidos->sum('monto')) . ')';
            $cuantos += $vencidos->count();
        }
        if ($proximos->count()) {
            $hoyMismo = $proximos->filter(fn ($p) => $p->vence_el->isSameDay($hoy));
            $partes[] = $hoyMismo->count() === $proximos->count()
                ? ($proximos->count() === 1 ? 'Hoy vence el cobro de ' . static::quien($proximos->first()) : $proximos->count() . ' cobros vencen hoy')
                : ($proximos->count() === 1 ? 'Cobro de ' . static::quien($proximos->first()) . ' vence ' . static::cuando($proximos->first()->vence_el, $hoy) : $proximos->count() . ' cobros vencen esta semana') . ' (' . $dinero($proximos->sum('monto')) . ')';
            $cuantos += $proximos->count();
        }

        // Etapas de proyectos activos: las de hoy y las atrasadas
        $etapas = ProyectoEtapa::with('proyecto.cliente')->where('estado', '!=', 'completada')
            ->whereHas('proyecto', fn ($q) => $q->where('estado', 'activo'))->get()
            ->map(function ($e) {
                $e->limite = $e->es_fecha ? $e->fecha_inicio : ($e->fecha_fin ?: $e->fecha_inicio);
                return $e;
            })->filter(fn ($e) => $e->limite);
        $deHoy = $etapas->filter(fn ($e) => $e->limite->toDateString() === $hoy->toDateString());
        $atrasadas = $etapas->filter(fn ($e) => $e->limite->toDateString() < $hoy->toDateString());
        if ($deHoy->count()) {
            $partes[] = $deHoy->count() === 1
                ? 'Hoy: ' . $deHoy->first()->nombre . ' de ' . static::quien($deHoy->first())
                : 'Hoy toca: ' . $deHoy->count() . ' etapas (' . $deHoy->take(2)->map(fn ($e) => $e->nombre)->implode(', ') . ($deHoy->count() > 2 ? '…' : '') . ')';
            $cuantos += $deHoy->count();
        }
        if ($atrasadas->count()) {
            $partes[] = $atrasadas->count() === 1 ? '1 etapa atrasada (' . $atrasadas->first()->nombre . ')' : $atrasadas->count() . ' etapas atrasadas';
            $cuantos += $atrasadas->count();
        }

        // Cotizaciones a punto de vencer (48 h)
        $porVencer = Presupuesto::where('estado', 'enviada')
            ->where('vigente_hasta', '>', now())->where('vigente_hasta', '<=', now()->addHours(48))->get();
        if ($porVencer->count()) {
            $p = $porVencer->first();
            $partes[] = $porVencer->count() === 1
                ? 'La cotización de ' . ($p->cliente_empresa ?: $p->cliente_nombre) . ' vence ' . static::cuando($p->vigente_hasta->copy()->setTimezone($tz), $hoy)
                : $porVencer->count() . ' cotizaciones vencen en 2 días';
            $cuantos += $porVencer->count();
        }

        if (! $partes) return ['', null];

        $titulo = 'Buenos días · ' . ($cuantos === 1 ? '1 pendiente' : "$cuantos pendientes");
        return [$titulo, implode("\n", $partes)];
    }

    private static function quien($x): string
    {
        $c = $x->proyecto?->cliente;
        return $c?->empresa ?: $c?->nombre ?: $x->proyecto?->nombre ?: 'un cliente';
    }

    private static function cuando($fecha, $hoy): string
    {
        $dias = $hoy->diffInDays($fecha->copy()->startOfDay(), false);
        return match (true) {
            $dias <= 0 => 'hoy',
            $dias === 1 => 'mañana',
            default => 'el ' . $fecha->locale('es')->isoFormat('dddd D'),
        };
    }
}
