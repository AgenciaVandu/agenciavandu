<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Lo que le pedimos a un proveedor para un proyecto: conceptos, indicaciones, su PDF, facturas y archivos */
class OrdenCompra extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'ordenes_compra';

    public const ESTADOS = [
        'borrador'   => ['texto' => 'Borrador',    'clase' => 'gris'],
        'enviada'    => ['texto' => 'Enviada',     'clase' => 'azul'],
        'en_proceso' => ['texto' => 'En proceso',  'clase' => 'pend'],
        'entregada'  => ['texto' => 'Entregada',   'clase' => 'ok'],
        'facturada'  => ['texto' => 'Facturada',   'clase' => 'ok'],
        'pagada'     => ['texto' => 'Pagada',      'clase' => 'ok'],
        'cancelada'  => ['texto' => 'Cancelada',   'clase' => 'gris'],
    ];

    /** Lo que sube el proveedor (y nosotros: diseños y referencias) */
    public const TIPOS_ARCHIVO = [
        'produccion' => ['texto' => 'Para producción', 'icono' => 'bi-printer'],
        'factura'  => ['texto' => 'Factura',      'icono' => 'bi-receipt'],
        'muestra'  => ['texto' => 'Muestra',      'icono' => 'bi-image'],
        'catalogo' => ['texto' => 'Catálogo',     'icono' => 'bi-journal-richtext'],
        'ponchado' => ['texto' => 'Ponchado',     'icono' => 'bi-bezier2'],
        'diseno'   => ['texto' => 'Diseño o referencia', 'icono' => 'bi-palette'],
        'otro'     => ['texto' => 'Otro',         'icono' => 'bi-paperclip'],
    ];

    protected $fillable = ['proveedor_id', 'proyecto_id', 'creada_por', 'titulo', 'indicaciones', 'fecha', 'fecha_entrega', 'lugar_entrega', 'con_iva', 'iva_porcentaje', 'estado', 'enviada_at', 'vista_at'];

    protected $casts = [
        'fecha'          => 'date',
        'fecha_entrega'  => 'date',
        'con_iva'        => 'boolean',
        'iva_porcentaje' => 'float',
        'enviada_at'     => 'datetime',
        'vista_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrdenCompra $o) {
            $o->folio ??= static::siguienteFolio();
            $o->fecha ??= now(config('vandu.zona_horaria'))->toDateString();
        });
        static::deleting(function (OrdenCompra $o) {
            $o->archivos()->get()->each->delete(); // cada archivo borra su copia (Dropbox o disco)
        });
    }

    public function proveedor(): BelongsTo { return $this->belongsTo(Proveedor::class); }
    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function autor(): BelongsTo { return $this->belongsTo(User::class, 'creada_por'); }
    public function conceptos(): HasMany { return $this->hasMany(OrdenCompraConcepto::class)->orderBy('orden')->orderBy('id'); }
    public function archivos(): HasMany { return $this->hasMany(OrdenCompraArchivo::class)->latest('id'); }
    public function comentarios(): HasMany { return $this->hasMany(OrdenCompraComentario::class)->oldest('id'); }

    /** Lo que nosotros adjuntamos (archivos para producción, diseños): siempre arriba */
    public const TIPOS_AGENCIA = ['produccion', 'diseno'];

    /** Archivos que le mandamos al proveedor, primero los de producción */
    public function getAdjuntosAttribute()
    {
        return $this->archivos->where('autor', 'agencia')->whereIn('tipo', self::TIPOS_AGENCIA)
            ->sortBy(fn ($a) => [$a->tipo === 'produccion' ? 0 : 1, $a->id])->values();
    }

    /** Las que el proveedor puede ver (todo menos borradores) */
    public function scopeVisibles(Builder $q): Builder { return $q->where('estado', '!=', 'borrador'); }

    /** Siguen en curso (no pagadas ni canceladas) */
    public function scopeAbiertas(Builder $q): Builder { return $q->whereNotIn('estado', ['pagada', 'cancelada']); }

    public static function siguienteFolio(): string
    {
        $prefijo = config('vandu.folio_orden_compra', 'OC-');
        $ultimo = static::where('folio', 'like', $prefijo . '%')->orderByDesc('id')->value('folio');
        $n = $ultimo ? ((int) Str::after($ultimo, $prefijo)) + 1 : 1;
        return $prefijo . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /* ---------------- Importes ---------------- */

    public function getSubtotalAttribute(): float
    {
        return round($this->conceptos->sum(fn ($c) => $c->importe), 2);
    }

    public function getIvaAttribute(): float
    {
        return $this->con_iva ? round($this->subtotal * $this->iva_porcentaje / 100, 2) : 0.0;
    }

    public function getTotalAttribute(): float
    {
        return round($this->subtotal + $this->iva, 2);
    }

    public static function dinero(float $v): string
    {
        return '$' . number_format($v, 2);
    }

    /* ---------------- Estado ---------------- */

    public function getEstadoTextoAttribute(): string { return self::ESTADOS[$this->estado]['texto'] ?? $this->estado; }
    public function getEstadoClaseAttribute(): string { return self::ESTADOS[$this->estado]['clase'] ?? 'gris'; }

    public function getFacturasAttribute()
    {
        return $this->archivos->where('tipo', 'factura');
    }

    /** "Entrega hoy", "Entrega mañana", "Entrega vie 16 oct", "Entrega vencida hace 2 días" */
    public function getEntregaTextoAttribute(): ?string
    {
        if (! $this->fecha_entrega) return null;
        $hoy = now(config('vandu.zona_horaria'))->startOfDay();
        $d = (int) $hoy->diffInDays($this->fecha_entrega, false);
        $abierta = in_array($this->estado, ['enviada', 'en_proceso'], true);
        return match (true) {
            $d === 0 => 'Entrega hoy',
            $d === 1 => 'Entrega mañana',
            $d < 0 && $abierta => 'Entrega vencida ' . ($d === -1 ? 'ayer' : 'hace ' . abs($d) . ' días'),
            default => 'Entrega ' . $this->fecha_entrega->locale('es')->isoFormat('ddd D MMM'),
        };
    }

    public function getRetrasadaAttribute(): bool
    {
        return $this->fecha_entrega && in_array($this->estado, ['enviada', 'en_proceso'], true)
            && $this->fecha_entrega->lt(now(config('vandu.zona_horaria'))->startOfDay());
    }

    public function getNombreArchivoAttribute(): string
    {
        return $this->folio . '-' . Str::slug($this->proveedor?->empresa ?: 'proveedor') . '.pdf';
    }

    /**
     * Huella de lo que se ve de estas órdenes (estado, datos, archivos, comentarios).
     * Si cambia, la página abierta sabe que hay novedades y se actualiza sola.
     */
    public static function firma(iterable $ids): string
    {
        $ids = collect($ids)->values()->all();
        if (! $ids) return 'vacio';
        $partes = [
            static::whereIn('id', $ids)->orderBy('id')->get(['id', 'estado', 'updated_at'])->map(fn ($o) => $o->id . $o->estado . $o->updated_at?->timestamp)->implode(','),
            OrdenCompraArchivo::whereIn('orden_compra_id', $ids)->selectRaw('count(*) as n, max(id) as m')->first()?->only(['n', 'm']),
            OrdenCompraComentario::whereIn('orden_compra_id', $ids)->selectRaw('count(*) as n, max(id) as m')->first()?->only(['n', 'm']),
            OrdenCompraConcepto::whereIn('orden_compra_id', $ids)->selectRaw('count(*) as n, max(id) as m')->first()?->only(['n', 'm']),
        ];
        return substr(md5(json_encode($partes)), 0, 16);
    }

    public function getResumenAttribute(): string
    {
        $n = $this->conceptos->count();
        return $n ? $this->conceptos->first()->descripcion . ($n > 1 ? ' y ' . ($n - 1) . ' más' : '') : $this->titulo;
    }

    /** Página de esta orden para el proveedor */
    public function getUrlProveedorAttribute(): string
    {
        $this->proveedor->portal_url; // asegura el token
        return route('proveedor.orden', [$this->proveedor->portal_token, $this->id]);
    }

    /** Carpeta de Dropbox de esta orden: /Vandu/Proveedores/<Empresa>/<Folio> */
    public function getCarpetaDropboxAttribute(): string
    {
        $d = \App\Support\Dropbox\Dropbox::class;
        return $d::raiz() . '/Proveedores/' . $d::nombreSeguro($this->proveedor->empresa) . '/' . $d::nombreSeguro($this->folio . ' ' . $this->titulo);
    }
}
