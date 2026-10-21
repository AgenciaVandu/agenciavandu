<?php

namespace App\Support\Fitness;

use App\Models\Acceso;
use App\Models\AccesoDispositivo;
use App\Models\Cliente;
use App\Models\Reservacion;

/**
 * La regla del torniquete: pasa quien trae su credencial del celular (o la impresa, si el gimnasio lo permite)
 * y tiene reservada una clase cuya ventana de entrada está abierta. Al pasar se le marca la asistencia.
 * Todo intento queda registrado en "accesos".
 */
class Torniquete
{
    /** @return array{permitido: bool, motivo: string, mensaje: string, cliente: ?Cliente, reservacion: ?Reservacion} */
    public static function qr(AccesoDispositivo $d, string $texto, string $metodo = 'qr'): array
    {
        [$c, $tipo] = Credencial::leer($texto);
        if (! $c) return self::registrar($d, $metodo, false, 'desconocido', 'Código no reconocido. Abre tu credencial en el portal.');
        if ($tipo === 'vencido') return self::registrar($d, $metodo, false, 'vencido', 'Ese QR ya cambió. Abre de nuevo tu credencial.', $c);
        if ($tipo === 'fija' && ! $d->credencial_fija) return self::registrar($d, $metodo, false, 'fija', 'Usa la credencial de tu celular.', $c);
        return self::atleta($d, $c, $metodo);
    }

    /** Ya se sabe quién es (QR leído, o rostro identificado por el equipo) */
    public static function atleta(AccesoDispositivo $d, Cliente $c, string $metodo = 'qr'): array
    {
        $nombre = $c->primer_nombre;
        if ($c->activo === false) return self::registrar($d, $metodo, false, 'inactivo', "{$nombre}, pasa a recepción.", $c);

        $candidatas = Checkin::clasesEnVentana($d->sucursal_id);
        $r = Reservacion::where('cliente_id', $c->id)->whereIn('clase_id', $candidatas->pluck('id'))->whereIn('estado', ['reservada', 'asistio'])
            ->with('clase', 'membresia')->get()->sortBy(fn ($r) => abs($r->clase->inicia_at->diffInMinutes(now(), false)))->first();

        if (! $r) {
            $prox = Reservacion::where('cliente_id', $c->id)->where('estado', 'reservada')
                ->whereHas('clase', fn ($q) => $q->where('inicia_at', '>', now())->whereDate('inicia_at', now(config('vandu.zona_horaria'))->toDateString()))
                ->with('clase')->get()->sortBy(fn ($r) => $r->clase->inicia_at)->first();
            $msj = $prox
                ? "{$nombre}, tu clase es a las " . $prox->clase->inicia_at->setTimezone(config('vandu.zona_horaria'))->format('H:i') . '. Puedes entrar ' . (int) Ajustes::get('checkin_antes') . ' min antes.'
                : "{$nombre}, no tienes clase reservada a esta hora.";
            return self::registrar($d, $metodo, false, 'sin_reserva', $msj, $c);
        }

        if ($r->estado === 'asistio') {
            if ($d->reingreso) return self::registrar($d, $metodo, true, 'reingreso', "De nuevo, {$nombre}. " . $r->clase->nombre, $c, $r);
            return self::registrar($d, $metodo, false, 'repetido', "{$nombre}, ya entraste a las " . $r->checkin_at->setTimezone(config('vandu.zona_horaria'))->format('H:i') . '.', $c, $r);
        }

        Reservas::marcar($r, 'asistio');
        $m = $r->membresia?->fresh();
        $extra = $m && ! $m->ilimitada ? ' · te quedan ' . $m->restantes . ($m->restantes === 1 ? ' clase' : ' clases') : '';
        return self::registrar($d, $metodo, true, 'adelante', "¡Adelante, {$nombre}! " . $r->clase->nombre . $extra, $c, $r->refresh());
    }

    public static function registrar(AccesoDispositivo $d, string $metodo, bool $ok, string $motivo, string $mensaje, ?Cliente $c = null, ?Reservacion $r = null): array
    {
        Acceso::create(['cuenta_id' => $d->cuenta_id, 'dispositivo_id' => $d->id, 'cliente_id' => $c?->id, 'reservacion_id' => $r?->id,
            'permitido' => $ok, 'motivo' => $motivo, 'metodo' => $metodo, 'mensaje' => mb_substr($mensaje, 0, 160)]);
        return ['permitido' => $ok, 'motivo' => $motivo, 'mensaje' => $mensaje, 'cliente' => $c, 'reservacion' => $r];
    }
}
