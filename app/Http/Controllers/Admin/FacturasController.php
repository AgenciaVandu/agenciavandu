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
        ]);
        try {
            Timbrado::timbrar($doc, $d['forma_pago'], $d['metodo_pago'], $d['uso_cfdi'] ?? null);
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
}
