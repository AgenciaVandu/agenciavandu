<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Presupuesto extends Model
{
    use \App\Models\Concerns\DeCuenta;

    public const ESTADOS = [
        'borrador'  => 'Borrador',
        'enviada'     => 'Enviada',
        'negociacion' => 'En negociación',
        'aceptada'  => 'Aceptada',
        'rechazada' => 'Rechazada',
    ];

    public const MODOS_IVA = [
        'mas_iva'    => 'Precios + IVA',
        'desglosado' => 'Desglosar IVA y total',
        'sin_iva'    => 'Sin IVA',
    ];

    protected $fillable = [
        'cliente_id', 'prospecto_id', 'cliente_nombre', 'cliente_empresa', 'fecha', 'titulo', 'tipo',
        'emisor_nombre', 'emisor_telefono', 'emisor_sitio', 'emisor_email',
        'modo_iva', 'metodo_sat', 'comprobante', 'nota_folio', 'iva_porcentaje', 'consideraciones', 'observaciones',
        'mostrar_pago', 'pago_intro', 'banco', 'clabe', 'beneficiario',
        'nota_comprobante', 'nota_factura',
        'vigente_hasta', 'estado', 'aceptada_el', 'notas_internas',
    ];

    protected $casts = [
        'fecha'           => 'date',
        'aceptada_el'     => 'date',
        'vigente_hasta'   => 'datetime',
        'ultima_vista_at' => 'datetime',
        'consideraciones' => 'array',
        'mostrar_pago'    => 'boolean',
        'iva_porcentaje'  => 'float',
    ];

    protected static function booted(): void
    {
        // La fecha de aceptación se llena sola; se puede editar para registrar ventas pasadas
        static::saving(function (Presupuesto $p) {
            // Una cotización para un prospecto: al aceptarse, el prospecto se vuelve cliente
            if ($p->estado === 'aceptada' && ! $p->cliente_id) $p->cliente_id = $p->asegurarCliente(false)->id;
            if ($p->estado === 'aceptada' && ! $p->aceptada_el) {
                $p->aceptada_el = now(config('vandu.zona_horaria'))->toDateString();
            } elseif ($p->estado !== 'aceptada') {
                $p->aceptada_el = null;
            }
        });

        // Ventas: al aceptarse, el prospecto ligado pasa a Ganado con el valor de la cotización
        static::saved(function (Presupuesto $p) {
            if ($p->estado !== 'aceptada' || ! ($p->wasChanged('estado') || $p->wasRecentlyCreated)) return;
            try {
                $pros = Prospecto::abiertos()->where(fn ($q) => $q->where('presupuesto_id', $p->id)->when($p->prospecto_id, fn ($q) => $q->orWhere('id', $p->prospecto_id)))->get();
                if ($pros->isEmpty() && $p->cliente_id) {
                    $pros = Prospecto::abiertos()->where('cliente_id', $p->cliente_id)->whereNull('presupuesto_id')->latest('id')->limit(1)->get();
                }
                foreach ($pros as $x) {
                    $x->fill(['presupuesto_id' => $p->id, 'valor' => $p->subtotal])->save();
                    $x->moverA(EmbudoEtapa::ganada(), auth()->id(), null, 'Aceptó la cotización ' . $p->folio);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });

        // Al borrarla también se borran los archivos de su factura
        static::deleting(fn (Presupuesto $p) => $p->archivosFiscales()->get()->each->delete());

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

    public function prospecto(): BelongsTo
    {
        return $this->belongsTo(Prospecto::class);
    }

    /** A quién va dirigida: su cliente, o el prospecto si todavía no es cliente (para correo y WhatsApp) */
    public function getContactoAttribute(): Cliente|Prospecto|null
    {
        return $this->cliente ?? $this->prospecto;
    }

    /** ¿Es para alguien que todavía no es cliente? */
    public function getParaProspectoAttribute(): bool
    {
        return ! $this->cliente_id && $this->prospecto_id;
    }

    /**
     * Devuelve su cliente; si es de un prospecto, lo vuelve cliente (y si el prospecto ya no existe,
     * lo crea con el nombre y la empresa de la cotización).
     */
    public function asegurarCliente(bool $guardar = true): Cliente
    {
        if ($this->cliente_id && ($c = $this->cliente)) return $c;
        $c = $this->prospecto?->volverCliente(auth()->id())
            ?? Cliente::create(['nombre' => $this->cliente_nombre, 'empresa' => $this->cliente_empresa, 'origen' => 'ventas']);
        $this->cliente_id = $c->id;
        $this->setRelation('cliente', $c);
        if ($guardar && $this->exists) $this->saveQuietly();
        return $c;
    }

    /** Archivos de su factura (PDF, XML) */
    public function facturas(): HasMany
    {
        // El comprobante de la cotización: la factura, o la nota de venta si se paga sin factura
        return $this->hasMany(PresupuestoFactura::class)->whereIn('tipo', ['factura', 'nota']);
    }

    /** ¿Se paga sin factura, con nota de venta? */
    public function getEsNotaAttribute(): bool
    {
        return $this->comprobante === 'nota';
    }

    /** "factura" o "nota": sirve de texto corto y de clave de la plantilla de correo */
    public function getComprobanteCortoAttribute(): string
    {
        return $this->es_nota ? 'nota' : 'factura';
    }

    /** Siguiente folio de nota de venta de la cuenta: NV-0001, NV-0002… */
    public static function siguienteFolioNota(): string
    {
        $n = static::whereNotNull('nota_folio')->pluck('nota_folio')->map(fn ($f) => (int) preg_replace('/\D/', '', $f))->max() ?? 0;
        return 'NV-' . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Complementos de pago (REP) de una factura PPD */
    public function complementos(): HasMany
    {
        return $this->hasMany(PresupuestoFactura::class)->where('tipo', 'complemento');
    }

    /** Todos los archivos fiscales: factura y complementos */
    public function archivosFiscales(): HasMany
    {
        return $this->hasMany(PresupuestoFactura::class);
    }

    /**
     * Complementos agrupados por lo que se subió junto (PDF + XML), del más reciente al más viejo:
     * [lote => ['archivos' => Collection, 'pago' => ?ProyectoPago, 'fecha' => ?Carbon, 'enviado' => ?Carbon, 'titulo' => string]]
     */
    public function getComplementosLotesAttribute(): \Illuminate\Support\Collection
    {
        $orden = fn ($f) => [['pdf' => 0, 'xml' => 1][$f->ext] ?? 2, $f->id];
        return $this->complementos->sortByDesc('id')->groupBy(fn ($f) => $f->lote ?: 'f' . $f->id)->map(function ($g) use ($orden) {
            $f = $g->first();
            $pago = $f->pago;
            return [
                'archivos' => $g->sortBy($orden)->values(),
                'pago'     => $pago,
                'fecha'    => $f->fecha_pago ?? $pago?->pagado_el,
                'enviado'  => $g->max('enviada_at'),
                'titulo'   => 'Complemento' . ($pago ? ' · ' . $pago->concepto : '') . (($f->fecha_pago ?? $pago?->pagado_el) ? ' · pago del ' . ($f->fecha_pago ?? $pago->pagado_el)->locale('es')->isoFormat('D MMM') : ''),
            ];
        });
    }

    /** PUE o PPD: el que se eligió, o PPD si el cliente paga a crédito */
    public function getMetodoSatEfectivoAttribute(): string
    {
        return $this->metodo_sat ?: ($this->cliente?->metodo_pago === 'credito' ? 'PPD' : 'PUE');
    }

    /** Los archivos de la factura en orden: PDF, XML y lo demás */
    public function getFacturasOrdenadasAttribute(): \Illuminate\Support\Collection
    {
        return $this->facturas->sortBy(fn ($f) => [['pdf' => 0, 'xml' => 1][$f->ext] ?? 2, $f->id])->values();
    }

    public function proyecto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Proyecto::class);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PresupuestoEvento::class)->latest('id');
    }

    public function codigos(): HasMany
    {
        return $this->hasMany(PresupuestoCodigo::class);
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
        $copia = $this->replicate(['folio', 'token', 'vistas', 'ultima_vista_at', 'aceptada_el']);
        $copia->fecha = now();
        $copia->estado = 'borrador';
        $copia->vigente_hasta = static::finDeDiaEnDias(config('vandu.vigencia_dias'));
        $copia->save();

        foreach ($this->conceptos as $concepto) {
            $copia->conceptos()->create($concepto->only(PresupuestoConcepto::COPIABLES));
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

    /** Renglones no vacíos de las observaciones */
    public function getObservacionesLineasAttribute(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->observaciones)), fn ($l) => $l !== ''));
    }

    /** Algún concepto se calculó con costos */
    public function getUsaCosteoAttribute(): bool
    {
        return $this->conceptos->contains(fn ($c) => $c->tiene_costeo);
    }

    /** Lo que se vendió en los conceptos con costeo (antes de IVA): la base del margen */
    public function getVentaCosteadaAttribute(): float
    {
        return round($this->conceptos->filter->tiene_costeo->sum(fn ($c) => $c->importe), 2);
    }

    /** Costo de una clase de gasto: tercero u operativo */
    public function costoDe(string $clase): float
    {
        return round($this->conceptos->sum(fn ($c) => $c->costoDe($clase)), 2);
    }

    /** Margen: qué parte de lo vendido (con costeo) es utilidad, en % */
    public function getMargenAttribute(): ?float
    {
        $venta = $this->venta_costeada;
        return $venta > 0 ? round($this->utilidad / $venta * 100, 1) : null;
    }

    /** Costo interno de los conceptos que tienen costeo */
    public function getCostoAttribute(): float
    {
        return round($this->conceptos->filter->tiene_costeo->sum(fn ($c) => $c->costo), 2);
    }

    /** Utilidad (antes de IVA) de los conceptos que tienen costeo */
    public function getUtilidadAttribute(): float
    {
        return round($this->conceptos->filter->tiene_costeo->sum(fn ($c) => $c->ganancia), 2);
    }

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

    /** En negociación sigue viva aunque pase su vigencia: no se da por perdida */
    public function getPerdidaAttribute(): bool
    {
        return $this->estado === 'rechazada'
            || (! in_array($this->estado, ['aceptada', 'negociacion'], true) && ! $this->vigente);
    }

    /** Abierta = se puede ganar todavía */
    public function getAbiertaAttribute(): bool
    {
        return $this->estado === 'negociacion'
            || (! in_array($this->estado, ['aceptada', 'rechazada'], true) && $this->vigente);
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
