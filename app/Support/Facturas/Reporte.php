<?php

namespace App\Support\Facturas;

use App\Models\FacturaGlobal;
use App\Models\PresupuestoFactura;
use App\Models\Venta;
use App\Models\VentaFactura;
use App\Support\Xlsx;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Reporte de facturación del mes: todo CFDI con folio fiscal (timbrado en Nerby o subido),
 * facturado, notas de crédito, complementos, globales, canceladas, IVA, por cliente y lo que falta cobrar en PPD.
 */
class Reporte
{
    public const TIPOS = ['factura' => 'Factura', 'egreso' => 'Nota de crédito', 'complemento' => 'Complemento de pago', 'global' => 'Factura global'];

    public static function rango(string $mes): array
    {
        $tz = config('vandu.zona_horaria');
        $ini = Carbon::createFromFormat('!Y-m', $mes, $tz)->startOfMonth();
        return [$ini->copy()->utc(), $ini->copy()->endOfMonth()->utc(), $ini];
    }

    /** Un renglón por CFDI */
    public static function cfdis(string $mes): Collection
    {
        [$a, $b] = self::rango($mes);
        $filas = collect();
        $venta = VentaFactura::with('venta.cliente', 'venta.razon')->whereNotNull('uuid')->whereBetween('created_at', [$a, $b])->get()->unique('uuid');
        foreach ($venta as $f) {
            $v = $f->venta;
            $tipo = in_array($f->tipo, ['egreso', 'complemento'], true) ? $f->tipo : 'factura';
            $fis = $v ? (new Documento($v))->fiscales() : [];
            $total = $tipo === 'factura' ? (float) ($f->total_cfdi ?? $v?->total) : (float) $f->monto;
            $tasa = $v?->con_iva ? $v->iva_porcentaje / 100 : 0;
            $iva = $tipo === 'factura' ? (float) ($v?->iva ?? 0) : ($tasa ? round($total - $total / (1 + $tasa), 2) : 0);
            $filas->push(self::fila($f->created_at, $tipo, $f->serie ? "{$f->serie}-{$f->folio}" : ($v?->folio ?? ''), $fis['razon_social'] ?? $v?->quien, $fis['rfc'] ?? $f->rfc_receptor,
                $total, $iva, $f->cancelada_at !== null, $tipo === 'factura' ? ($v?->metodo_pago ?? '') : '', $f->uuid, $f, $v));
        }
        $cot = PresupuestoFactura::with('presupuesto.cliente')->whereNotNull('uuid')->whereIn('tipo', ['factura', 'cancelada'])->whereBetween('created_at', [$a, $b])->get()->unique('uuid');
        foreach ($cot as $f) {
            $p = $f->presupuesto;
            $filas->push(self::fila($f->created_at, 'factura', $f->serie ? "{$f->serie}-{$f->folio}" : ($p?->folio ?? ''), $p?->cliente?->razon_social ?: ($p?->cliente_empresa ?: $p?->cliente_nombre),
                $p?->cliente?->rfc ?: $f->rfc_receptor, (float) ($f->total_cfdi ?? $p?->total), round((float) $p?->total - (float) $p?->subtotal, 2), $f->cancelada_at !== null, '', $f->uuid, $f, null));
        }
        foreach (FacturaGlobal::whereNotNull('uuid')->whereBetween('created_at', [$a, $b])->get() as $g) {
            $filas->push(self::fila($g->created_at, 'global', $g->folio_texto, 'PUBLICO EN GENERAL', 'XAXX010101000', $g->total, $g->iva, $g->cancelada_at !== null, 'PUE', $g->uuid, $g, null));
        }
        return $filas->sortBy('fecha')->values();
    }

    private static function fila($fecha, string $tipo, string $folio, ?string $cliente, ?string $rfc, float $total, float $iva, bool $cancelada, string $metodo, ?string $uuid, $origen, ?Venta $v): array
    {
        return ['fecha' => $fecha, 'tipo' => $tipo, 'folio' => $folio, 'cliente' => $cliente ?: '—', 'rfc' => mb_strtoupper((string) $rfc), 'total' => round($total, 2),
            'iva' => round($iva, 2), 'cancelada' => $cancelada, 'metodo' => $metodo, 'uuid' => $uuid, 'origen' => $origen, 'venta' => $v];
    }

    public static function datos(string $mes): array
    {
        $f = self::cfdis($mes);
        $vig = $f->where('cancelada', false);
        $ingresos = $vig->whereIn('tipo', ['factura', 'global']);
        $notas = $vig->where('tipo', 'egreso');
        return [
            'filas' => $f,
            'facturado' => round($ingresos->sum('total'), 2),
            'n_facturas' => $ingresos->count(),
            'notas' => round($notas->sum('total'), 2),
            'neto' => round($ingresos->sum('total') - $notas->sum('total'), 2),
            'iva' => round($ingresos->sum('iva') - $notas->sum('iva'), 2),
            'globales' => round($vig->where('tipo', 'global')->sum('total'), 2),
            'complementos' => $vig->where('tipo', 'complemento')->count(),
            'cobrado_ppd' => round($vig->where('tipo', 'complemento')->sum('total'), 2),
            'canceladas' => $f->where('cancelada', true)->count(),
            'por_cliente' => $ingresos->where('tipo', 'factura')->groupBy(fn ($x) => $x['rfc'] ?: $x['cliente'])
                ->map(fn ($g) => ['cliente' => $g->first()['cliente'], 'rfc' => $g->first()['rfc'], 'n' => $g->count(), 'total' => round($g->sum('total'), 2)])
                ->sortByDesc('total')->values(),
            'por_cobrar' => self::porCobrarPpd(),
        ];
    }

    /** Facturas PPD vigentes con saldo: lo que falta cobrar y a quién mandarle su complemento */
    public static function porCobrarPpd(): Collection
    {
        return Venta::with('cliente')->vigentes()->where('metodo_pago', 'PPD')
            ->whereHas('facturas', fn ($q) => $q->where('tipo', 'factura')->whereNotNull('uuid'))
            ->get()->filter(fn ($v) => $v->saldo > 0.009)->sortBy('fecha')->values();
    }

    public static function excel(string $mes): string
    {
        $d = self::datos($mes);
        $filas = $d['filas']->map(fn ($x) => [$x['fecha']->setTimezone(config('vandu.zona_horaria'))->format('Y-m-d'), self::TIPOS[$x['tipo']] ?? $x['tipo'], $x['folio'], $x['cliente'], $x['rfc'],
            $x['metodo'], $x['total'] - $x['iva'], $x['iva'], $x['total'], $x['cancelada'] ? 'Cancelada' : 'Vigente', $x['uuid']])->all();
        return Xlsx::crear([
            'Resumen' => ['columnas' => [], 'filas' => [
                ['Facturación de ' . self::rango($mes)[2]->locale('es')->isoFormat('MMMM YYYY'), ''],
                ['Facturado (facturas y globales vigentes)', $d['facturado']], ['Notas de crédito', $d['notas']], ['Neto', $d['neto']],
                ['IVA trasladado (neto)', $d['iva']], ['Complementos de pago', $d['complementos']], ['Cobrado con complemento', $d['cobrado_ppd']], ['Canceladas', $d['canceladas']],
            ], 'anchos' => [42, 18]],
            'CFDI' => ['columnas' => ['Fecha', 'Tipo', 'Serie y folio', 'Cliente', 'RFC', 'Método', 'Subtotal', 'IVA', 'Total', 'Estado', 'Folio fiscal'], 'filas' => $filas,
                'anchos' => [11, 20, 12, 32, 15, 8, 13, 12, 13, 11, 38]],
            'Por cliente' => ['columnas' => ['Cliente', 'RFC', 'Facturas', 'Total'], 'filas' => $d['por_cliente']->map(fn ($c) => [$c['cliente'], $c['rfc'], $c['n'], $c['total']])->all(), 'anchos' => [36, 15, 10, 14]],
        ]);
    }

    /** ZIP con los XML (y PDF) del mes para el contador */
    public static function zip(string $mes): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cfdi');
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::OVERWRITE);
        $carpetas = ['factura' => 'Facturas', 'egreso' => 'Notas de crédito', 'complemento' => 'Complementos de pago', 'global' => 'Globales'];
        foreach (self::cfdis($mes) as $x) {
            $dir = ($x['cancelada'] ? 'Canceladas/' : '') . $carpetas[$x['tipo']] . '/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $x['folio'] ?: $x['uuid']);
            $o = $x['origen'];
            if ($o instanceof FacturaGlobal) {
                foreach (['xml', 'pdf'] as $ext) if ($o->$ext && Storage::disk('local')->exists($o->$ext)) $z->addFromString("$dir.$ext", Storage::disk('local')->get($o->$ext));
                continue;
            }
            $hermanos = $o->facturapi_id
                ? $o::where('facturapi_id', $o->facturapi_id)->get()
                : $o::where($o instanceof VentaFactura ? 'venta_id' : 'presupuesto_id', $o->{$o instanceof VentaFactura ? 'venta_id' : 'presupuesto_id'})->where('tipo', $o->tipo)->get();
            foreach ($hermanos as $h) {
                if (in_array($h->ext, ['xml', 'pdf'], true) && Storage::disk('local')->exists($h->ruta)) $z->addFromString("$dir.{$h->ext}", Storage::disk('local')->get($h->ruta));
            }
        }
        if ($z->numFiles === 0) $z->addFromString('LEEME.txt', 'No hay facturas con folio fiscal en este mes.');
        $z->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }
}
