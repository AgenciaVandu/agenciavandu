<?php

namespace App\Support\Facturas;

use App\Models\Presupuesto;
use App\Models\Venta;
use App\Support\Cuentas;
use Illuminate\Support\Collection;

/**
 * Bandeja de facturas: qué falta facturar, qué falta enviar y qué ya se envió,
 * juntando cotizaciones aceptadas y ventas directas.
 */
class Bandeja
{
    private static function ventas(): bool { $c = Cuentas::actual(); return (bool) ($c?->tiene('stock') || $c?->tiene('facturacion')); }

    /** Negocio que solo factura (sin ventas, cotizaciones ni proyectos) */
    public static function soloFacturacion(): bool
    {
        $c = Cuentas::actual();
        return (bool) $c?->tiene('facturacion') && ! $c->tiene('stock') && ! $c->tiene('cotizaciones') && ! $c->tiene('proyectos') && ! $c->tiene('fitness') && ! $c->tiene('autos');
    }
    private static function cotizaciones(): bool { return (bool) Cuentas::actual()?->tiene('cotizaciones'); }

    /**
     * Por facturar: cotizaciones aceptadas que no se pagan con nota y no tienen factura; ventas directas sin factura
     * que el cliente pidió o cuyo cliente tiene datos fiscales (al mostrador no se le factura).
     */
    public static function porFacturar(): Collection
    {
        $r = collect();
        if (self::cotizaciones()) {
            Presupuesto::with(['cliente', 'facturas', 'razon'])->where('estado', 'aceptada')->where(fn ($q) => $q->whereNull('comprobante')->orWhere('comprobante', '!=', 'nota'))
                ->whereDoesntHave('facturas', fn ($q) => $q->where('tipo', 'factura'))->latest('id')->take(150)->get()
                ->each(fn ($p) => $r->push(new Documento($p)));
        }
        if (self::ventas()) {
            Venta::with(['cliente', 'facturas', 'razon'])->vigentes()->whereNull('presupuesto_id')->whereDoesntHave('facturas', fn ($q) => $q->where('tipo', 'factura'))
                ->where(fn ($q) => $q->whereNotNull('factura_solicitada_at')->orWhereHas('cliente', fn ($c) => $c->whereNotNull('rfc')->where('rfc', '!=', '')))
                ->latest('id')->take(150)->get()
                ->each(fn ($v) => $r->push(new Documento($v)));
        }
        // Primero lo que el cliente pidió, luego lo más reciente
        return $r->sortBy(fn (Documento $d) => [$d->solicitada() ? 0 : 1, -($d->fecha()?->timestamp ?? 0)])->values();
    }

    /** Con factura subida que el cliente aún no recibe */
    public static function porEnviar(): Collection
    {
        return self::conFactura(fn ($q) => $q->whereHas('facturas', fn ($f) => $f->where('tipo', 'factura')->whereNull('enviada_at')))
            ->sortByDesc(fn (Documento $d) => $d->archivos()->max('created_at'))->values();
    }

    /** Ya enviadas (las más recientes) */
    public static function enviadas(int $max = 80): Collection
    {
        return self::conFactura(fn ($q) => $q->whereHas('facturas', fn ($f) => $f->where('tipo', 'factura'))->whereDoesntHave('facturas', fn ($f) => $f->where('tipo', 'factura')->whereNull('enviada_at')))
            ->sortByDesc(fn (Documento $d) => $d->enviada())->take($max)->values();
    }

    private static function conFactura(callable $filtro): Collection
    {
        $r = collect();
        if (self::cotizaciones()) $filtro(Presupuesto::with(['cliente', 'facturas', 'razon']))->latest('id')->take(200)->get()->each(fn ($p) => $r->push(new Documento($p)));
        if (self::ventas()) $filtro(Venta::with(['cliente', 'facturas', 'razon']))->latest('id')->take(200)->get()->each(fn ($v) => $r->push(new Documento($v)));
        return $r;
    }

    /** Para el número del menú: facturas pedidas por clientes + listas para enviar */
    public static function pendientes(): int
    {
        try {
            $n = 0;
            if (self::cotizaciones()) {
                $n += Presupuesto::whereNotNull('factura_solicitada_at')->whereDoesntHave('facturas', fn ($q) => $q->where('tipo', 'factura'))->count();
                $n += Presupuesto::whereHas('facturas', fn ($f) => $f->where('tipo', 'factura')->whereNull('enviada_at'))->count();
            }
            if (self::ventas()) {
                $n += Venta::vigentes()->whereNotNull('factura_solicitada_at')->whereDoesntHave('facturas', fn ($q) => $q->where('tipo', 'factura'))->count();
                $n += Venta::whereHas('facturas', fn ($f) => $f->where('tipo', 'factura')->whereNull('enviada_at'))->count();
            }
            return $n;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
