<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Reservacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reservar, cancelar y pasar lista. Todo en transacción y con la clase y la membresía bloqueadas,
 * para que dos personas no se queden con el último lugar ni se gaste un crédito dos veces.
 */
class Reservas
{
    private static function error(string $msg): ValidationException
    {
        return ValidationException::withMessages(['reservacion' => $msg]);
    }

    /**
     * @param string $origen panel | atleta | escaner
     * @param bool $forzar desde el panel: permite reservar aunque ya haya empezado (p. ej. al escanear en la puerta)
     */
    public static function reservar(Clase $clase, Cliente $cliente, string $origen = 'panel', ?User $por = null, bool $forzar = false): Reservacion
    {
        return DB::transaction(function () use ($clase, $cliente, $origen, $por, $forzar) {
            $clase = Clase::whereKey($clase->id)->lockForUpdate()->firstOrFail();
            $tz = config('vandu.zona_horaria');
            $fecha = $clase->inicia_at->copy()->setTimezone($tz)->toDateString();

            if ($clase->estado !== 'programada') throw self::error('Esta clase está cancelada.');
            if ($cliente->activo === false) throw self::error("{$cliente->nombre} está inactivo.");
            if ($clase->termina_at->isPast()) throw self::error('Esta clase ya terminó.');
            if (! $forzar) {
                $cierre = $clase->inicia_at->copy()->subMinutes((int) Ajustes::get('reservar_hasta'));
                if ($cierre->isPast()) throw self::error('Las reservaciones para esta clase ya cerraron.');
                if ($origen === 'atleta' && $clase->inicia_at->gt(now()->addDays((int) Ajustes::get('dias_adelante'))->endOfDay())) {
                    throw self::error('Todavía no se abren las reservaciones para esa fecha.');
                }
            }

            $previa = Reservacion::where('clase_id', $clase->id)->where('cliente_id', $cliente->id)->lockForUpdate()->first();
            if ($previa && $previa->estado !== 'cancelada') throw self::error('Ya tiene lugar en esta clase.');

            $ocupados = Reservacion::where('clase_id', $clase->id)->where('estado', '!=', 'cancelada')->count();
            if ($ocupados >= $clase->cupo) throw self::error('La clase está llena: ' . $clase->cupo . ' de ' . $clase->cupo . ' lugares.');

            // Otra clase suya que se cruce en horario
            $choque = Reservacion::where('cliente_id', $cliente->id)->where('estado', '!=', 'cancelada')
                ->whereHas('clase', fn ($q) => $q->where('estado', 'programada')->where('id', '!=', $clase->id)
                    ->where('inicia_at', '<', $clase->termina_at)->where('termina_at', '>', $clase->inicia_at))
                ->with('clase.tipo')->first();
            if ($choque) throw self::error('Se cruza con su clase de ' . $choque->clase->nombre . ' (' . $choque->clase->horario . ').');

            $m = self::membresiaPara($clase, $cliente, $fecha);
            $m = Membresia::whereKey($m->id)->lockForUpdate()->first();
            if (! $m->ilimitada && $m->usados >= $m->creditos) throw self::error('Ya no le quedan clases en su paquete.');

            $usa = ! $m->ilimitada;
            if ($usa) $m->increment('usados');

            $datos = ['membresia_id' => $m->id, 'estado' => 'reservada', 'origen' => $origen, 'credito_usado' => $usa, 'credito_devuelto' => false,
                      'cancelada_at' => null, 'checkin_at' => null, 'checkin_por' => null, 'aviso_at' => null, 'reservada_por' => $por?->id];
            if ($previa) {
                $previa->update($datos);
                return $previa;
            }
            return Reservacion::create($datos + ['clase_id' => $clase->id, 'cliente_id' => $cliente->id]);
        });
    }

    /** La membresía con la que paga esta clase: vigente ese día, del tipo de clase, con créditos y sin pasar su límite diario */
    public static function membresiaPara(Clase $clase, Cliente $cliente, string $fecha): Membresia
    {
        $vigentes = Membresia::where('cliente_id', $cliente->id)->vigentes($fecha)->orderBy('vence_el')->orderBy('id')->get();
        if ($vigentes->isEmpty()) {
            $ultima = Membresia::where('cliente_id', $cliente->id)->where('estado', 'activa')->latest('vence_el')->first();
            throw self::error($ultima && $ultima->vence_el->toDateString() < $fecha
                ? 'Su paquete venció el ' . $ultima->vence_el->locale('es')->isoFormat('D [de] MMMM') . '. Hay que renovarlo.'
                : 'No tiene un paquete vigente para ese día. Asígnale uno primero.');
        }
        $delTipo = $vigentes->filter(fn ($m) => $m->permiteTipo($clase->clase_tipo_id));
        if ($delTipo->isEmpty()) throw self::error('Su paquete no incluye clases de ' . $clase->nombre . '.');
        $conCreditos = $delTipo->filter->con_creditos;
        if ($conCreditos->isEmpty()) throw self::error('Ya no le quedan clases en su paquete. Hay que renovarlo.');

        // Límite de clases por día (del paquete o del gimnasio)
        $tz = config('vandu.zona_horaria');
        $delDia = Reservacion::where('cliente_id', $cliente->id)->where('estado', '!=', 'cancelada')->where('clase_id', '!=', $clase->id)
            ->whereHas('clase', fn ($q) => $q->delDia($clase->inicia_at->copy()->setTimezone($tz)))->count();
        $m = $conCreditos->first(function ($m) use ($delDia) {
            $max = (int) ($m->max_por_dia ?: Ajustes::get('max_por_dia'));
            return $max <= 0 || $delDia < $max;
        });
        if (! $m) throw self::error('Ya tiene el máximo de clases para ese día.');
        return $m;
    }

    /**
     * Cancela su lugar. Con anticipación (o si lo decide el gimnasio) se le devuelve el crédito.
     * @return bool si se devolvió el crédito
     */
    public static function cancelar(Reservacion $r, ?bool $devolver = null): bool
    {
        return DB::transaction(function () use ($r, $devolver) {
            $r = Reservacion::whereKey($r->id)->lockForUpdate()->with('clase')->firstOrFail();
            if ($r->estado === 'cancelada') return $r->credito_devuelto;
            if (in_array($r->estado, ['asistio', 'falta'], true)) throw self::error('Ya se pasó lista de esta clase.');

            $aTiempo = $r->clase->inicia_at->copy()->subHours((int) Ajustes::get('horas_cancelar'))->isFuture();
            $devolver ??= $aTiempo;
            $devuelto = false;
            if ($devolver && $r->credito_usado && ! $r->credito_devuelto && $r->membresia_id) {
                $m = Membresia::whereKey($r->membresia_id)->lockForUpdate()->first();
                if ($m && $m->usados > 0) {
                    $m->decrement('usados');
                    $devuelto = true;
                }
            }
            $r->update(['estado' => 'cancelada', 'cancelada_at' => now(), 'credito_devuelto' => $devuelto]);
            return $devuelto;
        });
    }

    /** Asistió o faltó (pasar lista). Volver a "reservada" deshace la marca */
    public static function marcar(Reservacion $r, string $estado, ?User $por = null): void
    {
        abort_unless(in_array($estado, ['asistio', 'falta', 'reservada'], true), 422);
        if ($r->estado === 'cancelada') throw self::error('Esa reservación está cancelada.');
        $r->update([
            'estado'      => $estado,
            'checkin_at'  => $estado === 'asistio' ? ($r->checkin_at ?? now()) : null,
            'checkin_por' => $estado === 'asistio' ? ($r->checkin_por ?? $por?->id) : null,
        ]);
    }

    /**
     * El gimnasio cancela la clase: a todos se les devuelve su crédito.
     * @return \Illuminate\Support\Collection<Reservacion> los que tenían lugar (para avisarles)
     */
    public static function cancelarClase(Clase $clase, string $motivo)
    {
        return DB::transaction(function () use ($clase, $motivo) {
            $afectados = $clase->reservaciones()->where('estado', 'reservada')->with('cliente')->get();
            foreach ($afectados as $r) self::cancelar($r, true);
            $clase->update(['estado' => 'cancelada', 'motivo_cancelacion' => $motivo]);
            return $afectados;
        });
    }

    /** Las reservaciones que nadie marcó, de clases que ya terminaron hace más de una hora, quedan como falta */
    public static function cerrarFaltas(): int
    {
        return Reservacion::where('estado', 'reservada')
            ->whereHas('clase', fn ($q) => $q->where('termina_at', '<', now()->subHour()))
            ->update(['estado' => 'falta', 'updated_at' => now()]);
    }
}
