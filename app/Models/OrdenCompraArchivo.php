<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Factura, muestra, catálogo, ponchado… de una orden de compra. Vive en Dropbox (o en el servidor si no hay Dropbox) */
class OrdenCompraArchivo extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['tipo', 'autor', 'user_id', 'nombre', 'ruta', 'origen', 'dropbox_id', 'mime', 'peso', 'uuid', 'cfdi'];

    protected $casts = ['peso' => 'integer', 'cfdi' => 'array'];

    protected static function booted(): void
    {
        // Una factura XML que entra o sale cambia el costo comprobado de la orden
        static::created(fn (OrdenCompraArchivo $a) => $a->uuid && $a->orden?->recalcularFactura());
        static::deleted(function (OrdenCompraArchivo $a) {
            if ($a->uuid) $a->orden?->recalcularFactura();
            if ($a->origen === 'dropbox') {
                try { \App\Support\Dropbox\Dropbox::cliente()->borrar($a->dropbox_id ?: $a->ruta); } catch (\Throwable $e) { report($e); }
            } else {
                Storage::disk('local')->delete($a->ruta);
            }
        });
    }

    public function orden(): BelongsTo { return $this->belongsTo(OrdenCompra::class, 'orden_compra_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getTipoTextoAttribute(): string { return OrdenCompra::TIPOS_ARCHIVO[$this->tipo]['texto'] ?? 'Archivo'; }
    public function getIconoAttribute(): string { return OrdenCompra::TIPOS_ARCHIVO[$this->tipo]['icono'] ?? 'bi-paperclip'; }

    public function getEsImagenAttribute(): bool { return str_starts_with((string) $this->mime, 'image/'); }

    public function getPesoTextoAttribute(): string
    {
        $b = (int) $this->peso;
        return match (true) {
            $b >= 1073741824 => number_format($b / 1073741824, 1) . ' GB',
            $b >= 1048576    => number_format($b / 1048576, 1) . ' MB',
            default          => max(1, round($b / 1024)) . ' KB',
        };
    }

    public function getExtensionAttribute(): string
    {
        return strtoupper(pathinfo($this->nombre, PATHINFO_EXTENSION) ?: '—');
    }
}
