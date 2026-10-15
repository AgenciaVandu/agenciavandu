<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Alguien que podría comprarte: llega del formulario del sitio o lo capturas tú, y avanza por el embudo */
class Prospecto extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const ORIGENES = [
        'sitio'    => ['texto' => 'Formulario del sitio', 'icono' => 'bi-globe2'],
        'whatsapp' => ['texto' => 'WhatsApp',             'icono' => 'bi-whatsapp'],
        'referido' => ['texto' => 'Recomendación',        'icono' => 'bi-people'],
        'redes'    => ['texto' => 'Redes sociales',       'icono' => 'bi-instagram'],
        'manual'   => ['texto' => 'Lo capturé yo',        'icono' => 'bi-pencil'],
        'otro'     => ['texto' => 'Otro',                 'icono' => 'bi-three-dots'],
    ];

    public const MOTIVOS = ['Precio', 'Eligió a otro proveedor', 'Sin presupuesto', 'Dejó de responder', 'No era lo que buscaba', 'Lo pospuso', 'Otro'];

    protected $fillable = ['nombre', 'empresa', 'email', 'telefono', 'origen', 'interes', 'valor', 'etapa_id', 'responsable_id', 'cliente_id',
                           'presupuesto_id', 'nuevo', 'notas', 'motivo_perdida', 'etapa_at', 'cerrado_at', 'contacto_at'];

    protected $casts = ['valor' => 'float', 'nuevo' => 'boolean', 'etapa_at' => 'datetime', 'cerrado_at' => 'datetime', 'contacto_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Prospecto $p) {
            $p->etapa_id ??= EmbudoEtapa::primera()->id;
            $p->etapa_at ??= now();
        });
    }

    public function etapa(): BelongsTo { return $this->belongsTo(EmbudoEtapa::class); }
    public function responsable(): BelongsTo { return $this->belongsTo(User::class, 'responsable_id'); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function presupuesto(): BelongsTo { return $this->belongsTo(Presupuesto::class); }
    public function eventos(): HasMany { return $this->hasMany(ProspectoEvento::class)->latest('id'); }
    public function tareas(): HasMany { return $this->hasMany(Tarea::class); }

    public function scopeAbiertos(Builder $q): Builder { return $q->whereHas('etapa', fn ($e) => $e->where('tipo', 'abierta')); }
    public function scopeGanados(Builder $q): Builder { return $q->whereHas('etapa', fn ($e) => $e->where('tipo', 'ganada')); }
    public function scopePerdidos(Builder $q): Builder { return $q->whereHas('etapa', fn ($e) => $e->where('tipo', 'perdida')); }

    public function getQuienAttribute(): string { return $this->empresa ?: $this->nombre; }

    /** Valor ponderado: lo que vale × la probabilidad de su etapa */
    public function getPonderadoAttribute(): float
    {
        return round((float) $this->valor * ($this->etapa?->probabilidad ?? 0) / 100, 2);
    }

    public function getDiasEnEtapaAttribute(): int
    {
        return (int) ($this->etapa_at ?? $this->created_at)->diffInDays(now());
    }

    public function getWhatsappAttribute(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) $this->telefono);
        if ($tel === '') return null;
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }

    /** Cotizaciones que se le han hecho (antes o después de volverse cliente) */
    public function cotizaciones(): HasMany { return $this->hasMany(Presupuesto::class); }

    /**
     * Lo vuelve cliente (o devuelve el que ya tiene). Sus cotizaciones hechas como prospecto pasan a ese cliente.
     */
    public function volverCliente(?int $userId = null): Cliente
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($userId) {
            $c = $this->cliente;
            if (! $c) {
                $c = Cliente::create([
                    'nombre'   => $this->nombre, 'empresa' => $this->empresa, 'email' => $this->email,
                    'telefono' => $this->telefono, 'origen' => $this->origen === 'sitio' ? 'sitio' : 'ventas',
                    'interes'  => $this->interes, 'notas' => $this->notas,
                ]);
                $this->update(['cliente_id' => $c->id]);
                $this->setRelation('cliente', $c);
                $this->eventos()->create(['tipo' => 'convertido', 'user_id' => $userId, 'texto' => 'Se volvió cliente']);
            }
            Presupuesto::where('prospecto_id', $this->id)->whereNull('cliente_id')->update(['cliente_id' => $c->id]);
            return $c;
        });
    }

    /** Lo mueve de etapa y lo anota en su historial. Al ganar o perder queda la fecha de cierre */
    public function moverA(EmbudoEtapa $etapa, ?int $userId = null, ?string $motivo = null, ?string $nota = null): void
    {
        if ($this->etapa_id === $etapa->id && ! $motivo) return;
        $de = $this->etapa_id;
        $this->fill([
            'etapa_id'       => $etapa->id,
            'etapa_at'       => now(),
            'cerrado_at'     => $etapa->cerrada ? now() : null,
            'motivo_perdida' => $etapa->tipo === 'perdida' ? ($motivo ?: 'Otro') : null,
            'nuevo'          => false,
        ])->save();
        $this->eventos()->create(['tipo' => 'etapa', 'de_etapa_id' => $de, 'a_etapa_id' => $etapa->id, 'user_id' => $userId,
            'texto' => $etapa->tipo === 'perdida' ? trim(($motivo ?: 'Otro') . ($nota ? ' · ' . $nota : '')) : $nota]);
    }

    /**
     * Mensaje del formulario del sitio: si ya hay un prospecto abierto con ese correo o teléfono lo actualiza,
     * si no, crea uno nuevo en la primera etapa. Si ya es cliente, el prospecto queda ligado a su ficha.
     * $origen distingue el formulario de cotizar ('sitio') del botón de WhatsApp ('whatsapp').
     */
    public static function desdeFormulario(array $d, string $origen = 'sitio'): self
    {
        $nombre = trim(($d['name'] ?? '') . ' ' . ($d['lastname'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $tel = preg_replace('/\D+/', '', (string) ($d['phone'] ?? ''));
        $mismoTel = fn ($x) => strlen($tel) >= 8 && substr(preg_replace('/\D+/', '', (string) $x->telefono), -10) === substr($tel, -10);

        $p = static::abiertos()->when($email, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))->latest('id')->first()
            ?? static::abiertos()->whereNotNull('telefono')->get(['id', 'telefono'])->first($mismoTel);
        $p = $p ? static::find($p->id) : null;   // la búsqueda por teléfono solo trae id y teléfono

        if (! $p) {
            $cliente = ($email ? Cliente::whereRaw('LOWER(email) = ?', [$email])->first() : null)
                ?? Cliente::whereNotNull('telefono')->get(['id', 'telefono'])->first($mismoTel);
            $cliente = $cliente ? Cliente::find($cliente->id) : null;
            $p = new static(['origen' => $origen, 'cliente_id' => $cliente?->id, 'empresa' => $cliente?->empresa ?: ($d['company'] ?? null)]);
        }
        $p->nombre = $p->nombre ?: ($nombre ?: $email);
        $p->empresa = $p->empresa ?: (($d['company'] ?? null) ?: null);
        $p->email = $p->email ?: ($email ?: null);
        $p->telefono = $p->telefono ?: ($d['phone'] ?? null);
        $p->fill(['nuevo' => true, 'interes' => $d['service'] ?? $p->interes, 'contacto_at' => now()])->save();

        if ($p->wasRecentlyCreated) {
            $p->eventos()->create(['tipo' => 'alta', 'a_etapa_id' => $p->etapa_id,
                'texto' => $origen === 'whatsapp' ? 'Llegó desde el botón de WhatsApp del sitio' : 'Llegó desde el formulario del sitio']);
        }
        $p->eventos()->create(['tipo' => 'mensaje', 'texto' => $d['message'] ?? ($d['service'] ?? null), 'datos' => $d]);

        return $p;
    }
}
