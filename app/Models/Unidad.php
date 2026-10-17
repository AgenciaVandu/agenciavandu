<?php

namespace App\Models;

use App\Support\Credito as CalculoCredito;
use App\Support\Cuentas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** Auto del lote: su ficha, fotos, página pública y, cuando se vende, su crédito */
class Unidad extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'unidades';

    public const ESTADOS = [
        'disponible' => ['texto' => 'Disponible', 'clase' => 'aceptada'],
        'apartado'   => ['texto' => 'Apartado',   'clase' => 'negociacion'],
        'vendido'    => ['texto' => 'Vendido',    'clase' => 'pagada'],
    ];
    public const TIPOS = ['Sedán', 'Hatchback', 'SUV', 'Pickup', 'Minivan', 'Coupé', 'Van', 'Camión', 'Motocicleta'];
    public const TRANSMISIONES = ['Automática', 'Estándar', 'CVT'];
    public const COMBUSTIBLES = ['Gasolina', 'Diésel', 'Híbrido', 'Eléctrico', 'Gas'];

    protected $fillable = ['sucursal_id', 'vendedor_id', 'marca', 'modelo', 'version', 'anio', 'tipo', 'color', 'kilometraje', 'transmision',
                           'combustible', 'placas', 'serie', 'precio', 'costo', 'descripcion', 'ficha', 'estado', 'publico', 'ejemplo'];

    protected $casts = ['precio' => 'float', 'costo' => 'float', 'publico' => 'boolean', 'ejemplo' => 'boolean', 'anio' => 'integer', 'kilometraje' => 'integer'];

    protected $attributes = ['estado' => 'disponible', 'publico' => true, 'precio' => 0];

    protected static function booted(): void
    {
        static::creating(function (Unidad $u) {
            $base = Str::limit(Str::slug(trim($u->marca . ' ' . $u->modelo . ' ' . $u->anio)) ?: 'auto', 180, '');
            $slug = $base;
            for ($i = 2; static::sinCuenta()->where('slug', $slug)->exists(); $i++) $slug = $base . '-' . $i;
            $u->slug = $slug;
        });
    }

    public function sucursal(): BelongsTo { return $this->belongsTo(Sucursal::class); }
    public function vendedor(): BelongsTo { return $this->belongsTo(User::class, 'vendedor_id'); }
    public function fotos(): HasMany { return $this->hasMany(UnidadFoto::class)->orderBy('orden')->orderBy('id'); }
    public function venta(): HasOne { return $this->hasOne(Credito::class)->where('estado', '!=', 'cancelado')->latestOfMany(); }

    public function scopeBuscar(Builder $q, ?string $t): Builder
    {
        $t = trim((string) $t);
        if ($t === '') return $q;
        $like = '%' . mb_strtolower($t) . '%';
        return $q->where(fn ($w) => $w->whereRaw('LOWER(marca) LIKE ?', [$like])->orWhereRaw('LOWER(modelo) LIKE ?', [$like])
            ->orWhereRaw('LOWER(placas) LIKE ?', [$like])->orWhereRaw('LOWER(version) LIKE ?', [$like])->orWhereRaw('CAST(anio AS CHAR) LIKE ?', [$like]));
    }

    public function getNombreAttribute(): string
    {
        return trim($this->marca . ' ' . $this->modelo . ' ' . $this->version . ($this->anio ? ' ' . $this->anio : ''));
    }

    public function getUrlAttribute(): string
    {
        return Cuentas::enlace(route('autos.unidad', $this->slug), $this->cuenta_id);
    }

    public function getPortadaAttribute(): ?UnidadFoto
    {
        return $this->relationLoaded('fotos') ? $this->fotos->first() : $this->fotos()->first();
    }

    /** "Desde $X al mes": con el enganche mínimo, la tasa y el plazo que el lote tiene por defecto */
    public function getMensualidadEstimadaAttribute(): ?array
    {
        $a = CalculoCredito::ajustes();
        if ($this->precio <= 0 || $a['plazo'] < 1) return null;
        $enganche = round($this->precio * $a['enganche_pct'] / 100, 2);
        return ['mensualidad' => CalculoCredito::mensualidad($this->precio - $enganche, $a['tasa'], $a['plazo']), 'plazo' => $a['plazo'], 'enganche' => $enganche];
    }

    /** Líneas de la ficha técnica: "Rines: 17 pulgadas" */
    public function getFichaLineasAttribute(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->ficha))));
    }
}
