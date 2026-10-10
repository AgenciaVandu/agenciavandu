<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Cliente extends Model
{
    use \App\Models\Concerns\DeCuenta;

    protected $fillable = [
        'nombre', 'empresa', 'email', 'telefono',
        'rfc', 'razon_social', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'metodo_pago', 'dias_credito', 'email_factura', 'notas',
        'origen', 'nuevo', 'interes', 'contacto_at',
    ];

    protected $casts = ['nuevo' => 'boolean', 'contacto_at' => 'datetime'];

    protected static function booted(): void
    {
        // Al borrar el cliente se borra su expediente de constancias
        static::deleting(function (Cliente $c) {
            $c->constancias->each->delete(); // las de Dropbox van a su papelera
            Storage::disk('local')->deleteDirectory("clientes/{$c->id}");
        });
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ClienteMensaje::class)->latest('id');
    }

    /**
     * Registra un mensaje del formulario del sitio: si ya existe (mismo correo o teléfono) lo actualiza
     * sin borrar lo que ya tenías; si no, lo crea. En ambos casos queda marcado como "Nuevo".
     */
    public static function desdeFormulario(array $d): self
    {
        $nombre = trim(($d['name'] ?? '') . ' ' . ($d['lastname'] ?? ''));
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $tel = preg_replace('/\D+/', '', (string) ($d['phone'] ?? ''));

        $c = static::query()
            ->when($email, fn ($q) => $q->whereRaw('LOWER(email) = ?', [$email]))
            ->first();
        if (! $c && strlen($tel) >= 8) {
            $c = static::all(['id', 'telefono'])->first(fn ($x) => substr(preg_replace('/\D+/', '', (string) $x->telefono), -10) === substr($tel, -10));
            $c = $c ? static::find($c->id) : null;
        }

        $c ??= new static(['origen' => 'sitio']);
        $c->nombre = $c->nombre ?: ($nombre ?: $email);
        $c->email = $c->email ?: $email;
        $c->telefono = $c->telefono ?: ($d['phone'] ?? null);
        $c->fill(['nuevo' => true, 'interes' => $d['service'] ?? null, 'contacto_at' => now()])->save();

        $c->mensajes()->create(['servicio' => $d['service'] ?? null, 'datos' => $d]);

        return $c;
    }

    public function constancias(): HasMany
    {
        return $this->hasMany(ClienteConstancia::class)->latest('id');
    }

    /** Ya hay lo mínimo para facturar */
    public function getFiscalesCompletosAttribute(): bool
    {
        return filled($this->rfc) && filled($this->razon_social) && filled($this->regimen_fiscal) && filled($this->cp_fiscal);
    }

    public function getRegimenTextoAttribute(): ?string
    {
        return $this->regimen_fiscal ? $this->regimen_fiscal . ' · ' . (config("vandu.sat.regimenes.{$this->regimen_fiscal}") ?? '') : null;
    }

    public function getUsoCfdiTextoAttribute(): ?string
    {
        return $this->uso_cfdi ? $this->uso_cfdi . ' · ' . (config("vandu.sat.usos_cfdi.{$this->uso_cfdi}") ?? '') : null;
    }

    public function getMetodoPagoTextoAttribute(): ?string
    {
        return $this->metodo_pago ? config("vandu.metodos_pago.{$this->metodo_pago}", $this->metodo_pago) : null;
    }

    /** Bloque listo para pegar en el sistema de facturación */
    public function getFiscalesTextoAttribute(): string
    {
        return collect([
            'RFC'            => $this->rfc,
            'Razón social'   => $this->razon_social,
            'Régimen fiscal' => $this->regimen_texto,
            'Código postal'  => $this->cp_fiscal,
            'Uso de CFDI'    => $this->uso_cfdi_texto,
            'Método de pago' => $this->metodo_pago_texto . ($this->metodo_pago === 'credito' && $this->dias_credito ? " a {$this->dias_credito} días" : ''),
            'Correo'         => $this->email_factura ?: $this->email,
        ])->filter()->map(fn ($v, $k) => "$k: $v")->join("\n");
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class)->latest();
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class)->latest();
    }

    /** Teléfono solo con dígitos y lada 52 para wa.me */
    public function getWhatsappAttribute(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) $this->telefono);
        if ($tel === '') {
            return null;
        }
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }
}
