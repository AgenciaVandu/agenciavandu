<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lo que le pasa a un prospecto: cambia de etapa, una nota, un mensaje del sitio, se convierte… */
class ProspectoEvento extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const UPDATED_AT = null;

    protected $fillable = ['tipo', 'de_etapa_id', 'a_etapa_id', 'user_id', 'texto', 'datos'];

    protected $casts = ['datos' => 'array'];

    public function prospecto(): BelongsTo { return $this->belongsTo(Prospecto::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function de(): BelongsTo { return $this->belongsTo(EmbudoEtapa::class, 'de_etapa_id'); }
    public function a(): BelongsTo { return $this->belongsTo(EmbudoEtapa::class, 'a_etapa_id'); }
}
