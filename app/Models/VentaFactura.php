<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Un archivo de la factura de una venta directa (pedido): normalmente el PDF y el XML */
class VentaFactura extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'venta_facturas';

    protected $fillable = ['venta_id', 'tipo', 'nombre', 'ruta', 'mime', 'peso', 'uuid', 'rfc_receptor', 'total_cfdi', 'origen', 'facturapi_id', 'cancelacion', 'cancelada_at', 'enviada_at'];

    protected $casts = ['peso' => 'integer', 'total_cfdi' => 'float', 'enviada_at' => 'datetime'];

    protected static function booted(): void
    {
        static::deleted(fn (VentaFactura $f) => Storage::disk('local')->delete($f->ruta));
    }

    public function venta(): BelongsTo { return $this->belongsTo(Venta::class); }

    public function getExtAttribute(): string { return Str::lower(pathinfo($this->nombre, PATHINFO_EXTENSION)); }

    public function getIconoAttribute(): string
    {
        return ['pdf' => 'bi-file-earmark-pdf', 'xml' => 'bi-filetype-xml', 'zip' => 'bi-file-earmark-zip'][$this->ext] ?? 'bi-file-earmark';
    }

    public function getPesoTextoAttribute(): string
    {
        return $this->peso >= 1048576 ? number_format($this->peso / 1048576, 1) . ' MB' : max(1, round($this->peso / 1024)) . ' KB';
    }

    public function getEtiquetaAttribute(): string
    {
        return 'Factura ' . (in_array($this->ext, ['pdf', 'xml', 'zip'], true) ? Str::upper($this->ext) : $this->nombre);
    }

    public function contenido(): ?string
    {
        return Storage::disk('local')->exists($this->ruta) ? Storage::disk('local')->get($this->ruta) : null;
    }

    public function respuesta(bool $descargar = true)
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($this->ruta), 404);
        $r = response()->file($disco->path($this->ruta), ['Content-Type' => $this->mime ?: 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
        $r->setContentDisposition($descargar ? 'attachment' : 'inline', $this->nombre, Str::ascii($this->nombre) ?: 'factura.' . $this->ext);
        return $r;
    }
}
