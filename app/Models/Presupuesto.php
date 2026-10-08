<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Presupuesto extends Model
{
    public const ESTADOS = [
        'borrador'  => 'Borrador',
        'enviada'   => 'Enviada',
        'aceptada'  => 'Aceptada',
        'rechazada' => 'Rechazada',
    ];

    public const MODOS_IVA = [
        'mas_iva'    => 'Precios + IVA',
        'desglosado' => 'Desglosar IVA y total',
        'sin_iva'    => 'Sin IVA',
    ];

    protected $fillable = [
        'cliente_id', 'cliente_nombre', 'cliente_empresa', 'fecha', 'titulo',
        'emisor_nombre', 'emisor_telefono', 'emisor_sitio', 'emisor_email',
        'modo_iva', 'iva_porcentaje', 'consideraciones',
        'mostrar_pago', 'pago_intro', 'banco', 'clabe', 'beneficiario',
        'nota_comprobante', 'nota_factura',
        'vigente_hasta', 'estado', 'notas_internas',
    ];

    protected $casts = [
        'fecha'           => 'date',
        'vigente_hasta'   => 'datetime',
        'ultima_vista_at' => 'datetime',
        'consideraciones' => 'array',
        'mostrar_pago'    => 'boolean',
        'iva_porcentaje'  => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Presupuesto $p) {
            $p->token ??= Str::random(32);
            $p->folio ??= static::siguienteFolio();
        });
    }

    /* ---------------- Relaciones ---------------- */

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(PresupuestoConcepto::class)->orderBy('orden');
    }

    /* ---------------- Fábricas ---------------- */

    /** Cotización nueva con los valores por defecto de config/vandu.php */
    public static function nuevaPara(?Cliente $cliente = null): self
    {
        $c = config('vandu');

        return new self([
            'cliente_id'       => $cliente?->id,
            'cliente_nombre'   => $cliente?->nombre,
            'cliente_empresa'  => $cliente?->empresa,
            'fecha'            => now(),
            'titulo'           => $c['titulo'],
            'emisor_nombre'    => $c['emisor']['nombre'],
            'emisor_telefono'  => $c['emisor']['telefono'],
            'emisor_sitio'     => $c['emisor']['sitio'],
            'emisor_email'     => $c['emisor']['email'],
            'modo_iva'         => 'mas_iva',
            'iva_porcentaje'   => $c['iva_porcentaje'],
            'consideraciones'  => $c['consideraciones'],
            'mostrar_pago'     => true,
            'pago_intro'       => $c['pago']['intro'],
            'banco'            => $c['pago']['banco'],
            'clabe'            => $c['pago']['clabe'],
            'beneficiario'     => $c['pago']['beneficiario'],
            'nota_comprobante' => $c['pago']['nota_comprobante'],
            'nota_factura'     => $c['pago']['nota_factura'],
            'vigente_hasta'    => static::finDeDiaEnDias($c['vigencia_dias']),
            'estado'           => 'borrador',
        ]);
    }

    /** Copia completa (nuevo folio, nuevo enlace, fecha de hoy, vigencia reiniciada) */
    public function duplicar(): self
    {
        $copia = $this->replicate(['folio', 'token', 'vistas', 'ultima_vista_at']);
        $copia->fecha = now();
        $copia->estado = 'borrador';
        $copia->vigente_hasta = static::finDeDiaEnDias(config('vandu.vigencia_dias'));
        $copia->save();

        foreach ($this->conceptos as $concepto) {
            $copia->conceptos()->create($concepto->only(['descripcion', 'cantidad', 'precio', 'orden']));
        }

        return $copia;
    }

    public static function siguienteFolio(): string
    {
        $prefijo = config('vandu.folio_prefijo', 'CT-');
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
        return $this->modo_iva === 'sin_iva' ? 0 : round($this->subtotal * $this->iva_porcentaje / 100, 2);
    }

    public function getTotalAttribute(): float
    {
        return round($this->subtotal + $this->iva, 2);
    }

    /** "$855.00 + IVA" en modo original, "$855.00" en los demás */
    public function monto(float $valor, bool $sufijo = true): string
    {
        $txt = '$' . number_format($valor, 2);
        return ($sufijo && $this->modo_iva === 'mas_iva') ? $txt . ' + IVA' : $txt;
    }

    /* ---------------- Vigencia ---------------- */

    /** Fin del día (23:59) dentro de N días, en la zona de Vandu, convertido a la zona de la app */
    public static function finDeDiaEnDias(int $dias): Carbon
    {
        return now(config('vandu.zona_horaria'))->addDays($dias)->endOfDay()->setTimezone(config('app.timezone'));
    }

    /** La vigencia expresada en la hora local de Vandu (para mostrar/editar) */
    public function getVigenciaLocalAttribute(): Carbon
    {
        return $this->vigente_hasta->copy()->setTimezone(config('vandu.zona_horaria'));
    }

    public function getVigenteAttribute(): bool
    {
        return $this->vigente_hasta->isFuture();
    }

    /** "24 de Julio de 2026" (igual que el formato original) */
    public function getFechaLargaAttribute(): string
    {
        return static::fechaLarga($this->fecha);
    }

    public static function fechaLarga(Carbon $fecha): string
    {
        $meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
            'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $fecha->day . ' de ' . $meses[$fecha->month] . ' de ' . $fecha->year;
    }

    /** Consideraciones sin secciones/viñetas vacías */
    public function getConsideracionesLimpiasAttribute(): array
    {
        return collect($this->consideraciones ?? [])
            ->map(fn ($s) => [
                'titulo' => trim($s['titulo'] ?? ''),
                'items'  => array_values(array_filter(array_map('trim', $s['items'] ?? []), 'strlen')),
            ])
            ->filter(fn ($s) => $s['titulo'] !== '' || count($s['items']))
            ->values()
            ->all();
    }

    public function getUrlPublicaAttribute(): string
    {
        return route('presupuesto.publico', $this->token);
    }

    public function getNombreArchivoAttribute(): string
    {
        $nombre = Str::slug($this->cliente_empresa ?: $this->cliente_nombre);
        return "{$this->folio}-{$nombre}.pdf";
    }
}
