<?php

namespace App\Support\Facturas;

use App\Models\FacturaGlobal;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Factura global al público en general: las ventas de mostrador ya cobradas que nadie pidió facturar,
 * de un día, semana, quincena, mes o bimestre, en un solo CFDI (RFC XAXX010101000, régimen 616, uso S01).
 */
class Globales
{
    /** Ventas que entran: cobradas, sin factura, sin global, sin cotización y de clientes sin RFC (o de mostrador) */
    public static function candidatas(string $desde, string $hasta): Collection
    {
        return Venta::with(['pagos', 'cliente'])->vigentes()->whereNull('presupuesto_id')->whereNull('global_id')
            ->whereDoesntHave('facturas', fn ($q) => $q->where('tipo', 'factura'))
            ->whereNull('factura_solicitada_at')
            ->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)
            ->where(fn ($q) => $q->whereNull('cliente_id')->orWhereHas('cliente', fn ($c) => $c->whereNull('rfc')->orWhere('rfc', '')))
            ->orderBy('fecha')->orderBy('id')->get()
            ->filter(fn (Venta $v) => $v->pagada)->values();
    }

    /** "months" del SAT: el mes (01–12) o, para bimestral, 13 = ene-feb … 18 = nov-dic */
    public static function meses(string $periodicidad, Carbon $hasta): string
    {
        return $periodicidad === 'two_months' ? (string) (12 + (int) ceil($hasta->month / 2)) : $hasta->format('m');
    }

    public static function emitir(string $desde, string $hasta, string $periodicidad, ?string $cp, ?int $serieId = null): FacturaGlobal
    {
        $ventas = self::candidatas($desde, $hasta);
        if ($ventas->isEmpty()) throw new TimbradoError('No hay ventas de mostrador cobradas sin factura en esas fechas.');
        if (count($ventas) > 1000) throw new TimbradoError('Son más de 1,000 ventas: haz la global por periodos más cortos.');
        $cp = $cp ?: (Timbrado::ajustes()['emisor']['cp'] ?? null);
        if (! preg_match('/^\d{5}$/', (string) $cp)) throw new TimbradoError('Falta el código postal de tu domicilio fiscal (lugar de expedición).');

        $aj = Timbrado::ajustes();
        $items = [];
        foreach ($ventas as $v) {
            $tasa = $v->con_iva ? round($v->iva_porcentaje / 100, 4) : null;
            $base = round(max(0, $v->subtotal - $v->descuento), 2);
            if ($base > 0) $items[] = ['quantity' => 1, 'product' => ['description' => 'Venta', 'product_key' => '01010101', 'unit_key' => 'ACT', 'sku' => $v->folio,
                'price' => $base, 'tax_included' => false, 'taxes' => $tasa !== null ? [['type' => 'IVA', 'rate' => $tasa]] : []]];
            if ($v->envio_costo > 0) $items[] = ['quantity' => 1, 'product' => ['description' => 'Envío', 'product_key' => '01010101', 'unit_key' => 'ACT', 'sku' => $v->folio,
                'price' => round($v->envio_costo, 2), 'tax_included' => false, 'taxes' => []]];
        }
        // La forma de pago que más se usó
        $forma = $ventas->flatMap->pagos->groupBy('forma')->map->sum('monto')->sortDesc()->keys()->first();
        $h = Carbon::parse($hasta);
        $payload = [
            'customer' => ['legal_name' => 'PUBLICO EN GENERAL', 'tax_id' => 'XAXX010101000', 'tax_system' => '616', 'address' => ['zip' => $cp]],
            'global' => ['periodicity' => $periodicidad, 'months' => self::meses($periodicidad, $h), 'year' => (int) $h->year],
            'items' => $items,
            'payment_form' => Timbrado::formaDe($forma),
            'payment_method' => 'PUE',
            'use' => 'S01',
            'external_id' => 'global-' . $desde . '-' . $hasta,
        ];
        $e = Timbrado::emitir($payload, 'G', $serieId, 'Global ' . $desde . ' a ' . $hasta);

        $g = FacturaGlobal::create([
            'serie' => $e['serie'], 'folio' => $e['folio'], 'periodicidad' => $periodicidad, 'meses' => $payload['global']['months'], 'anio' => $payload['global']['year'],
            'desde' => $desde, 'hasta' => $hasta, 'ventas' => $ventas->count(),
            'subtotal' => round($ventas->sum(fn ($v) => max(0, $v->subtotal - $v->descuento) + $v->envio_costo), 2),
            'iva' => round($ventas->sum('iva'), 2), 'total' => round($ventas->sum('total'), 2),
            'facturapi_id' => $e['id'], 'uuid' => $e['json']['uuid'] ?? null, 'user_id' => auth()->id(),
        ]);
        foreach (['pdf', 'xml'] as $ext) {
            if ($e[$ext] === null) continue;
            $ruta = "globales/{$g->id}/" . Str::uuid() . ".$ext";
            Storage::disk('local')->put($ruta, $e[$ext]);
            $g->$ext = $ruta;
        }
        $g->save();
        Venta::whereIn('id', $ventas->pluck('id'))->update(['global_id' => $g->id]);
        return $g;
    }

    public static function cancelar(FacturaGlobal $g, string $motivo): string
    {
        if (! $g->facturapi_id) throw new TimbradoError('Esta factura global no se generó en Nerby.');
        $estado = Timbrado::estadoCancelacion(Timbrado::cancelarId($g->facturapi_id, $motivo));
        if ($estado === 'cancelada') {
            $g->update(['cancelacion' => 'cancelada', 'cancelada_at' => now()]);
            Venta::where('global_id', $g->id)->update(['global_id' => null]);   // vuelven a estar disponibles
        } else {
            $g->update(['cancelacion' => $estado === 'rechazada' ? null : 'pendiente']);
        }
        return $estado;
    }
}
