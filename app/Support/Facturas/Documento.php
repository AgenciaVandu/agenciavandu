<?php

namespace App\Support\Facturas;

use App\Models\Cliente;
use App\Models\ClienteRazon;
use App\Models\Presupuesto;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Lo que se factura: una cotización aceptada o una venta directa (pedido). Esta clase los trata igual
 * para la bandeja de facturas, el envío y el timbrado.
 */
class Documento
{
    public function __construct(public Presupuesto|Venta $m) {}

    public static function de(string $tipo, int $id): self
    {
        return new self($tipo === 'venta' ? Venta::with(['cliente', 'facturas', 'razon'])->findOrFail($id) : Presupuesto::with(['cliente', 'facturas', 'razon', 'conceptos'])->findOrFail($id));
    }

    public function tipo(): string { return $this->m instanceof Venta ? 'venta' : 'presupuesto'; }
    public function clave(): string { return $this->tipo() . '-' . $this->m->id; }
    public function folio(): string { return $this->m instanceof Venta ? $this->m->folio : $this->m->folio; }
    public function cliente(): ?Cliente { return $this->m->cliente; }

    public function quien(): string
    {
        return $this->m instanceof Venta ? $this->m->quien : ($this->m->cliente_empresa ?: $this->m->cliente_nombre ?: 'Cliente');
    }

    /** De qué es: el título de la cotización o lo que se vendió ("Paletas para evento y 2 más") */
    public function concepto(): ?string
    {
        $m = $this->m;
        if ($m instanceof Presupuesto) {
            $t = trim((string) ($m->titulo ?: $m->conceptos->first()?->resumen));
        } else {
            $c = $m->relationLoaded('conceptos') ? $m->conceptos : $m->conceptos()->get(['id', 'descripcion']);
            $t = trim((string) $c->first()?->descripcion);
            if ($t !== '' && $c->count() > 1) $t .= ' y ' . ($c->count() - 1) . ' más';
        }
        return $t === '' ? null : \Illuminate\Support\Str::limit($t, 70);
    }

    /** La razón social con la que se factura: la elegida, o la principal del cliente si tiene datos fiscales */
    public function razon(): ?ClienteRazon
    {
        return $this->m->razon;
    }

    /** RFC, razón social, régimen y CP (de la razón elegida o de la ficha del cliente) */
    public function fiscales(): array
    {
        $r = $this->razon();
        $c = $this->cliente();
        return [
            'rfc' => $r?->rfc ?: $c?->rfc, 'razon_social' => $r?->razon_social ?: $c?->razon_social,
            'regimen' => $r?->regimen_fiscal ?: $c?->regimen_fiscal, 'cp' => $r?->cp_fiscal ?: $c?->cp_fiscal,
            'uso' => $r?->uso_cfdi ?: ($c?->uso_cfdi ?? null) ?: 'G03',
            'email' => $r?->email_factura ?: ($c?->email_factura ?? null) ?: $c?->email,
        ];
    }

    public function fiscalesCompletos(): bool
    {
        $f = $this->fiscales();
        return filled($f['rfc']) && filled($f['razon_social']) && filled($f['regimen']) && filled($f['cp']);
    }

    public function correo(): ?string
    {
        return $this->fiscales()['email'] ?: null;
    }

    public function whatsapp(): ?string
    {
        if ($this->m instanceof Presupuesto) return $this->m->contacto?->whatsapp;
        $tel = preg_replace('/\D/', '', (string) ($this->m->cliente?->telefono ?: $this->m->cliente_telefono));
        return $tel ? (strlen($tel) === 10 ? '52' . $tel : $tel) : null;
    }

    /** Total con IVA (lo que debe decir la factura) */
    public function total(): float
    {
        return round((float) $this->m->total, 2);
    }

    public function fecha(): ?Carbon
    {
        return $this->m instanceof Venta ? $this->m->fecha : ($this->m->aceptada_el ?? $this->m->fecha);
    }

    /** Forma de pago del SAT según el último cobro registrado */
    public function formaSat(): string
    {
        $forma = $this->m instanceof Venta ? $this->m->pagos()->reorder()->latest('id')->value('forma') : null;
        return Timbrado::formaDe($forma);
    }

    public function solicitada(): ?Carbon { return $this->m->factura_solicitada_at; }

    /** Archivos de la factura (no notas de venta), PDF primero */
    public function archivos(): Collection
    {
        return $this->m->facturas->where('tipo', 'factura')->sortBy(fn ($f) => [['pdf' => 0, 'xml' => 1][$f->ext] ?? 2, $f->id])->values();
    }

    public function enviada(): ?Carbon
    {
        $a = $this->archivos();
        return $a->isNotEmpty() && $a->every(fn ($f) => $f->enviada_at) ? $a->max('enviada_at') : null;
    }

    /** La factura que timbró Nerby (se puede cancelar desde aquí) */
    public function facturapiId(): ?string { return $this->archivos()->firstWhere('facturapi_id')?->facturapi_id; }

    /** null, 'pendiente' (el cliente debe aceptar la cancelación) */
    public function cancelacion(): ?string { return $this->archivos()->firstWhere('cancelacion')?->cancelacion; }

    public function uuid(): ?string { return $this->archivos()->firstWhere('uuid')?->uuid; }

    /** El XML dice otro total o RFC: se avisa antes de enviarla */
    public function diferencias(): array
    {
        $x = $this->archivos()->first(fn ($f) => $f->uuid);
        if (! $x) return [];
        $d = [];
        if ($x->total_cfdi !== null && abs($x->total_cfdi - $this->total()) > 1) $d[] = 'El XML dice $' . number_format($x->total_cfdi, 2) . ' y la venta $' . number_format($this->total(), 2);
        $rfc = mb_strtoupper((string) $this->fiscales()['rfc']);
        if ($x->rfc_receptor && $rfc && $x->rfc_receptor !== $rfc) $d[] = "El XML es para el RFC {$x->rfc_receptor} y el cliente tiene {$rfc}";
        return $d;
    }

    public function url(): string
    {
        if ($this->m instanceof Venta) {
            return \App\Support\Cuentas::actual()?->tiene('stock') && ! $this->m->metodo_pago ? route('admin.pedidos.show', $this->m) : route('admin.facturas.venta', $this->m);
        }
        return route('admin.presupuestos.edit', [$this->m, 'ver' => 'factura']);
    }

    /** Enlace público para que el cliente descargue un archivo */
    public function enlaceArchivo($f): string
    {
        return $this->m instanceof Venta ? route('pedido.publico.factura', [$this->m->token, $f->id]) : route('presupuesto.factura', [$this->m->token, $f->id]);
    }

    public function relacionArchivos()
    {
        return $this->m->facturas();
    }

    /** Dónde guardar en disco */
    public function carpeta(): string
    {
        return ($this->m instanceof Venta ? 'ventas' : 'presupuestos') . "/{$this->m->id}/facturas";
    }
}
