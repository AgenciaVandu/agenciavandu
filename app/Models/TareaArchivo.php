<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TareaArchivo extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = ['tarea_id', 'user_id', 'nombre', 'ruta', 'dropbox_id', 'tamano'];

    public function tarea(): BelongsTo { return $this->belongsTo(Tarea::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function getTipoAttribute(): string
    {
        $mime = \App\Support\ArchivosProyecto::mimeDe($this->nombre);
        return str_starts_with($mime, 'image/') ? 'foto' : (str_starts_with($mime, 'video/') ? 'video' : 'archivo');
    }

    public function getPesoAttribute(): string
    {
        $b = (int) $this->tamano;
        return match (true) {
            $b >= 1073741824 => number_format($b / 1073741824, 1) . ' GB',
            $b >= 1048576    => number_format($b / 1048576, 1) . ' MB',
            default          => max(1, round($b / 1024)) . ' KB',
        };
    }
}
