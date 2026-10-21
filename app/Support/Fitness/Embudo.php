<?php

namespace App\Support\Fitness;

use App\Models\Cliente;
use App\Models\Membresia;
use App\Models\Reservacion;
use App\Models\Tarea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seguimiento del gimnasio: en qué punto está cada persona, sacado de sus paquetes.
 *   Interesado → Clase de prueba → Nuevo inscrito → (inscrito al corriente) → Por renovar → Vencido
 * No hay que moverlos a mano: al asignar la prueba o un paquete cambian solos. Solo "descartar" se marca.
 */
class Embudo
{
    public const ETAPAS = [
        'interesado' => ['texto' => 'Interesados',     'icono' => 'bi-person-plus',        'ayuda' => 'Preguntaron pero aún no vienen. Invítalos a su clase de prueba.'],
        'prueba'     => ['texto' => 'Clase de prueba', 'icono' => 'bi-stars',              'ayuda' => 'Tomaron o tienen agendada su prueba. Ofréceles su primer paquete.'],
        'nuevo'      => ['texto' => 'Nuevos inscritos', 'icono' => 'bi-trophy',            'ayuda' => 'Compraron su primer paquete en los últimos 30 días.'],
        'renovar'    => ['texto' => 'Por renovar',     'icono' => 'bi-arrow-repeat',       'ayuda' => 'Se les acaban las clases o el paquete vence en 7 días o menos.'],
        'vencido'    => ['texto' => 'Vencidos',        'icono' => 'bi-hourglass-bottom',   'ayuda' => 'Su paquete venció en los últimos 90 días y no han renovado.'],
    ];

    public const ORIGENES = [
        'whatsapp' => ['texto' => 'WhatsApp',          'icono' => 'bi-whatsapp'],
        'redes'    => ['texto' => 'Instagram o redes', 'icono' => 'bi-instagram'],
        'referido' => ['texto' => 'Recomendación',     'icono' => 'bi-people'],
        'visita'   => ['texto' => 'Pasó al gimnasio',  'icono' => 'bi-door-open'],
        'otro'     => ['texto' => 'Otro',              'icono' => 'bi-three-dots'],
    ];

    public const MOTIVOS = ['Precio', 'Horarios', 'Le queda lejos', 'Se fue a otro gimnasio', 'Dejó de responder', 'No era lo que buscaba', 'Otro'];

    public const PERIODOS = ['mes' => 'Este mes', 'trimestre' => 'Últimos 3 meses', 'anio' => 'Este año'];

    public const DIAS_NUEVO = 30;
    public const DIAS_RENOVAR = 7;
    public const DIAS_VENCIDO = 90;

    /**
     * Etapa de una persona según sus paquetes (sin cancelados). null = inscrito al corriente, dado de baja
     * o vencido hace mucho: no necesita seguimiento.
     */
    public static function etapa(Cliente $c, Collection $membresias): ?string
    {
        if ($c->activo === false) return null;
        $hoy = Membresia::hoy();
        $ms = $membresias->where('estado', '!=', 'cancelada');
        $pagadas = $ms->where('es_prueba', false);
        $vigentes = $pagadas->filter->vigente;

        if ($c->descartado_at && $vigentes->isEmpty()) return 'descartado';
        if ($pagadas->isEmpty()) return $ms->isEmpty() ? 'interesado' : 'prueba';

        if ($vigentes->isEmpty()) {
            // Ya pagado para más adelante (renovó antes de tiempo): no se sigue
            if ($pagadas->contains(fn ($m) => $m->inicia_el->toDateString() > $hoy)) return null;
            $ultimo = $pagadas->max(fn ($m) => $m->vence_el->toDateString());
            return Carbon::parse($ultimo)->gte(Carbon::parse($hoy)->subDays(self::DIAS_VENCIDO)) ? 'vencido' : null;
        }

        $limite = Carbon::parse($hoy)->addDays(self::DIAS_RENOVAR)->toDateString();
        $sana = fn ($m) => $m->vence_el->toDateString() > $limite && ($m->ilimitada || $m->restantes > 1);
        $renovado = $pagadas->contains(fn ($m) => $m->inicia_el->toDateString() > $hoy);
        if (! $vigentes->contains($sana) && ! $renovado) return 'renovar';

        $primera = $pagadas->min(fn ($m) => $m->inicia_el->toDateString());
        return Carbon::parse($primera)->gte(Carbon::parse($hoy)->subDays(self::DIAS_NUEVO)) ? 'nuevo' : null;
    }

    /** Tablero: cada etapa con sus personas y el dato que importa para el siguiente paso */
    public static function tablero(string $q = ''): array
    {
        $personas = Cliente::query()
            ->where(fn ($w) => $w->whereNull('activo')->orWhere('activo', true))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%$q%")->orWhere('telefono', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->with(['membresias' => fn ($m) => $m->reorder()->orderBy('inicia_el')])
            ->get();

        $grupos = collect(array_fill_keys([...array_keys(self::ETAPAS), 'descartado'], []))->map(fn () => collect());
        foreach ($personas as $c) {
            $e = self::etapa($c, $c->membresias);
            if ($e) $grupos[$e]->push($c);
        }

        // Pruebas: su próxima clase o si ya vino / faltó
        $ids = $grupos['prueba']->pluck('id');
        $reservas = $ids->isEmpty() ? collect() : Reservacion::with('clase.tipo')->whereIn('cliente_id', $ids)
            ->whereIn('estado', ['reservada', 'asistio', 'falta'])->get()->groupBy('cliente_id');

        // Seguimientos agendados (tareas abiertas ligadas a la persona)
        $todos = $grupos->flatten(1)->pluck('id');
        $tareas = $todos->isEmpty() ? collect() : Tarea::with('responsable')->abiertas()->whereIn('cliente_id', $todos)
            ->orderByRaw('fecha_limite is null')->orderBy('fecha_limite')->orderBy('hora')->get()->groupBy('cliente_id');

        $hoy = Membresia::hoy();
        foreach ($grupos as $etapa => $lista) {
            $lista->each(function (Cliente $c) use ($etapa, $reservas, $tareas, $hoy) {
                $c->setAttribute('etapa_seguimiento', $etapa);
                $c->setAttribute('detalle', self::detalle($c, $etapa, $reservas->get($c->id, collect()), $hoy));
                $c->setRelation('siguiente', $tareas->get($c->id)?->first());
            });
            $grupos[$etapa] = $lista->sortBy(fn ($c) => $c->detalle['orden'])->values();
        }
        return $grupos->all();
    }

    /** Lo que se ve en la tarjeta y con qué se le escribe por WhatsApp */
    public static function detalle(Cliente $c, string $etapa, Collection $reservas, string $hoy): array
    {
        $tz = config('vandu.zona_horaria');
        $pagadas = $c->membresias->where('estado', '!=', 'cancelada')->where('es_prueba', false);
        $negocio = \App\Support\Cuentas::actual()?->nombre ?? 'el gimnasio';
        $n = $c->primer_nombre;
        $desde = fn ($f) => $f ? Carbon::parse($f)->locale('es')->diffForHumans(['parts' => 1]) : null;

        switch ($etapa) {
            case 'interesado':
                $o = self::ORIGENES[$c->origen]['texto'] ?? null;
                return ['linea' => trim(($o ? $o . ' · ' : '') . 'llegó ' . $desde($c->contacto_at ?? $c->created_at)),
                        'nota' => $c->interes, 'tono' => null, 'orden' => -($c->contacto_at ?? $c->created_at)->timestamp,
                        'accion' => 'Agendar prueba', 'paquete' => 'prueba',
                        'whatsapp' => "Hola {$n}, te saluda {$negocio}. ¿Te agendo una clase de prueba esta semana? Dime qué día y horario te queda mejor."];

            case 'prueba':
                $proxima = $reservas->where('estado', 'reservada')->filter(fn ($r) => $r->clase && $r->clase->inicia_at->isFuture())->sortBy(fn ($r) => $r->clase->inicia_at)->first();
                $vino = $reservas->firstWhere('estado', 'asistio');
                $falto = $reservas->firstWhere('estado', 'falta');
                if ($proxima) {
                    return ['linea' => 'Viene ' . $proxima->clase->inicio->locale('es')->isoFormat('ddd D [·] H:mm') . ' a ' . $proxima->clase->tipo?->nombre,
                            'nota' => null, 'tono' => 'info', 'orden' => $proxima->clase->inicia_at->timestamp, 'accion' => null, 'paquete' => null,
                            'whatsapp' => "Hola {$n}, te esperamos en tu clase de prueba el " . $proxima->clase->inicio->locale('es')->isoFormat('dddd D [a las] H:mm') . ". Llega 10 minutos antes y trae agua y toalla."];
                }
                if ($vino) {
                    return ['linea' => 'Ya vino ' . $desde($vino->checkin_at ?? $vino->clase?->inicia_at) . ' · ofrécele paquete',
                            'nota' => null, 'tono' => 'ok', 'orden' => -1e10 + ($vino->checkin_at ?? $vino->updated_at)->timestamp, 'accion' => 'Inscribir', 'paquete' => 'pagado',
                            'whatsapp' => "Hola {$n}, ¿qué tal te sentiste en tu clase de prueba? Te comparto nuestros paquetes para que sigas entrenando con nosotros."];
                }
                return ['linea' => $falto ? 'No llegó a su prueba' : 'Tiene su prueba, falta reservar clase',
                        'nota' => null, 'tono' => 'alerta', 'orden' => 0, 'accion' => 'Reservar clase', 'paquete' => null, 'agenda' => true,
                        'whatsapp' => "Hola {$n}, aún tienes tu clase de prueba en {$negocio}. ¿Qué día te la reservo?"];

            case 'nuevo':
                $primera = $pagadas->sortBy(fn ($m) => $m->inicia_el->toDateString())->first();
                return ['linea' => $primera->nombre . ' · ' . $desde($primera->inicia_el),
                        'nota' => null, 'tono' => 'ok', 'orden' => -$primera->inicia_el->timestamp, 'accion' => null, 'paquete' => null,
                        'whatsapp' => "¡Bienvenido a {$negocio}, {$n}! Cualquier duda con tus clases o reservas, escríbenos por aquí."];

            case 'renovar':
                $m = $pagadas->filter->vigente->sortByDesc(fn ($x) => $x->vence_el->toDateString())->first();
                $dias = $m->dias_restantes;
                $vence = $dias <= 0 ? 'vence hoy' : 'vence en ' . $dias . ' ' . ($dias === 1 ? 'día' : 'días');
                $clases = $m->ilimitada ? null : ($m->restantes <= 0 ? 'sin clases' : ($m->restantes === 1 ? 'le queda 1 clase' : "le quedan {$m->restantes} clases"));
                return ['linea' => $m->nombre . ' · ' . ($clases && $m->restantes <= 1 ? $clases : $vence),
                        'nota' => null, 'tono' => 'alerta', 'orden' => $m->vence_el->timestamp, 'accion' => 'Renovar', 'paquete' => $m->producto_id,
                        'whatsapp' => "Hola {$n}, " . ($clases && $m->restantes <= 1 ? ($m->restantes <= 0 ? 'ya usaste todas las clases de tu paquete' : 'te queda 1 clase en tu paquete') : 'tu paquete ' . $vence)
                            . ". ¿Te lo renuevo para que no pierdas tu lugar?"];

            case 'vencido':
                $m = $pagadas->sortByDesc(fn ($x) => $x->vence_el->toDateString())->first();
                return ['linea' => $m->nombre . ' · venció ' . $desde($m->vence_el),
                        'nota' => null, 'tono' => 'malo', 'orden' => -$m->vence_el->timestamp, 'accion' => 'Renovar', 'paquete' => $m->producto_id,
                        'whatsapp' => "Hola {$n}, te extrañamos en {$negocio}. ¿Te reactivo tu paquete? Esta semana tenemos lugares en tus horarios."];

            default: // descartado
                return ['linea' => ($c->motivo_descarte ?: 'Descartado') . ' · ' . $desde($c->descartado_at),
                        'nota' => $c->interes, 'tono' => null, 'orden' => -optional($c->descartado_at)->timestamp, 'accion' => null, 'paquete' => null,
                        'whatsapp' => "Hola {$n}, te saluda {$negocio}. ¿Te gustaría volver a entrenar con nosotros?"];
        }
    }

    /** Números del periodo: cuántos prueban, cuántos se inscriben y cuántos renuevan */
    public static function resultados(string $periodo): array
    {
        $tz = config('vandu.zona_horaria');
        $desde = match ($periodo) {
            'mes'  => now($tz)->startOfMonth(),
            'anio' => now($tz)->startOfYear(),
            default => now($tz)->subMonths(3)->startOfDay(),
        };
        $d = $desde->toDateString();
        $ms = Membresia::where('estado', '!=', 'cancelada')->orderBy('inicia_el')->orderBy('id')
            ->get(['id', 'cliente_id', 'es_prueba', 'inicia_el', 'vence_el', 'precio'])->groupBy('cliente_id');

        $pruebas = 0; $convertidos = 0; $nuevos = 0; $ingresoNuevos = 0.0; $renovaciones = 0; $ingresoRenov = 0.0;
        $tocaba = 0; $renovaron = 0;
        foreach ($ms as $lista) {
            $prueba = $lista->firstWhere('es_prueba', true);
            $pagadas = $lista->where('es_prueba', false)->values();
            if ($prueba && $prueba->inicia_el->toDateString() >= $d) {
                $pruebas++;
                if ($pagadas->isNotEmpty()) $convertidos++;
            }
            foreach ($pagadas as $i => $m) {
                if ($m->inicia_el->toDateString() < $d) continue;
                if ($i === 0) { $nuevos++; $ingresoNuevos += (float) $m->precio; }
                else { $renovaciones++; $ingresoRenov += (float) $m->precio; }
            }
            // Renovación: de los paquetes que vencieron en el periodo, ¿cuántos compraron otro?
            foreach ($pagadas as $i => $m) {
                $v = $m->vence_el->toDateString();
                if ($v < $d || $v >= Membresia::hoy()) continue;
                $tocaba++;
                if ($pagadas->slice($i + 1)->isNotEmpty()) $renovaron++;
            }
        }
        return [
            'desde'        => $desde,
            'pruebas'      => $pruebas,
            'convertidos'  => $convertidos,
            'conversion'   => $pruebas ? (int) round($convertidos / $pruebas * 100) : null,
            'nuevos'       => $nuevos,
            'ingresoNuevos' => round($ingresoNuevos, 2),
            'renovaciones' => $renovaciones,
            'ingresoRenov' => round($ingresoRenov, 2),
            'tocaba'       => $tocaba,
            'renovaron'    => $renovaron,
            'renovacion'   => $tocaba ? (int) round($renovaron / $tocaba * 100) : null,
        ];
    }
}
