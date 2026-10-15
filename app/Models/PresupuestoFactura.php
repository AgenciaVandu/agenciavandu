<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Un archivo de la factura de una cotización (normalmente el PDF y el XML) */
class PresupuestoFactura extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $table = 'presupuesto_facturas';

    protected $fillable = ['tipo', 'lote', 'pago_id', 'fecha_pago', 'nombre', 'ruta', 'mime', 'peso', 'enviada_at'];

    protected $casts = ['peso' => 'integer', 'enviada_at' => 'datetime', 'fecha_pago' => 'date'];

    protected static function booted(): void
    {
        static::deleted(fn (PresupuestoFactura $f) => Storage::disk('local')->delete($f->ruta));
    }

    public function presupuesto(): BelongsTo
    {
        return $this->belongsTo(Presupuesto::class);
    }

    /** El pago del proyecto que ampara (solo complementos) */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(ProyectoPago::class, 'pago_id');
    }

    public function getEsComplementoAttribute(): bool
    {
        return $this->tipo === 'complemento';
    }

    public function getExtAttribute(): string
    {
        return Str::lower(pathinfo($this->nombre, PATHINFO_EXTENSION));
    }

    public function getIconoAttribute(): string
    {
        return ['pdf' => 'bi-file-earmark-pdf', 'xml' => 'bi-filetype-xml', 'zip' => 'bi-file-earmark-zip'][$this->ext] ?? 'bi-file-earmark';
    }

    public function getPesoTextoAttribute(): string
    {
        return $this->peso >= 1048576 ? number_format($this->peso / 1048576, 1) . ' MB' : max(1, round($this->peso / 1024)) . ' KB';
    }

    /** Lo que ve el cliente en el botón: "Factura PDF", "Factura XML" */
    public function getEtiquetaAttribute(): string
    {
        if ($this->tipo === 'nota') return 'Nota de venta' . ($this->ext === 'pdf' ? '' : ' ' . Str::upper($this->ext));
        return ($this->es_complemento ? 'Complemento de pago ' : 'Factura ') . (in_array($this->ext, ['pdf', 'xml', 'zip'], true) ? Str::upper($this->ext) : $this->nombre);
    }

    public function contenido(): ?string
    {
        return Storage::disk('local')->exists($this->ruta) ? Storage::disk('local')->get($this->ruta) : null;
    }

    /** Respuesta para descargar (o ver el PDF en el navegador) */
    public function respuesta(bool $descargar = true)
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($this->ruta), 404);
        $r = response()->file($disco->path($this->ruta), ['Content-Type' => $this->mime ?: 'application/octet-stream', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
        $r->setContentDisposition($descargar ? 'attachment' : 'inline', $this->nombre, Str::ascii($this->nombre) ?: 'factura.' . $this->ext);
        return $r;
    }
}
