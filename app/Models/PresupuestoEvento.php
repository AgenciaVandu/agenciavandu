<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un momento en la vida de la cotización: enviada, editada, cambios solicitados, aceptada… */
class PresupuestoEvento extends Model
{
    public const UPDATED_AT = null;

    public const TIPOS = [
        'creada'      => ['texto' => 'Cotización creada',              'icono' => 'bi-file-earmark-plus'],
        'enviada'     => ['texto' => 'Enviada',                        'icono' => 'bi-send'],
        'codigo'      => ['texto' => 'Código de verificación enviado', 'icono' => 'bi-shield-lock'],
        'editada'     => ['texto' => 'Cotización actualizada',         'icono' => 'bi-pencil-square'],
        'cambios'     => ['texto' => 'Cambios solicitados',            'icono' => 'bi-chat-left-text'],
        'aceptada'    => ['texto' => 'Cotización aceptada',            'icono' => 'bi-check-circle-fill'],
        'estado'      => ['texto' => 'Cambio de estado',               'icono' => 'bi-arrow-left-right'],
        'vigencia'    => ['texto' => 'Vigencia extendida',             'icono' => 'bi-calendar-plus'],
        'bloqueo'     => ['texto' => 'Demasiados intentos con código', 'icono' => 'bi-shield-exclamation'],
    ];

    protected $fillable = ['presupuesto_id', 'tipo', 'actor', 'autor', 'detalle', 'datos', 'visible_cliente', 'ip'];

    protected $casts = ['datos' => 'array', 'visible_cliente' => 'boolean'];

    public function presupuesto(): BelongsTo { return $this->belongsTo(Presupuesto::class); }

    public function getTituloAttribute(): string
    {
        if ($this->tipo === 'estado' && ! empty($this->datos['a'])) {
            return 'Estado: ' . (Presupuesto::ESTADOS[$this->datos['a']] ?? $this->datos['a']);
        }
        if ($this->tipo === 'editada' && ! empty($this->datos['version'])) {
            return 'Versión ' . $this->datos['version'];
        }
        return self::TIPOS[$this->tipo]['texto'] ?? ucfirst($this->tipo);
    }

    public function getIconoAttribute(): string
    {
        return self::TIPOS[$this->tipo]['icono'] ?? 'bi-dot';
    }
}
