<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoría del catálogo (Sillas, Escritorios, Servicios…) */
class ProductoCategoria extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'producto_categorias';

    protected $fillable = ['nombre', 'orden'];

    public function productos(): HasMany { return $this->hasMany(Producto::class, 'categoria_id'); }

    /** La busca por nombre (sin importar mayúsculas) o la crea */
    public static function porNombre(?string $nombre): ?self
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') return null;
        return static::whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])->first()
            ?? static::create(['nombre' => mb_substr($nombre, 0, 80), 'orden' => (int) static::max('orden') + 1]);
    }
}
