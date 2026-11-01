<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Facturas\Archivos;
use App\Support\Facturas\Bandeja;
use App\Support\Facturas\Documento;
use App\Support\Facturas\Enviar;
use App\Support\Facturas\Timbrado;
use Illuminate\Http\Request;

/** Bandeja de facturas: por facturar, por enviar y enviadas, de cotizaciones y de ventas directas */
class FacturasController extends Controller
{
    public const VISTAS = ['por_facturar' => 'Por facturar', 'por_enviar' => 'Por enviar', 'enviadas' => 'Enviadas'];

    public function index(Request $request)
    {
        $ver = array_key_exists($request->query('ver'), self::VISTAS) ? $request->query('ver') : null;
        $porFacturar = Bandeja::porFacturar();
        $porEnviar = Bandeja::porEnviar();
        // Abre donde hay algo que hacer
        $ver ??= $porEnviar->isNotEmpty() ? 'por_enviar' : 'por_facturar';
        return view('admin.facturas.index', [
            'ver' => $ver,
            'docs' => match ($ver) { 'por_facturar' => $porFacturar, 'por_enviar' => $porEnviar, default => Bandeja::enviadas() },
            'n' => ['por_facturar' => $porFacturar->count(), 'por_enviar' => $porEnviar->count()],
            'timbrar' => Timbrado::activo(),
            'series' => Timbrado::activo() ? \App\Support\Facturas\Series::de('I') : collect(),
            'timbres' => \App\Support\Facturas\Timbres::aplica() ? \App\Support\Facturas\Timbres::resumen(\App\Support\Cuentas::actual()) : null,
            'solo' => Bandeja::soloFacturacion(),
        ]);
    }

    private function doc(string $tipo, int $id): Documento
    {
        abort_unless(in_array($tipo, ['presupuesto', 'venta'], true), 404);
        return Documento::de($tipo, $id);
    }

    public function subir(Request $request, string $tipo, int $id)
    {
        $doc = $this->doc($tipo, $id);
        $request->validate(Archivos::reglas(), Archivos::MENSAJES);
        Archivos::subir($doc, $request->file('facturas'));
        $doc = $this->doc($tipo, $id);
        $avisos = $doc->diferencias();
        return back()->with('ok', "Factura de {$doc->folio()} guardada." . ($avisos ? ' Revisa: ' . implode('. ', $avisos) . '.' : ' Ya la puedes enviar.'));
    }

    public function enviar(Request $request, string $tipo, int $id)
    {
        $d = $request->validate(['para' => 'nullable|email:filter|max:190']);
        [$ok, $msg] = Enviar::correo($this->doc($tipo, $id), $d['para'] ?? null);
        return back()->with($ok ? 'ok' : 'error', $msg);
    }

    /** Envía varias de un jalón, cada una a su cliente */
    public function enviarVarias(Request $request)
    {
        $d = $request->validate(['docs' => 'required|array|min:1|max:50', 'docs.*' => ['string', 'regex:/^(presupuesto|venta)-\d+$/']], ['docs.required' => 'Elige al menos una factura.']);
        $bien = 0; $mal = [];
        foreach ($d['docs'] as $clave) {
            [$tipo, $id] = explode('-', $clave);
            [$ok, $msg] = Enviar::correo($this->doc($tipo, (int) $id));
            $ok ? $bien++ : $mal[] = $msg;
        }
        $txt = $bien ? ($bien === 1 ? '1 factura enviada.' : "$bien facturas enviadas.") : '';
        return back()->with($mal ? 'error' : 'ok', trim($txt . ($mal ? ' No se enviaron: ' . implode(' ', $mal) : '')));
    }

    public function whatsapp(string $tipo, int $id)
    {
        $url = Enviar::whatsapp($this->doc($tipo, $id));
        abort_unless($url, 404, 'No tiene WhatsApp o factura.');
        return redirect()->away($url);
    }

    /** Genera (timbra) la factura en Nerby y guarda su PDF y XML */
    public function timbrar(Request $request, string $tipo, int $id)
    {
        $doc = $this->doc($tipo, $id);
        $d = $request->validate([
            'forma_pago' => ['required', \Illuminate\Validation\Rule::in(array_keys(Timbrado::FORMAS_SAT))],
            'metodo_pago' => 'required|in:PUE,PPD',
            'uso_cfdi' => 'nullable|string|max:4',
            'enviar' => 'nullable|boolean',
            'serie_id' => 'nullable|integer',
        ]);
        try {
            Timbrado::timbrar($doc, $d['forma_pago'], $d['metodo_pago'], $d['uso_cfdi'] ?? null, isset($d['serie_id']) ? (int) $d['serie_id'] : null);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->with('error', "No se pudo generar la factura de {$doc->folio()}: " . $e->getMessage());
        }
        $doc = $this->doc($tipo, $id);
        if ($request->boolean('enviar')) {
            [$ok, $msg] = Enviar::correo($doc);
            return back()->with($ok ? 'ok' : 'error', "Factura de {$doc->folio()} generada. " . $msg);
        }
        return back()->with('ok', "Factura de {$doc->folio()} generada (folio fiscal {$doc->uuid()}). Ya la puedes enviar.");
    }

    /** Cancela ante el SAT la factura que se timbró en Nerby */
    public function cancelar(Request $request, string $tipo, int $id)
    {
        $d = $request->validate(['motivo' => ['required', \Illuminate\Validation\Rule::in(array_keys(Timbrado::MOTIVOS))]], ['motivo.required' => 'Elige el motivo de la cancelación.']);
        $doc = $this->doc($tipo, $id);
        try {
            $estado = Timbrado::cancelar($doc, $d['motivo']);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->with('error', "No se pudo cancelar la factura de {$doc->folio()}: " . $e->getMessage());
        }
        return back()->with('ok', $estado === 'cancelada'
            ? "Factura de {$doc->folio()} cancelada ante el SAT. La venta regresó a «Por facturar»."
            : "Pedimos la cancelación de la factura de {$doc->folio()}. El cliente tiene que aceptarla en el buzón del SAT (tiene 3 días hábiles); revísala después.");
    }

    public function revisar(string $tipo, int $id)
    {
        $doc = $this->doc($tipo, $id);
        try {
            $estado = Timbrado::revisarCancelacion($doc);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with($estado === 'rechazada' ? 'error' : 'ok', match ($estado) {
            'cancelada' => "La factura de {$doc->folio()} ya quedó cancelada.",
            'rechazada' => "El cliente rechazó la cancelación de la factura de {$doc->folio()}: sigue vigente.",
            default => "La cancelación de {$doc->folio()} sigue esperando que el cliente la acepte.",
        });
    }

    /* ---------------- Nueva factura directa ---------------- */

    public const FORMA_VENTA = ['01' => 'efectivo', '03' => 'transferencia', '04' => 'tarjeta', '28' => 'tarjeta', '02' => 'otro', '99' => 'otro'];

    public function crear(Request $request)
    {
        $c = \App\Support\Cuentas::actual();
        return view('admin.facturas.nueva', [
            'timbrar' => Timbrado::activo(),
            'series' => \App\Support\Facturas\Series::de('I'),
            'cliente' => $request->query('cliente') ? \App\Models\Cliente::with('razones')->find($request->query('cliente')) : null,
            'clientes' => \App\Models\Cliente::with('razones')->orderByRaw('COALESCE(NULLIF(empresa, \'\'), nombre)')->get(['id', 'nombre', 'empresa', 'rfc', 'razon_social', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'email', 'email_factura', 'dias_credito']),
            'conIva' => (bool) $c->ajuste('ventas.iva', true),
            'timbres' => \App\Support\Facturas\Timbres::aplica() ? \App\Support\Facturas\Timbres::resumen($c) : null,
        ]);
    }

    public function guardar(Request $request)
    {
        $d = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'razon_id' => 'nullable|integer',
            'serie_id' => 'nullable|integer',
            'uso_cfdi' => ['required', \Illuminate\Validation\Rule::in(array_keys(config('vandu.sat.usos_cfdi')))],
            'metodo_pago' => 'required|in:PUE,PPD',
            'forma_pago' => ['nullable', 'required_if:metodo_pago,PUE', \Illuminate\Validation\Rule::in(array_keys(Timbrado::FORMAS_SAT))],
            'con_iva' => 'nullable|boolean',
            'descuento' => 'nullable|numeric|min:0|max:99999999',
            'notas' => 'nullable|string|max:1000',
            'enviar' => 'nullable|boolean',
            'conceptos' => 'required|array|min:1|max:100',
            'conceptos.*.producto_id' => 'nullable|integer',
            'conceptos.*.descripcion' => 'required|string|max:1000',
            'conceptos.*.clave_sat' => ['nullable', 'regex:/^\d{8}$/'],
            'conceptos.*.unidad_sat' => ['nullable', 'regex:/^[A-Z0-9]{1,3}$/'],
            'conceptos.*.cantidad' => 'required|numeric|min:0.0001|max:9999999',
            'conceptos.*.precio' => 'required|numeric|min:0|max:999999999',
            'conceptos.*.guardar' => 'nullable|boolean',
            'conceptos.*.objeto_imp' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\Facturas\CatalogoSat::OBJETOS))],
        ], [
            'cliente_id.required' => 'Elige a quién le facturas.', 'conceptos.required' => 'Agrega al menos un concepto.',
            'conceptos.*.descripcion.required' => 'Escribe la descripción de cada concepto.', 'conceptos.*.clave_sat.regex' => 'La clave SAT tiene 8 números.',
            'conceptos.*.unidad_sat.regex' => 'La unidad SAT es una clave corta, por ejemplo H87 o E48.', 'forma_pago.required_if' => 'Elige cómo te pagó.',
        ]);
        if (! Timbrado::activo()) return back()->withInput()->with('error', 'Activa la facturación en Configuración › Facturación.');
        $cliente = \App\Models\Cliente::findOrFail($d['cliente_id']);
        $razon = ! empty($d['razon_id']) ? \App\Models\ClienteRazon::where('cliente_id', $cliente->id)->find($d['razon_id']) : null;
        $prueba = new \App\Models\Venta(['cliente_id' => $cliente->id]);
        $prueba->setRelation('cliente', $cliente)->setRelation('razon', $razon);
        if (! (new Documento($prueba))->fiscalesCompletos()) {
            return back()->withInput()->with('error', 'A ' . ($cliente->empresa ?: $cliente->nombre) . ' le faltan datos fiscales (RFC, razón social, régimen o C.P.). Complétalos en su ficha o sube su constancia.');
        }
        try {
            \App\Support\Facturas\Timbres::verificar();
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Productos nuevos al catálogo (sin inventario), si se pidió
        $productos = [];
        foreach (array_values($d['conceptos']) as $i => $x) {
            $pid = ! empty($x['producto_id']) && \App\Models\Producto::whereKey($x['producto_id'])->exists() ? (int) $x['producto_id'] : null;
            if (! $pid && ! empty($x['guardar'])) {
                $pid = \App\Models\Producto::create(['nombre' => \Illuminate\Support\Str::limit(trim($x['descripcion']), 150, ''), 'precio' => $x['precio'], 'controla_existencia' => false,
                    'clave_sat' => $x['clave_sat'] ?? null, 'unidad_sat' => $x['unidad_sat'] ?? null, 'objeto_imp' => $x['objeto_imp'] ?? '02'])->id;
            } elseif ($pid && (! empty($x['clave_sat']) || ! empty($x['unidad_sat']))) {
                // Lo que se escribió aquí se queda en el producto para la próxima
                $pr = \App\Models\Producto::find($pid);
                $pr->forceFill(array_filter(['clave_sat' => $pr->clave_sat ?: ($x['clave_sat'] ?? null), 'unidad_sat' => $pr->unidad_sat ?: ($x['unidad_sat'] ?? null)]))->save();
            }
            $productos[] = ['producto_id' => $pid, 'descripcion' => trim($x['descripcion']), 'cantidad' => $x['cantidad'], 'precio' => $x['precio']];
        }
        $ppd = $d['metodo_pago'] === 'PPD';
        $v = \App\Support\Ventas\Registrar::crear([
            'productos' => $productos, 'cliente_id' => $cliente->id, 'descuento' => $d['descuento'] ?? 0, 'con_iva' => $request->boolean('con_iva'),
            'pago' => $ppd ? 'credito' : 'contado', 'forma' => self::FORMA_VENTA[$d['forma_pago'] ?? '99'] ?? 'otro',
            'dias' => $cliente->dias_credito ?: 30, 'entrega' => 'ahora', 'notas' => $d['notas'] ?? null,
        ], $request->user());
        $v->forceFill(['razon_id' => $razon?->id, 'uso_cfdi' => $d['uso_cfdi']])->save();
        foreach ($v->conceptos()->orderBy('orden')->get() as $i => $con) {
            $x = array_values($d['conceptos'])[$i] ?? [];
            $con->update(['clave_sat' => $x['clave_sat'] ?? null, 'unidad_sat' => $x['unidad_sat'] ?? null, 'objeto_imp' => $x['objeto_imp'] ?? null]);
        }
        $v->load('conceptos.producto', 'cliente', 'razon', 'facturas');
        try {
            Timbrado::timbrar(new Documento($v), $d['forma_pago'] ?? '99', $d['metodo_pago'], $d['uso_cfdi'], isset($d['serie_id']) ? (int) $d['serie_id'] : null);
        } catch (\App\Support\Facturas\TimbradoIncompleto $e) {
            return redirect()->route('admin.facturas.venta', $v)->with('error', $e->getMessage());
        } catch (\App\Support\Facturas\TimbradoError $e) {
            // No se timbró: la venta no se queda
            \App\Support\Ventas\Registrar::cancelar($v);
            $v->pagos()->delete();
            $v->conceptos()->delete();
            $v->delete();
            return back()->withInput()->with('error', 'No se pudo timbrar: ' . $e->getMessage());
        }
        $doc = Documento::de('venta', $v->id);
        $msg = "Factura {$doc->archivos()->first()?->serie}-{$doc->archivos()->first()?->folio} timbrada.";
        if ($request->boolean('enviar') && $doc->correo()) {
            [$ok, $m] = Enviar::correo($doc);
            $msg .= ' ' . $m;
        }
        return redirect()->route('admin.facturas.venta', $v)->with('ok', $msg);
    }

    /* ---------------- Detalle: pagos, complementos y notas de crédito ---------------- */

    public function venta(\App\Models\Venta $venta)
    {
        $venta->load(['conceptos.producto', 'pagos.usuario:id,name', 'cliente', 'razon', 'facturas']);
        $doc = new Documento($venta);
        $porId = fn ($tipo) => $venta->facturas->where('tipo', $tipo)->groupBy(fn ($f) => $f->facturapi_id ?: 'f' . $f->id)->values();
        return view('admin.facturas.venta', [
            'v' => $venta, 'doc' => $doc, 'complementos' => $porId('complemento'), 'notas' => $porId('egreso'),
            'canceladas' => $venta->facturas->where('tipo', 'cancelada')->groupBy(fn ($f) => $f->facturapi_id ?: 'f' . $f->id)->values(),
            'timbrar' => Timbrado::activo(),
        ]);
    }

    public function archivo(\App\Models\Venta $venta, int $factura)
    {
        return $venta->facturas()->findOrFail($factura)->respuesta();
    }

    /** Registra un pago y, si la factura es PPD, timbra su complemento */
    public function pago(Request $request, \App\Models\Venta $venta)
    {
        $d = $request->validate([
            'monto' => 'required|numeric|min:0.01|max:' . max(0.01, $venta->saldo),
            'forma' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Venta::FORMAS))],
            'fecha' => 'required|date|before_or_equal:' . now(config('vandu.zona_horaria'))->toDateString(),
            'complemento' => 'nullable|boolean',
        ], ['monto.max' => 'El pago es mayor que lo que debe ($' . number_format($venta->saldo, 2) . ').']);
        \App\Support\Ventas\Registrar::abonar($venta, (float) $d['monto'], $d['forma'], $request->user(), $d['fecha']);
        $msg = 'Pago de $' . number_format($d['monto'], 2) . ' registrado.';
        if ($request->boolean('complemento') && $venta->metodo_pago === 'PPD') {
            try {
                Timbrado::complemento($venta->fresh(), $venta->pagos()->reorder()->latest('id')->first());
                $msg .= ' Complemento de pago timbrado.';
            } catch (\App\Support\Facturas\TimbradoError $e) {
                return back()->with('error', $msg . ' El complemento no se pudo timbrar: ' . $e->getMessage() . ' Puedes intentarlo otra vez desde el pago.');
            }
        }
        return back()->with('ok', $msg);
    }

    public function complemento(\App\Models\Venta $venta, \App\Models\VentaPago $pago)
    {
        abort_unless($pago->venta_id === $venta->id, 404);
        try {
            Timbrado::complemento($venta, $pago);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->with('error', 'No se pudo timbrar el complemento: ' . $e->getMessage());
        }
        return back()->with('ok', 'Complemento de pago timbrado. Ya lo puedes enviar.');
    }

    public function notaCredito(Request $request, \App\Models\Venta $venta)
    {
        $d = $request->validate([
            'monto' => 'required|numeric|min:0.01|max:99999999',
            'motivo' => 'required|string|max:160',
            'forma_pago' => ['required', \Illuminate\Validation\Rule::in(array_keys(Timbrado::FORMAS_SAT))],
        ], ['motivo.required' => 'Escribe el motivo (por ejemplo: Descuento por pronto pago o Devolución de 2 piezas).']);
        try {
            Timbrado::notaCredito($venta, (float) $d['monto'], $d['motivo'], $d['forma_pago']);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->withInput()->with('error', 'No se pudo timbrar la nota de crédito: ' . $e->getMessage());
        }
        return back()->with('ok', 'Nota de crédito de $' . number_format($d['monto'], 2) . ' timbrada.');
    }

    /** Cancela un complemento o una nota de crédito */
    public function cancelarCfdi(Request $request, \App\Models\Venta $venta, int $factura)
    {
        $d = $request->validate(['motivo' => ['required', \Illuminate\Validation\Rule::in(array_keys(Timbrado::MOTIVOS))]]);
        $f = $venta->facturas()->whereIn('tipo', ['complemento', 'egreso'])->findOrFail($factura);
        try {
            $estado = Timbrado::cancelarCfdi($venta->facturas()->where('facturapi_id', $f->facturapi_id)->get(), $d['motivo']);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->with('error', 'No se pudo cancelar: ' . $e->getMessage());
        }
        return back()->with('ok', $estado === 'cancelada' ? 'Cancelado ante el SAT.' : 'Pedimos la cancelación; el cliente tiene que aceptarla.');
    }

    /** Envía por correo un complemento o una nota de crédito */
    public function enviarCfdi(\App\Models\Venta $venta, int $factura)
    {
        $f = $venta->facturas()->whereIn('tipo', ['complemento', 'egreso'])->findOrFail($factura);
        [$ok, $msg] = Enviar::cfdi(new Documento($venta), $venta->facturas()->where('facturapi_id', $f->facturapi_id)->get(), $f->tipo === 'complemento' ? 'Complemento de pago' : 'Nota de crédito');
        return back()->with($ok ? 'ok' : 'error', $msg);
    }

    /* ---------------- Factura global ---------------- */

    public function global(Request $request)
    {
        $tz = config('vandu.zona_horaria');
        $desde = $request->query('desde', now($tz)->subMonthNoOverflow()->startOfMonth()->toDateString());
        $hasta = $request->query('hasta', now($tz)->subMonthNoOverflow()->endOfMonth()->toDateString());
        $ventas = \App\Support\Facturas\Globales::candidatas($desde, $hasta);
        return view('admin.facturas.global', [
            'desde' => $desde, 'hasta' => $hasta, 'ventas' => $ventas, 'timbrar' => Timbrado::activo(),
            'globales' => \App\Models\FacturaGlobal::latest('id')->take(24)->get(),
            'series' => \App\Support\Facturas\Series::de('G'),
            'cp' => Timbrado::ajustes()['emisor']['cp'] ?? null,
        ]);
    }

    public function emitirGlobal(Request $request)
    {
        $d = $request->validate([
            'desde' => 'required|date', 'hasta' => 'required|date|after_or_equal:desde|before_or_equal:' . now(config('vandu.zona_horaria'))->endOfMonth()->toDateString(),
            'periodicidad' => ['required', \Illuminate\Validation\Rule::in(array_keys(\App\Models\FacturaGlobal::PERIODICIDADES))],
            'cp' => 'nullable|digits:5', 'serie_id' => 'nullable|integer',
        ]);
        try {
            $g = \App\Support\Facturas\Globales::emitir($d['desde'], $d['hasta'], $d['periodicidad'], $d['cp'] ?? null, isset($d['serie_id']) ? (int) $d['serie_id'] : null);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->withInput()->with('error', 'No se pudo timbrar la factura global: ' . $e->getMessage());
        }
        return redirect()->route('admin.facturas.global', ['desde' => $d['desde'], 'hasta' => $d['hasta']])
            ->with('ok', "Factura global {$g->folio_texto} timbrada: {$g->ventas} ventas por $" . number_format($g->total, 2) . '.');
    }

    public function archivoGlobal(\App\Models\FacturaGlobal $global, string $ext)
    {
        abort_unless(in_array($ext, ['pdf', 'xml'], true) && $global->$ext && \Illuminate\Support\Facades\Storage::disk('local')->exists($global->$ext), 404);
        return \Illuminate\Support\Facades\Storage::disk('local')->download($global->$ext, "Factura global {$global->folio_texto}.$ext");
    }

    public function cancelarGlobal(Request $request, \App\Models\FacturaGlobal $global)
    {
        $d = $request->validate(['motivo' => ['required', \Illuminate\Validation\Rule::in(array_keys(Timbrado::MOTIVOS))]]);
        try {
            $estado = \App\Support\Facturas\Globales::cancelar($global, $d['motivo']);
        } catch (\App\Support\Facturas\TimbradoError $e) {
            return back()->with('error', 'No se pudo cancelar: ' . $e->getMessage());
        }
        return back()->with('ok', $estado === 'cancelada' ? "Factura global {$global->folio_texto} cancelada; sus ventas quedaron libres para otra global." : 'Pedimos la cancelación.');
    }

    /* ---------------- Reportes ---------------- */

    private function mes(Request $request): string
    {
        $m = (string) $request->query('mes');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : now(config('vandu.zona_horaria'))->format('Y-m');
    }

    public function reportes(Request $request)
    {
        $mes = $this->mes($request);
        return view('admin.facturas.reportes', ['mes' => $mes, 'meses' => \App\Support\Finanzas\PaqueteContador::meses(), 'd' => \App\Support\Facturas\Reporte::datos($mes)]);
    }

    public function excel(Request $request)
    {
        $mes = $this->mes($request);
        return response(\App\Support\Facturas\Reporte::excel($mes), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="Facturacion ' . $mes . '.xlsx"',
        ]);
    }

    public function zip(Request $request)
    {
        $mes = $this->mes($request);
        return response(\App\Support\Facturas\Reporte::zip($mes), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="CFDI ' . $mes . '.zip"']);
    }
}
