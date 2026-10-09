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
    public const ESTADOS = ['activo' => 'Activo', 'pausado' => 'En pausa', 'terminado' => 'Terminado'];

    protected $fillable = [
        'cliente_id', 'presupuesto_id', 'tipo', 'nombre', 'estado', 'monto_total',
        'fecha_inicio', 'mensaje_cliente', 'notas_internas',
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
        $texto = Str::lower($p->conceptos->pluck('descripcion')->join(' '));
        return Str::contains($texto, config('vandu.palabras_audiovisual', [])) ? 'audiovisual' : 'web';
    }

    public static function desdePresupuesto(Presupuesto $p, string $tipo, ?Carbon $inicio = null): self
    {
        $metodo = config("vandu.proyectos.$tipo");
        abort_unless($metodo, 422, 'Tipo de proyecto no válido.');
        $inicio ??= now(config('vandu.zona_horaria'))->startOfDay();

        return DB::transaction(function () use ($p, $tipo, $metodo, $inicio) {
            $p->loadMissing('conceptos');
            $primero = Str::of($p->conceptos->first()?->descripcion ?? $metodo['nombre'])->before("\n")->limit(70, '…');

            $proyecto = self::create([
                'cliente_id'     => $p->cliente_id,
                'presupuesto_id' => $p->id,
                'tipo'           => $tipo,
                'nombre'         => (string) $primero,
                'estado'         => 'activo',
                'monto_total'    => $p->total,
                'fecha_inicio'   => $inicio,
            ]);

            // Etapas con fechas propuestas en días hábiles (las de "fecha" se agendan después)
            $cursor = $inicio->copy();
            foreach ($metodo['etapas'] as $i => $e) {
                $agendada = ! empty($e['fecha']);
                $fin = $agendada ? null : $cursor->copy()->addWeekdays(max(1, $e['dias']) - 1);
                $proyecto->etapas()->create([
                    'clave'        => $e['clave'],
                    'nombre'       => $e['nombre'],
                    'descripcion'  => $e['descripcion'] ?? null,
                    'orden'        => $i,
                    'es_fecha'     => $agendada,
                    'fecha_inicio' => $agendada ? null : $cursor->copy(),
                    'fecha_fin'    => $fin,
                ]);
                if ($fin) {
                    $cursor = $fin->copy()->addWeekday();
                }
            }

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
                ]);
            }

            return $proyecto;
        });
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

    public function getUrlPublicaAttribute(): string
    {
        return route('proyecto.publico', $this->token);
    }

    public function getCarpetaAttribute(): string
    {
        return "proyectos/{$this->id}";
    }
}
