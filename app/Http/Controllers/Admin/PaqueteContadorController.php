<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Cuenta;
use App\Support\Cuentas;
use App\Support\Finanzas\PaqueteContador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/** Paquete para el contador: descargar el ZIP del mes o mandárselo por correo con un enlace que dura 15 días */
class PaqueteContadorController extends Controller
{
    private function mes(Request $request): string
    {
        return $request->validate(['mes' => ['nullable', Rule::in(array_keys(PaqueteContador::meses()))]])['mes'] ?? PaqueteContador::mesSugerido();
    }

    private function zip(Cuenta $c, string $mes)
    {
        $ruta = PaqueteContador::armar($c, $mes);
        return response()->download($ruta, PaqueteContador::nombre($c, $mes), ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function descargar(Request $request)
    {
        return $this->zip(Cuentas::actual(), $this->mes($request));
    }

    public function enviar(Request $request)
    {
        $mes = $this->mes($request);
        $d = $request->validate(['email' => 'required|email:filter|max:160', 'nombre' => 'nullable|string|max:80', 'nota' => 'nullable|string|max:600'],
            ['email.required' => 'Escribe el correo de tu contador.', 'email.email' => 'Revisa el correo de tu contador.']);
        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        data_set($a, 'contador', ['email' => $d['email'], 'nombre' => $d['nombre'] ?? null]);
        $c->forceFill(['ajustes' => $a])->save();

        $titulo = PaqueteContador::meses()[$mes];
        $url = URL::temporarySignedRoute('contador.paquete', now()->addDays(15), ['cuenta' => $c->id, 'mes' => $mes]);
        $hola = filled($d['nombre'] ?? null) ? 'Hola ' . trim($d['nombre']) . ',' : 'Hola,';
        try {
            Mail::to($d['email'], $d['nombre'] ?? null)->send(new CorreoVandu(
                asunto: "Contabilidad de {$titulo} · {$c->nombre}",
                titulo: "Contabilidad de {$titulo}",
                cuerpo: "$hola\n\n{$request->user()->name} te comparte la información de {$c->nombre} de {$titulo}: el Excel con ingresos, gastos y compras, los XML de las facturas, las fotos de los tickets y las facturas emitidas."
                    . (filled($d['nota'] ?? null) ? "\n\n" . trim($d['nota']) : '') . "\n\nEl enlace funciona 15 días.",
                boton: 'Descargar el paquete (ZIP)',
                url: $url,
            ));
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['email' => 'No se pudo mandar el correo. Descarga el paquete y mándalo tú.'])->withInput();
        }
        return back()->with('ok', "Le mandamos a {$d['email']} el paquete de {$titulo}.");
    }

    /** El enlace del correo: firmado, sin sesión */
    public function publico(Request $request, int $cuenta, string $mes)
    {
        $c = Cuentas::activar($cuenta);
        abort_if(! $c || ! $c->activa || ! preg_match('/^\d{4}-\d{2}$/', $mes), 404);
        return $this->zip($c, $mes);
    }
}
