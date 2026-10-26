<?php

namespace App\Support\Finanzas;

use App\Models\Cuenta;
use App\Models\CuentaDinero;
use App\Models\Gasto;
use App\Models\OrdenCompra;
use App\Models\PresupuestoFactura;
use App\Models\ProyectoPago;
use App\Models\VentaPago;
use App\Support\Xlsx;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Todo lo que el contador pide cada mes, en un ZIP:
 *  - Resumen <mes>.xlsx: resumen, ingresos (cada cobro con su IVA), gastos, compras a proveedores y facturas emitidas.
 *  - Gastos/Facturas (XML), Gastos/Tickets (fotos), Compras/<orden> (XML y PDF del proveedor), Facturas emitidas/<folio>.
 *  - LEEME.txt con el resumen y lo que falta (gastos sin factura, archivos que no se pudieron incluir).
 */
class PaqueteContador
{
    /** Meses que se pueden pedir: los últimos 12, el más reciente primero ('2026-10' => 'Octubre 2026') */
    public static function meses(): array
    {
        $hoy = now(config('vandu.zona_horaria'))->startOfMonth();
        return collect(range(0, 11))->mapWithKeys(fn ($i) => [$hoy->copy()->subMonthsNoOverflow($i)->format('Y-m') => ucfirst($hoy->copy()->subMonthsNoOverflow($i)->locale('es')->isoFormat('MMMM YYYY'))])->all();
    }

    /** El mes que normalmente se declara: el pasado (o el actual en los últimos días del mes) */
    public static function mesSugerido(): string
    {
        $hoy = now(config('vandu.zona_horaria'));
        return ($hoy->day >= 25 ? $hoy : $hoy->copy()->subMonthNoOverflow())->format('Y-m');
    }

    public static function nombre(Cuenta $c, string $mes): string
    {
        return Str::slug($c->nombre) . '-contabilidad-' . $mes . '.zip';
    }

    /** Arma el ZIP en un archivo temporal y regresa su ruta */
    public static function armar(Cuenta $c, string $mes): string
    {
        $ini = Carbon::createFromFormat('Y-m-d', $mes . '-01', config('vandu.zona_horaria'))->startOfMonth();
        $desde = $ini->toDateString();
        $hasta = $ini->copy()->endOfMonth()->toDateString();
        $titulo = ucfirst($ini->locale('es')->isoFormat('MMMM YYYY'));
        $cuentas = CuentaDinero::pluck('nombre', 'id');
        $formas = config('vandu.metodos_pago') + Gasto::FORMAS;

        $tmp = tempnam(sys_get_temp_dir(), 'paq');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $faltan = [];
        $usados = [];
        $poner = function (string $ruta, ?string $bytes) use ($zip, &$usados): ?string {
            if ($bytes === null) return null;
            $ruta = preg_replace('/[\x00-\x1F<>:"|?*\\\\]/u', '', $ruta);
            $base = $ruta; $n = 2;
            while (isset($usados[$ruta])) { $ruta = preg_replace('/(\.[^.\/]+)?$/', " ($n)$1", $base, 1); $n++; }
            $usados[$ruta] = true;
            $zip->addFromString($ruta, $bytes);
            return $ruta;
        };
        $corto = fn (?string $s, int $n = 40) => trim(Str::limit(preg_replace('/[\/\\\\]+/', '-', (string) $s), $n, ''));

        // Ingresos: cada cobro, con su parte de IVA
        $ingresos = [];
        foreach (ProyectoPago::with('proyecto.presupuesto.conceptos', 'proyecto.cliente')->whereNotNull('pagado_el')->whereBetween('pagado_el', [$desde, $hasta])->orderBy('pagado_el')->get() as $p) {
            $pre = $p->proyecto?->presupuesto;
            $iva = $pre && $pre->total > 0 ? round($p->monto * $pre->iva / $pre->total, 2) : 0.0;
            $ingresos[] = [$p->pagado_el->toDateString(), 'Cobro de proyecto', $pre?->folio, $p->proyecto?->cliente?->empresa ?: $p->proyecto?->cliente?->nombre, $p->proyecto?->cliente?->rfc,
                $p->concepto, $formas[$p->metodo] ?? $p->metodo, $cuentas[$p->cuenta_dinero_id] ?? '', round($p->monto - $iva, 2), $iva, round((float) $p->monto, 2)];
        }
        if ($c->tiene('stock')) {
            foreach (VentaPago::with('venta.cliente')->whereHas('venta', fn ($q) => $q->vigentes())->whereBetween('fecha', [$desde, $hasta])->orderBy('fecha')->get() as $p) {
                $v = $p->venta;
                $iva = $v->total > 0 ? round($p->monto * $v->iva / $v->total, 2) : 0.0;
                $ingresos[] = [$p->fecha->toDateString(), 'Venta', $v->folio, $v->quien, $v->cliente?->rfc, $v->credito ? 'Abono' : 'Pago de contado',
                    Gasto::FORMAS[$p->forma] ?? $p->forma, $cuentas[$p->cuenta_dinero_id] ?? '', round($p->monto - $iva, 2), $iva, round((float) $p->monto, 2)];
            }
        }
        usort($ingresos, fn ($a, $b) => strcmp($a[0], $b[0]));

        // Gastos con su comprobante
        $gastos = [];
        $sinFactura = [];
        if ($c->tiene('gastos')) {
            foreach (Gasto::whereBetween('fecha', [$desde, $hasta])->orderBy('fecha')->get() as $g) {
                $nombre = $g->fecha->toDateString() . ' ' . $corto($g->proveedor ?: $g->concepto);
                $archivo = null;
                if ($g->xml) {
                    $archivo = $poner('Gastos/Facturas/' . $nombre . ' ' . substr((string) $g->uuid, 0, 8) . '.xml', Storage::exists($g->xml) ? Storage::get($g->xml) : null);
                    if (! $archivo) $faltan[] = "Gasto \"{$g->concepto}\" ({$g->fecha->toDateString()}): no se encontró el XML.";
                }
                if ($g->foto) {
                    $foto = $poner('Gastos/Tickets/' . $nombre . '.jpg', Storage::exists($g->foto) ? Storage::get($g->foto) : null);
                    $archivo = $archivo ? $archivo . ' · ' . $foto : $foto;
                }
                if (! $g->xml) $sinFactura[] = "{$g->fecha->toDateString()}  {$g->concepto}" . ($g->proveedor ? " ({$g->proveedor})" : '') . '  $' . number_format($g->total, 2);
                $gastos[] = [$g->fecha->toDateString(), $g->concepto, $g->proveedor, $g->rfc, $g->categoria_texto, round($g->subtotal, 2), round($g->iva, 2), round($g->total, 2),
                    Gasto::FORMAS[$g->forma_pago] ?? $g->forma_pago, $cuentas[$g->cuenta_dinero_id] ?? '', $g->xml ? 'Factura (XML)' : ($g->foto ? 'Ticket (foto)' : 'Sin comprobante'), $g->uuid, $archivo];
            }
        }

        // Compras a proveedores: las que se pagaron en el mes o se hicieron en el mes
        $compras = [];
        $ordenes = OrdenCompra::with('conceptos', 'proveedor', 'archivos')->whereNotIn('estado', ['borrador', 'cancelada'])
            ->where(fn ($q) => $q->whereBetween('fecha', [$desde, $hasta])->orWhereBetween('pagada_el', [$desde, $hasta]))->orderBy('fecha')->get();
        foreach ($ordenes as $o) {
            $carpeta = 'Compras/' . $o->folio . ' ' . $corto($o->proveedor?->empresa, 30) . '/';
            $incluidos = [];
            foreach ($o->archivos->where('tipo', 'factura') as $a) {
                $bytes = null;
                if ($a->origen === 'dropbox') {
                    try { $bytes = Http::timeout(30)->get(\App\Support\Dropbox\Dropbox::cliente()->enlaceTemporal($a->dropbox_id ?: $a->ruta))->throw()->body(); } catch (\Throwable $e) { $bytes = null; }
                } elseif (Storage::disk('local')->exists($a->ruta)) {
                    $bytes = Storage::disk('local')->get($a->ruta);
                }
                $r = $poner($carpeta . $corto($a->nombre, 80), $bytes);
                if ($r) $incluidos[] = basename($r); else $faltan[] = "Orden {$o->folio}: no se pudo incluir \"{$a->nombre}\"" . ($a->origen === 'dropbox' ? ' (está en Dropbox)' : '') . '.';
            }
            $sub = $o->subtotal_final; $tot = $o->total_final;
            $compras[] = [$o->folio, $o->fecha?->toDateString(), $o->pagada_el?->toDateString(), $o->proveedor?->empresa, $o->proveedor?->rfc, $o->estado_texto,
                round($sub, 2), round($tot - $sub, 2), round($tot, 2), $o->comprobada ? 'Sí' : 'No', $o->archivos->whereNotNull('uuid')->pluck('uuid')->join(', '), implode(', ', $incluidos)];
        }

        // Facturas que el negocio emitió (las que se subieron a las cotizaciones)
        $emitidas = [];
        foreach (PresupuestoFactura::with('presupuesto')->whereBetween('created_at', [$ini->copy()->startOfDay()->utc(), $ini->copy()->endOfMonth()->utc()])->orderBy('created_at')->get() as $fa) {
            $pre = $fa->presupuesto;
            $r = $poner('Facturas emitidas/' . ($pre?->folio ?: 'sin-folio') . '/' . $corto($fa->nombre, 80), $fa->contenido());
            if (! $r) $faltan[] = 'Factura emitida "' . $fa->nombre . '": no se encontró el archivo.';
            $emitidas[] = [$fa->created_at->setTimezone(config('vandu.zona_horaria'))->toDateString(), $pre?->folio, $pre?->cliente_empresa ?: $pre?->cliente_nombre, $fa->etiqueta, $r ? basename($r) : ''];
        }

        // Números del mes (los mismos de Finanzas > Contabilidad)
        $cont = Contabilidad::datos($desde, $hasta);
        $re = $cont['resultados']; $iv = $cont['iva']; $cp = $cont['comprobantes'];
        $resumen = [
            ['Negocio', $c->nombre], ['Mes', $titulo], ['Generado', now(config('vandu.zona_horaria'))->format('Y-m-d H:i')], ['', ''],
            ['ESTADO DE RESULTADOS (antes de IVA)', ''], ['Ventas', $re['ventas']], ['Costo de lo vendido', $re['costo']], ['Utilidad bruta', $re['bruta']],
            ['Gastos de operación', $re['gastos']], ['Utilidad de operación', $re['utilidad']], ['', ''],
            ['IVA (sobre lo cobrado y pagado)', ''], ['IVA cobrado', $iv['trasladado']], ['IVA de gastos con factura', $iv['gastos']], ['IVA de compras con factura', $iv['ordenes']],
            [$iv['saldo'] >= 0 ? 'IVA estimado a pagar' : 'IVA estimado a favor', abs($iv['saldo'])], ['IVA de tickets sin factura (no acreditable)', $iv['perdido']], ['', ''],
            ['COMPROBANTES', ''], ['Gastos con factura', $cp['con'] ?? 0], ['Gastos sin factura', $cp['sin'] ?? 0], ['', ''],
            ['Estimación para planear; no incluye retenciones, tasa 0, exentos ni ISR.', ''],
        ];
        $xlsx = Xlsx::crear([
            'Resumen' => ['columnas' => [], 'filas' => $resumen, 'anchos' => [44, 18], 'negritas' => [4, 9, 11, 15, 18]],
            'Ingresos' => ['columnas' => ['Fecha', 'Tipo', 'Folio', 'Cliente', 'RFC', 'Concepto', 'Forma de pago', 'Cuenta', 'Subtotal', 'IVA', 'Total'],
                'filas' => $ingresos, 'anchos' => [11, 16, 10, 30, 15, 26, 15, 14, 13, 12, 13]],
            'Gastos' => ['columnas' => ['Fecha', 'Concepto', 'Proveedor', 'RFC', 'Categoría', 'Subtotal', 'IVA', 'Total', 'Forma de pago', 'Cuenta', 'Comprobante', 'UUID', 'Archivo en el ZIP'],
                'filas' => $gastos, 'anchos' => [11, 28, 26, 15, 20, 12, 11, 12, 14, 13, 15, 38, 50]],
            'Compras' => ['columnas' => ['Folio', 'Fecha', 'Pagada el', 'Proveedor', 'RFC', 'Estado', 'Subtotal', 'IVA', 'Total', 'Con factura', 'UUID', 'Archivos'],
                'filas' => $compras, 'anchos' => [10, 11, 11, 28, 15, 12, 12, 11, 12, 11, 38, 40]],
            'Facturas emitidas' => ['columnas' => ['Fecha', 'Cotización', 'Cliente', 'Documento', 'Archivo'], 'filas' => $emitidas, 'anchos' => [11, 12, 30, 26, 40]],
        ]);
        $zip->addFromString('Resumen ' . $titulo . '.xlsx', $xlsx);

        $leeme = "{$c->nombre} — contabilidad de {$titulo}\n" . str_repeat('=', 50) . "\n\n"
            . "Contenido\n- Resumen {$titulo}.xlsx: resumen, ingresos, gastos, compras y facturas emitidas.\n"
            . '- Gastos/Facturas: ' . collect($gastos)->where(10, 'Factura (XML)')->count() . " XML.  Gastos/Tickets: fotos de tickets.\n"
            . '- Compras: ' . count($compras) . " órdenes de compra con las facturas del proveedor.\n"
            . '- Facturas emitidas: ' . count($emitidas) . " archivos.\n\n"
            . 'Ingresos cobrados: $' . number_format(array_sum(array_column($ingresos, 10)), 2) . "\n"
            . 'Gastos: $' . number_format(array_sum(array_column($gastos, 7)), 2) . "\n"
            . ($iv['saldo'] >= 0 ? 'IVA estimado a pagar: $' : 'IVA estimado a favor: $') . number_format(abs($iv['saldo']), 2) . "\n\n"
            . ($sinFactura ? "Gastos SIN factura (" . count($sinFactura) . "):\n- " . implode("\n- ", $sinFactura) . "\n\n" : "Todos los gastos tienen factura.\n\n")
            . ($faltan ? "No se pudieron incluir:\n- " . implode("\n- ", $faltan) . "\n\n" : '')
            . "Generado desde el panel el " . now(config('vandu.zona_horaria'))->format('Y-m-d H:i') . ". Los montos del resumen son una estimación; la declaración la confirma el contador.\n";
        $zip->addFromString('LEEME.txt', $leeme);
        $zip->close();
        return $tmp;
    }
}
