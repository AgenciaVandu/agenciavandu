<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Proyecto extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const ESTADOS = ['activo' => 'Activo', 'pausado' => 'En pausa', 'terminado' => 'Terminado'];

    protected $fillable = [
        'cliente_id', 'presupuesto_id', 'tipo', 'nombre', 'estado', 'monto_total',
        'forma_pago', 'dias_credito', 'fecha_inicio', 'mensaje_cliente', 'notas_internas',
    ];

    protected $casts = [
        'fecha_inicio'    => 'date',
        'monto_total'     => 'float',
        'ultima_vista_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Proyecto $p) => $p->token ??= Str::random(32));
    }

    /* ---------------- Relaciones ---------------- */

    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function presupuesto(): BelongsTo { return $this->belongsTo(Presupuesto::class); }
    public function etapas(): HasMany { return $this->hasMany(ProyectoEtapa::class)->orderBy('orden'); }
    public function pagos(): HasMany { return $this->hasMany(ProyectoPago::class)->orderBy('orden'); }
    public function archivos(): HasMany { return $this->hasMany(ProyectoArchivo::class)->orderBy('orden')->orderBy('id'); }

    /* ---------------- Crear desde una cotización ---------------- */

    /** Tipo sugerido según las palabras de los conceptos */
    public static function tipoSugerido(Presupuesto $p): string
    {
        if ($p->tipo && config("vandu.proyectos.{$p->tipo}")) {
            return $p->tipo;
        }
        $texto = Str::lower($p->conceptos->map(fn ($c) => $c->titulo . ' ' . $c->descripcion)->join(' '));
        if (config('vandu.proyectos.redes') && Str::contains($texto, config('vandu.palabras_redes', []))) {
            return 'redes';
        }
        if (Str::contains($texto, config('vandu.palabras_audiovisual', []))) {
            return 'audiovisual';
        }
        return Str::contains($texto, config('vandu.palabras_produccion', [])) ? 'produccion' : 'web';
    }

    /**
     * @param Carbon|null $fin  Si se da, el proyecto se registra como ya terminado: etapas repartidas
     *                          entre $inicio y $fin, todas completadas, y los pagos registrados.
     */
    public static function desdePresupuesto(Presupuesto $p, string $tipo, ?Carbon $inicio = null, ?Carbon $fin = null): self
    {
        $metodo = config("vandu.proyectos.$tipo");
        abort_unless($metodo, 422, 'Tipo de proyecto no válido.');
        $inicio ??= now(config('vandu.zona_horaria'))->startOfDay();

        return DB::transaction(function () use ($p, $tipo, $metodo, $inicio, $fin) {
            $p->loadMissing('conceptos');
            $primero = Str::of($p->conceptos->first()?->resumen ?: $metodo['nombre'])->before("\n")->limit(70, '…');

            $proyecto = self::create([
                'cliente_id'     => $p->cliente_id,
                'presupuesto_id' => $p->id,
                'tipo'           => $tipo,
                'nombre'         => (string) $primero,
                'estado'         => $fin ? 'terminado' : 'activo',
                'monto_total'    => $p->total,
                'fecha_inicio'   => $inicio,
            ]);

            $historico = $fin !== null;
            $fechasEtapa = $historico ? self::repartir($metodo['etapas'], $inicio, $fin) : null;

            // Etapas con fechas propuestas en días hábiles (las de "fecha" se agendan después)
            $cursor = $inicio->copy();
            foreach ($metodo['etapas'] as $i => $e) {
                $agendada = ! empty($e['fecha']);
                if ($historico) {
                    [$ini, $finEtapa] = $fechasEtapa[$i];
                    $proyecto->etapas()->create([
                        'clave'         => $e['clave'],
                        'nombre'        => $e['nombre'],
                        'descripcion'   => $e['descripcion'] ?? null,
                        'orden'         => $i,
                        'es_fecha'      => $agendada,
                        'dias'          => $e['dias'] ?? null,
                        'estado'        => 'completada',
                        'fecha_inicio'  => $agendada ? $finEtapa : $ini,
                        'fecha_fin'     => $agendada ? null : $finEtapa,
                        'completada_at' => $finEtapa->copy()->setTime(18, 0)->shiftTimezone(config('vandu.zona_horaria'))->setTimezone(config('app.timezone')),
                    ]);
                    continue;
                }
                $finEt = $agendada ? null : $cursor->copy()->addWeekdays(max(1, $e['dias']) - 1);
                $proyecto->etapas()->create([
                    'clave'        => $e['clave'],
                    'nombre'       => $e['nombre'],
                    'descripcion'  => $e['descripcion'] ?? null,
                    'orden'        => $i,
                    'es_fecha'     => $agendada,
                    'dias'         => $e['dias'] ?? null,
                    'fecha_inicio' => $agendada ? null : $cursor->copy(),
                    'fecha_fin'    => $finEt,
                ]);
                if ($finEt) {
                    $cursor = $finEt->copy()->addWeekday();
                }
            }
            $inicioDeEtapa = $historico
                ? collect($metodo['etapas'])->mapWithKeys(fn ($e, $i) => [$e['clave'] => ! empty($e['fecha']) ? $fechasEtapa[$i][1] : $fechasEtapa[$i][0]])
                : collect();

            // Pagos: el último absorbe el redondeo
            $acumulado = 0;
            foreach ($metodo['pagos'] as $i => $pg) {
                $ultimo = $i === count($metodo['pagos']) - 1;
                $monto = $ultimo ? round($proyecto->monto_total - $acumulado, 2) : round($proyecto->monto_total * $pg['porcentaje'] / 100, 2);
                $acumulado += $monto;
                $proyecto->pagos()->create([
                    'clave'      => $pg['clave'],
                    'concepto'   => $pg['concepto'],
                    'porcentaje' => $pg['porcentaje'],
                    'monto'      => $monto,
                    'antes_de'   => $pg['antes_de'] ?? null,
                    'orden'      => $i,
                    // En proyectos ya terminados: el primer pago al inicio, los demás al empezar la etapa que habilitan
                    'pagado_el'  => $historico ? ($i === 0 ? $inicio->copy() : ($inicioDeEtapa[$pg['antes_de'] ?? ''] ?? $fin->copy())) : null,
                ]);
            }

            return $proyecto;
        });
    }

    /**
     * Reparte las etapas entre dos fechas según su duración estimada.
     * @return array<int, array{0: Carbon, 1: Carbon}> [inicio, fin] por etapa
     */
    private static function repartir(array $etapas, Carbon $inicio, Carbon $fin): array
    {
        $pesos = array_map(fn ($e) => max(1, $e['dias'] ?? 1), $etapas);
        $total = array_sum($pesos);
        $dias = max(0, $inicio->diffInDays($fin));
        $acum = 0; $salida = [];
        foreach ($pesos as $i => $peso) {
            $desde = $inicio->copy()->addDays((int) floor($acum / $total * $dias));
            $acum += $peso;
            $hasta = $i === count($pesos) - 1 ? $fin->copy() : $inicio->copy()->addDays(max(0, (int) floor($acum / $total * $dias) - 1));
            $salida[] = [$desde, $hasta->lt($desde) ? $desde->copy() : $hasta];
        }
        return $salida;
    }

    /* ---------------- Lectura ---------------- */

    public function getMetodologiaAttribute(): array
    {
        return config("vandu.proyectos.{$this->tipo}", []);
    }

    public function getTipoNombreAttribute(): string
    {
        return $this->metodologia['nombre'] ?? ucfirst($this->tipo);
    }

    public function getTieneGaleriaAttribute(): bool
    {
        return ! empty($this->metodologia['galeria']);
    }

    /** % de avance: etapas completadas (la en curso cuenta la mitad) */
    public function getProgresoAttribute(): int
    {
        $etapas = $this->etapas;
        if ($etapas->isEmpty()) return 0;
        $puntos = $etapas->sum(fn ($e) => $e->estado === 'completada' ? 1 : ($e->estado === 'en_curso' ? .5 : 0));
        return (int) round($puntos / $etapas->count() * 100);
    }

    public function getPagadoAttribute(): float
    {
        return round($this->pagos->whereNotNull('pagado_el')->sum('monto'), 2);
    }

    public function getPorCobrarAttribute(): float
    {
        return round($this->pagos->whereNull('pagado_el')->sum('monto'), 2);
    }

    public function getEtapaActualAttribute(): ?ProyectoEtapa
    {
        return $this->etapas->firstWhere('estado', 'en_curso') ?? $this->etapas->firstWhere('estado', 'pendiente');
    }

    public function getACreditoAttribute(): bool
    {
        return $this->forma_pago === 'credito';
    }

    /** Pagos pendientes que bloquean una etapa */
    public function pagosQueBloquean(ProyectoEtapa $etapa): Collection
    {
        return $this->pagos->where('antes_de', $etapa->clave)->whereNull('pagado_el')->values();
    }

    /**
     * Línea del tiempo combinada: antes de cada etapa van los pagos que la habilitan.
     * @return array<int, array{tipo: 'pago'|'etapa', item: ProyectoPago|ProyectoEtapa}>
     */
    public function lineaDelTiempo(): array
    {
        $items = [];
        $usados = collect();
        foreach ($this->etapas as $etapa) {
            foreach ($this->pagos->where('antes_de', $etapa->clave) as $pago) {
                $items[] = ['tipo' => 'pago', 'item' => $pago];
                $usados->push($pago->id);
            }
            $items[] = ['tipo' => 'etapa', 'item' => $etapa];
        }
        foreach ($this->pagos->whereNotIn('id', $usados) as $pago) {
            $items[] = ['tipo' => 'pago', 'item' => $pago];
        }
        return $items;
    }

    /** Lo que sigue, en lenguaje para el cliente */
    public function getSiguientePasoAttribute(): ?string
    {
        if ($this->estado === 'terminado') return null;
        foreach ($this->lineaDelTiempo() as $t) {
            if ($t['tipo'] === 'pago' && ! $t['item']->pagado) {
                return "{$t['item']->concepto} pendiente";
            }
            if ($t['tipo'] === 'etapa' && $t['item']->estado !== 'completada') {
                return $t['item']->nombre . ($t['item']->estado === 'en_curso' ? ' en curso' : '');
            }
        }
        return 'Proyecto completado';
    }

    public function getUrlEntregaAttribute(): string
    {
        return route('proyecto.entrega', $this->token);
    }

    /** "18 fotos y 2 videos" con lo visible de la galería */
    public function getEntregablesTextoAttribute(): string
    {
        $g = $this->archivos->where('grupo', 'galeria')->where('visible', true);
        $fotos = $g->filter->es_imagen->count();
        $videos = $g->filter->es_video->count();
        $otros = $g->count() - $fotos - $videos;
        $partes = array_filter([
            $fotos ? $fotos . ($fotos === 1 ? ' foto' : ' fotos') : null,
            $videos ? $videos . ($videos === 1 ? ' video' : ' videos') : null,
            $otros ? $otros . ($otros === 1 ? ' archivo' : ' archivos') : null,
        ]);
        if (! $partes) return 'tu material';
        $ultimo = array_pop($partes);
        return $partes ? implode(', ', $partes) . ' y ' . $ultimo : $ultimo;
    }

    public function getUrlPublicaAttribute(): string
    {
        return route('proyecto.publico', $this->token);
    }

    public function getCarpetaAttribute(): string
    {
        return "proyectos/{$this->id}";
    }
}
