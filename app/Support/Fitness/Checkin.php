<?php

namespace App\Support\Fitness;

use App\Models\Clase;
use App\Models\Cliente;
use App\Models\Reservacion;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Entrada con QR: se escanea la credencial del atleta (la del celular cambia cada minuto; la impresa es fija) y se busca su reservación de una clase de hoy,
 * en la sucursal de quien escanea y dentro de la ventana de entrada. Si no reservó, se ofrecen las clases
 * a las que todavía se le puede inscribir.
 */
class Checkin
{
    /** @return array{ok: bool, tipo: string, mensaje: string, atleta: ?array, clase: ?array, opciones: array} */
    public static function escanear(string $codigo, User $por): array
    {
        [$c, $tipo] = Credencial::leer($codigo);
        if (! $c) return self::respuesta(false, 'desconocido', 'Este código no es de un atleta de este gimnasio.');
        if ($tipo === 'vencido') return self::respuesta(false, 'vencido', 'Ese QR ya cambió. Pídele que abra de nuevo su credencial.', $c);
        if ($c->activo === false) return self::respuesta(false, 'inactivo', "{$c->nombre} está dado de baja.", $c);

        $candidatas = self::clasesEnVentana($por);
        $r = Reservacion::where('cliente_id', $c->id)->whereIn('clase_id', $candidatas->pluck('id'))->where('estado', '!=', 'cancelada')
            ->with('clase.tipo', 'membresia')->get()->sortBy(fn ($r) => abs($r->clase->inicia_at->diffInMinutes(now(), false)))->first();

        if ($r) {
            if ($r->estado === 'asistio') {
                return self::respuesta(false, 'repetido', 'Ya había entrado a las ' . $r->checkin_at->setTimezone(config('vandu.zona_horaria'))->format('H:i') . '.', $c, $r->clase, $r);
            }
            Reservas::marcar($r, 'asistio', $por);
            $r->refresh();
            return self::respuesta(true, 'adelante', '¡Adelante!', $c, $r->clase, $r);
        }

        // Sin reservación: ¿a qué clase se le puede inscribir ahora?
        $opciones = $candidatas->filter(fn ($cl) => $cl->libres > 0)->values()->map(fn ($cl) => [
            'id' => $cl->id, 'nombre' => $cl->nombre, 'horario' => $cl->horario, 'libres' => $cl->libres,
        ])->all();
        $mensaje = $candidatas->isEmpty() ? 'No hay clases en este momento en esta sucursal.' : 'No tiene reservación para esta hora.';
        return self::respuesta(false, 'sin_reserva', $mensaje, $c, null, null, $opciones);
    }

    /** Inscribe en la puerta y marca la entrada de una vez */
    public static function inscribirYEntrar(Clase $clase, Cliente $c, User $por): Reservacion
    {
        $r = Reservas::reservar($clase, $c, 'escaner', $por, true);
        Reservas::marcar($r, 'asistio', $por);
        return $r->refresh();
    }

    /** Clases de hoy cuya ventana de entrada está abierta, en la sucursal de quien escanea o del torniquete (si tiene una) */
    public static function clasesEnVentana(User|int|null $por): Collection
    {
        $sucursal = $por instanceof User ? $por->sucursal_id : $por;
        $antes = (int) Ajustes::get('checkin_antes');
        $despues = (int) Ajustes::get('checkin_despues');
        return Clase::programadas()->with('tipo')->withCount('ocupadas')
            ->where('inicia_at', '<=', now()->addMinutes($antes))
            ->where('inicia_at', '>=', now()->subMinutes($despues))
            ->when($sucursal, fn ($q) => $q->where(fn ($w) => $w->where('sucursal_id', $sucursal)->orWhereNull('sucursal_id')))
            ->orderBy('inicia_at')->get();
    }

    private static function respuesta(bool $ok, string $tipo, string $mensaje, ?Cliente $c = null, ?Clase $clase = null, ?Reservacion $r = null, array $opciones = []): array
    {
        $m = $r?->membresia ?? $c?->membresia_actual;
        return [
            'ok'      => $ok,
            'tipo'    => $tipo,
            'mensaje' => $mensaje,
            'atleta'  => $c ? [
                'id' => $c->id, 'nombre' => $c->nombre, 'foto' => $c->foto_url, 'iniciales' => $c->iniciales,
                'paquete' => $m ? $m->nombre . ' · ' . $m->restantes_texto : 'Sin paquete vigente',
                'vence'   => $m ? 'Vence el ' . $m->vence_el->locale('es')->isoFormat('D [de] MMMM') : null,
                'url'     => route('admin.clientes.show', [$c, 'ver' => 'atleta']),
            ] : null,
            'clase'    => $clase ? ['id' => $clase->id, 'nombre' => $clase->nombre, 'horario' => $clase->horario] : null,
            'opciones' => $opciones,
        ];
    }
}
