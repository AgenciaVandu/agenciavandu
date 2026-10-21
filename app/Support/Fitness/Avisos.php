<?php

namespace App\Support\Fitness;

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\PushSuscripcion;
use App\Models\Reservacion;
use App\Support\Cuentas;
use App\Support\Push\Notificar;

/**
 * Notificaciones push al atleta (en el celular donde activó los avisos de su portal) y al equipo del gimnasio.
 * Las programadas las corre el comando vandu:fitness cada 5 minutos: cada aviso sale una sola vez.
 */
class Avisos
{
    public static function atleta(?Cliente $c, string $titulo, ?string $cuerpo = null, ?string $etiqueta = null, ?string $url = null): int
    {
        if (! $c) return 0;
        $destinos = PushSuscripcion::where('cliente_id', $c->id)->get();
        if ($destinos->isEmpty()) return 0;
        return Notificar::ahora('atleta', $titulo, $cuerpo, $url ?? Cuentas::enlace(route('atleta.inicio'), $c->cuenta_id), $etiqueta, $destinos, false);
    }

    /** @return array{clase: int, credito: int, vence: int} cuántos avisos salieron */
    public static function programados(): array
    {
        $a = Ajustes::todas();
        $n = ['clase' => 0, 'credito' => 0, 'vence' => 0];

        // "Tu clase empieza en X min"
        if ((int) $a['aviso_clase'] > 0) {
            $rs = Reservacion::with(['clase.tipo', 'clase.sucursal', 'cliente'])->where('estado', 'reservada')->whereNull('aviso_at')
                ->whereHas('clase', fn ($q) => $q->programadas()->where('inicia_at', '>', now())->where('inicia_at', '<=', now()->addMinutes((int) $a['aviso_clase'])))->get();
            foreach ($rs as $r) {
                $min = max(1, (int) round(now()->diffInMinutes($r->clase->inicia_at)));
                self::atleta($r->cliente, "Tu clase de {$r->clase->nombre} empieza " . ($min >= 60 ? 'a las ' . $r->clase->inicio->format('H:i') : "en $min min"),
                    trim(($r->clase->sucursal ? $r->clase->sucursal->nombre . '. ' : '') . 'Ten lista tu credencial QR para entrar.'), 'clase-' . $r->clase_id);
                $r->forceFill(['aviso_at' => now()])->save();
                $n['clase']++;
            }
        }

        $vigentes = Membresia::with('cliente')->vigentes()->where('ejemplo', false)->get();

        // Le queda una clase
        foreach ($vigentes->filter(fn ($m) => ! $m->ilimitada && $m->restantes === 1 && ! $m->aviso_credito_at) as $m) {
            self::atleta($m->cliente, 'Te queda 1 clase de tu paquete', "Renueva tu {$m->nombre} en recepción para seguir reservando.", 'credito-' . $m->id);
            $m->forceFill(['aviso_credito_at' => now()])->save();
            $n['credito']++;
        }

        // Su paquete vence pronto (y todavía le quedan clases o es ilimitado)
        if ((int) $a['aviso_vence'] > 0) {
            $limite = now(config('vandu.zona_horaria'))->addDays((int) $a['aviso_vence'])->toDateString();
            foreach ($vigentes->filter(fn ($m) => $m->vence_el->toDateString() <= $limite && ! $m->aviso_vence_at) as $m) {
                $dias = $m->dias_restantes;
                self::atleta($m->cliente, $dias <= 0 ? 'Tu paquete vence hoy' : "Tu paquete vence en $dias " . ($dias === 1 ? 'día' : 'días'),
                    "{$m->nombre}: " . ($m->ilimitada ? 'membresía ilimitada' : "te quedan {$m->restantes_texto}") . '. Renuévalo en recepción para no perder tu lugar.', 'vence-' . $m->id);
                $m->forceFill(['aviso_vence_at' => now()])->save();
                $n['vence']++;
            }
        }

        // Al equipo: un resumen si hubo paquetes por terminarse
        if ($n['credito'] + $n['vence'] > 0) {
            Notificar::evento('fitness_vence', ($n['credito'] + $n['vence']) . ' ' . ($n['credito'] + $n['vence'] === 1 ? 'atleta necesita' : 'atletas necesitan') . ' renovar',
                'Les avisamos en su celular. Revisa quién en Atletas.', route('admin.clientes.index', ['ver' => 'por_vencer']), 'fitness-vence');
        }
        return $n;
    }
}
