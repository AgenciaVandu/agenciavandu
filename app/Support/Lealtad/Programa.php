<?php

namespace App\Support\Lealtad;

use App\Models\Comensal;
use App\Models\Cuenta;
use App\Models\LealtadCampana;
use App\Models\LealtadCanje;
use App\Models\LealtadInscripcion;
use App\Models\LealtadPremio;
use App\Models\LealtadVisita;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas del programa de lealtad de un restaurante: unirse, registrar visitas (una por día),
 * avance en cada campaña y canje de premios. Siempre con la cuenta explícita: se usa desde el panel
 * del restaurante y desde la app del comensal (que no pertenece a ninguna cuenta).
 */
class Programa
{
    private static function error(string $msg): ValidationException
    {
        return ValidationException::withMessages(['lealtad' => $msg]);
    }

    public static function hoy(): string
    {
        return now(config('vandu.zona_horaria'))->toDateString();
    }

    public static function inscripcion(Cuenta $c, Comensal $p): ?LealtadInscripcion
    {
        return LealtadInscripcion::sinCuenta()->where('cuenta_id', $c->id)->where('comensal_id', $p->id)->first();
    }

    public static function inscrito(Cuenta $c, Comensal $p): bool
    {
        $i = self::inscripcion($c, $p);
        return $i && ! $i->baja_at;
    }

    /** Se une (o vuelve) al programa: aquí da su consentimiento para que el restaurante vea sus datos */
    public static function unir(Cuenta $c, Comensal $p, string $origen = 'portal', ?User $por = null): LealtadInscripcion
    {
        $i = self::inscripcion($c, $p) ?? new LealtadInscripcion(['cuenta_id' => $c->id, 'comensal_id' => $p->id]);
        $i->forceFill(['cuenta_id' => $c->id, 'consentido_at' => now(), 'baja_at' => null, 'origen' => $i->exists ? $i->origen : $origen, 'inscrito_por' => $i->inscrito_por ?? $por?->id])->save();
        return $i;
    }

    /** Se sale del programa: el restaurante deja de ver sus datos de contacto (las visitas quedan para sus números) */
    public static function baja(Cuenta $c, Comensal $p): void
    {
        self::inscripcion($c, $p)?->forceFill(['baja_at' => now()])->save();
    }

    public static function campanas(Cuenta $c): Collection
    {
        return LealtadCampana::sinCuenta()->where('cuenta_id', $c->id)->conCanje()->with('premios')->orderBy('termina_el')->get()
            ->filter(fn ($k) => $k->se_puede_canjear)->values();
    }

    /**
     * Avance del comensal en cada campaña del restaurante (vigentes y en periodo de canje).
     * @return Collection<array{campana, visitas, meta, premios: Collection, siguiente}>
     */
    public static function avance(Cuenta $c, Comensal $p, ?Collection $campanas = null): Collection
    {
        $campanas ??= self::campanas($c);
        if ($campanas->isEmpty()) return collect();
        $fechas = LealtadVisita::sinCuenta()->where('cuenta_id', $c->id)->where('comensal_id', $p->id)
            ->where('fecha', '>=', $campanas->min(fn ($k) => $k->inicia_el->toDateString()))->pluck('fecha')->map(fn ($f) => $f->toDateString());
        $canjeados = LealtadCanje::sinCuenta()->where('cuenta_id', $c->id)->where('comensal_id', $p->id)->pluck('premio_id')->flip();

        return $campanas->map(function (LealtadCampana $k) use ($fechas, $canjeados) {
            $visitas = $fechas->filter(fn ($f) => $f >= $k->inicia_el->toDateString() && $f <= $k->termina_el->toDateString())->count();
            $premios = $k->premios->map(fn (LealtadPremio $pr) => [
                'premio' => $pr,
                'desbloqueado' => $visitas >= $pr->visitas,
                'canjeado' => isset($canjeados[$pr->id]),
                'faltan' => max(0, $pr->visitas - $visitas),
            ]);
            return [
                'campana' => $k, 'visitas' => $visitas, 'meta' => $k->meta_visitas,
                'premios' => $premios,
                'disponibles' => $premios->filter(fn ($x) => $x['desbloqueado'] && ! $x['canjeado'])->values(),
                'siguiente' => $premios->first(fn ($x) => ! $x['desbloqueado']),
            ];
        })->values();
    }

    /**
     * Registra la visita de hoy. Cuenta en todas las campañas vigentes del restaurante.
     * @return array{visita: LealtadVisita, nuevos: Collection} premios que se desbloquearon con esta visita
     */
    public static function visita(Cuenta $c, Comensal $p, ?float $monto = null, ?User $por = null, ?int $sucursal = null): array
    {
        $i = self::inscripcion($c, $p);
        if (! $i || $i->baja_at) throw self::error("{$p->primer_nombre} no está en el programa de {$c->nombre}. Únelo primero.");
        return DB::transaction(function () use ($c, $p, $monto, $por, $sucursal) {
            $hoy = self::hoy();
            if (LealtadVisita::sinCuenta()->where('cuenta_id', $c->id)->where('comensal_id', $p->id)->whereDate('fecha', $hoy)->lockForUpdate()->exists()) {
                throw self::error("La visita de hoy de {$p->primer_nombre} ya está registrada. Solo cuenta una por día.");
            }
            $antes = self::avance($c, $p);
            $v = new LealtadVisita(['comensal_id' => $p->id, 'fecha' => $hoy, 'monto' => $monto, 'registrada_por' => $por?->id, 'sucursal_id' => $sucursal]);
            $v->cuenta_id = $c->id;
            $v->save();
            $despues = self::avance($c, $p);
            $ya = $antes->flatMap(fn ($a) => $a['disponibles']->pluck('premio.id'))->merge($antes->flatMap(fn ($a) => $a['premios']->filter(fn ($x) => $x['canjeado'])->pluck('premio.id')))->flip();
            $nuevos = $despues->flatMap(fn ($a) => $a['disponibles'])->filter(fn ($x) => ! isset($ya[$x['premio']->id]))->values();
            return ['visita' => $v, 'nuevos' => $nuevos];
        });
    }

    public static function canjear(Cuenta $c, Comensal $p, LealtadPremio $premio, ?User $por = null): LealtadCanje
    {
        if ($premio->cuenta_id !== $c->id) throw self::error('Ese premio no es de este restaurante.');
        $i = self::inscripcion($c, $p);
        if (! $i || $i->baja_at) throw self::error("{$p->primer_nombre} no está en el programa.");
        return DB::transaction(function () use ($c, $p, $premio, $por) {
            $a = self::avance($c, $p)->first(fn ($x) => $x['campana']->id === $premio->campana_id);
            $item = $a ? $a['premios']->first(fn ($x) => $x['premio']->id === $premio->id) : null;
            if (! $item) throw self::error('Esa campaña ya terminó y pasó su plazo para canjear.');
            if ($item['canjeado']) throw self::error("{$p->primer_nombre} ya canjeó «{$premio->nombre}».");
            if (! $item['desbloqueado']) throw self::error("Le faltan {$item['faltan']} " . ($item['faltan'] === 1 ? 'visita' : 'visitas') . " para «{$premio->nombre}».");
            $k = new LealtadCanje(['comensal_id' => $p->id, 'premio_id' => $premio->id, 'canjeado_por' => $por?->id]);
            $k->cuenta_id = $c->id;
            $k->save();
            return $k;
        });
    }

    /** Restaurantes de la red donde está inscrito (para la app del comensal) */
    public static function restaurantesDe(Comensal $p): Collection
    {
        return LealtadInscripcion::sinCuenta()->where('comensal_id', $p->id)->whereNull('baja_at')->with('cuenta')->get()
            ->filter(fn ($i) => $i->cuenta && $i->cuenta->activa && $i->cuenta->tiene('lealtad'))->values();
    }
}
