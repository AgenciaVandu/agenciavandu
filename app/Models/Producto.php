<?php

namespace App\Models;

use App\Support\Cuentas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Producto o servicio del catálogo. Lleva existencias (si controla inventario), un enlace público que se arma solo
 * con su nombre y, si cambia el nombre, el enlace anterior sigue funcionando.
 */
class Producto extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['categoria_id', 'proveedor_id', 'clave', 'codigo_barras', 'nombre', 'descripcion', 'unidad', 'precio', 'costo',
                           'controla_existencia', 'minimo', 'foto', 'publico', 'mostrar_precio', 'activo', 'ejemplo'];

    protected $attributes = ['unidad' => 'pieza', 'precio' => 0, 'existencia' => 0, 'controla_existencia' => true, 'publico' => true, 'activo' => true];

    protected $casts = [
        'precio' => 'float', 'costo' => 'float', 'existencia' => 'float', 'minimo' => 'float',
        'controla_existencia' => 'boolean', 'publico' => 'boolean', 'mostrar_precio' => 'boolean', 'activo' => 'boolean', 'ejemplo' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Producto $p) {
            $p->slug = static::slugLibre($p->nombre, $p->clave);
        });

        // Si cambia el nombre, el enlace se actualiza y el anterior queda guardado para redirigir
        static::updating(function (Producto $p) {
            if (! $p->isDirty('nombre')) return;
            $nuevo = static::slugLibre($p->nombre, $p->clave, $p->id);
            if ($nuevo === $p->getOriginal('slug')) return;
            ProductoSlug::firstOrCreate(['slug' => $p->getOriginal('slug')], ['producto_id' => $p->id]);
            ProductoSlug::where('slug', $nuevo)->delete();   // si el nuevo era un enlace viejo suyo, deja de serlo
            $p->slug = $nuevo;
        });
    }

    public function categoria(): BelongsTo { return $this->belongsTo(ProductoCategoria::class, 'categoria_id'); }
    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function movimientos(): HasMany { return $this->hasMany(ProductoMovimiento::class)->latest('id'); }

    public function scopeActivos(Builder $q): Builder { return $q->where('activo', true); }

    /** Busca por nombre, clave o categoría */
    public function scopeBuscar(Builder $q, ?string $texto): Builder
    {
        $texto = trim((string) $texto);
        if ($texto === '') return $q;
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($texto)) . '%';
        return $q->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$like])
            ->orWhereRaw('LOWER(clave) LIKE ?', [$like])
            ->orWhereHas('categoria', fn ($c) => $c->whereRaw('LOWER(nombre) LIKE ?', [$like])));
    }

    /** Los que están en su mínimo o por debajo */
    public function scopeBajos(Builder $q): Builder
    {
        return $q->where('controla_existencia', true)->whereNotNull('minimo')->whereColumn('existencia', '<=', 'minimo');
    }

    /**
     * Enlace libre a partir del nombre: "Silla Ejecutiva Negra" → silla-ejecutiva-negra.
     * Si ya existe (en este u otro negocio, o como enlace viejo), le agrega la clave o un número.
     */
    public static function slugLibre(string $nombre, ?string $clave = null, ?int $ignorar = null): string
    {
        $base = Str::limit(Str::slug($nombre) ?: 'producto', 180, '');
        $ocupado = fn (string $s) => static::sinCuenta()->where('slug', $s)->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))->exists()
            || ProductoSlug::where('slug', $s)->when($ignorar, fn ($q) => $q->where('producto_id', '!=', $ignorar))->exists();

        if (! $ocupado($base)) return $base;
        if ($clave && ($conClave = $base . '-' . Str::slug($clave)) && ! $ocupado($conClave)) return $conClave;
        for ($i = 2; ; $i++) {
            if (! $ocupado($base . '-' . $i)) return $base . '-' . $i;
        }
    }

    /* ---------- Existencias ---------- */

    public function getBajoMinimoAttribute(): bool
    {
        return $this->controla_existencia && $this->minimo !== null && $this->existencia <= $this->minimo;
    }

    public function getAgotadoAttribute(): bool
    {
        return $this->controla_existencia && $this->existencia <= 0;
    }

    /** "12 piezas", "1 caja", "Sin existencia" */
    public function getExistenciaTextoAttribute(): string
    {
        if (! $this->controla_existencia) return 'Sin inventario';
        if ($this->existencia <= 0) return 'Sin existencia';
        return self::numero($this->existencia) . ' ' . $this->unidadPara($this->existencia);
    }

    public function unidadPara(float $cantidad): string
    {
        $u = $this->unidad ?: 'pieza';
        // "m²", "kg" o "pzas" se quedan igual
        if (abs($cantidad - 1) < 0.0001 || str_ends_with($u, 's') || ! preg_match('/[a-záéíóú]$/iu', $u) || in_array($u, ['kg', 'g', 'ml', 'cm', 'mm', 'm', 'lt', 'pza', 'pz'], true)) return $u;
        return preg_match('/[aeiouáéíóú]$/iu', $u) ? $u . 's' : $u . 'es';
    }

    public static function numero(float $n): string
    {
        return fmod($n, 1.0) == 0.0 ? number_format($n, 0) : rtrim(rtrim(number_format($n, 2), '0'), '.');
    }

    /* ---------- Precio, margen y catálogo público ---------- */

    public function getMargenAttribute(): ?float
    {
        if ($this->costo === null || $this->precio <= 0) return null;
        return round(($this->precio - $this->costo) / $this->precio * 100, 1);
    }

    /** El catálogo público muestra el precio si el producto lo pide o, si no dice nada, si así lo quiere el negocio */
    public function getMuestraPrecioAttribute(): bool
    {
        return $this->mostrar_precio ?? (bool) (Cuentas::actual()?->ajuste('catalogo.mostrar_precio', true) ?? true);
    }

    public function getUrlAttribute(): string
    {
        return Cuentas::enlace(route('catalogo.producto', $this->slug), $this->cuenta_id);
    }

    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto ? route('catalogo.foto', [$this->id, basename($this->foto)]) : null;
    }

    public function getMiniaturaUrlAttribute(): ?string
    {
        return $this->foto ? route('catalogo.foto', [$this->id, 'min-' . basename($this->foto)]) : null;
    }
}
