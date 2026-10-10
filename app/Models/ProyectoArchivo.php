<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProyectoArchivo extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['etapa_id', 'grupo', 'origen', 'dropbox_id', 'nombre', 'ruta', 'mime', 'peso', 'vista', 'miniatura', 'ancho', 'alto', 'visible', 'orden'];

    protected $casts = ['visible' => 'boolean', 'peso' => 'integer'];

    protected static function booted(): void
    {
        // Al borrar el registro se borran también sus archivos
        static::deleted(function (ProyectoArchivo $a) {
            $locales = array_filter([$a->vista, $a->miniatura, $a->ruta_correo]);
            if ($a->origen === 'dropbox') {
                // Va a la papelera de Dropbox (se puede recuperar desde dropbox.com)
                try { \App\Support\Dropbox\Dropbox::cliente()->borrar($a->dropbox_id); } catch (\Throwable $e) { report($e); }
            } else {
                $locales[] = $a->ruta;
            }
            Storage::disk('local')->delete($locales);
        });
    }

    public function proyecto(): BelongsTo { return $this->belongsTo(Proyecto::class); }
    public function etapa(): BelongsTo { return $this->belongsTo(ProyectoEtapa::class, 'etapa_id'); }

    /** Recorte cuadrado para correos (se genera al pedirlo) */
    public function getRutaCorreoAttribute(): string
    {
        return "proyectos/{$this->proyecto_id}/optimizadas/correo-{$this->id}.jpg";
    }

    public function getEnDropboxAttribute(): bool
    {
        return $this->origen === 'dropbox';
    }

    public function getEsImagenAttribute(): bool { return str_starts_with((string) $this->mime, 'image/'); }
    public function getEsVideoAttribute(): bool { return str_starts_with((string) $this->mime, 'video/'); }

    public function getPesoTextoAttribute(): string
    {
        $b = $this->peso;
        return match (true) {
            $b >= 1073741824 => number_format($b / 1073741824, 1) . ' GB',
            $b >= 1048576    => number_format($b / 1048576, 1) . ' MB',
            $b >= 1024       => number_format($b / 1024, 0) . ' KB',
            default          => $b . ' B',
        };
    }

    public function getIconoAttribute(): string
    {
        $ext = strtolower(pathinfo($this->nombre, PATHINFO_EXTENSION));
        return match (true) {
            $this->es_imagen                         => 'bi-file-earmark-image',
            $this->es_video                          => 'bi-file-earmark-play',
            $ext === 'pdf'                           => 'bi-file-earmark-pdf',
            in_array($ext, ['zip', 'rar', '7z'])     => 'bi-file-earmark-zip',
            in_array($ext, ['doc', 'docx'])          => 'bi-file-earmark-word',
            in_array($ext, ['xls', 'xlsx', 'csv'])   => 'bi-file-earmark-spreadsheet',
            in_array($ext, ['fig', 'sketch', 'xd'])  => 'bi-vector-pen',
            default                                  => 'bi-file-earmark',
        };
    }
}
