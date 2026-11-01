<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Cuentas;
use App\Support\Facturas\Organizacion;
use App\Support\Facturas\OrganizacionError;
use App\Support\Facturas\Timbrado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Configuración › Facturación: datos fiscales del negocio y su sello digital para timbrar desde Nerby
 * (o, si el servidor no tiene la cuenta de Nerby, la clave de su propia cuenta de Facturapi).
 */
class FacturacionController extends Controller
{
    public function index()
    {
        $a = Timbrado::ajustes();
        return view('admin.facturacion', [
            'a' => $a, 'activo' => Timbrado::activo(), 'nerby' => Organizacion::disponible() && ! filled($a['key']),
            'timbradas' => Timbrado::timbradasEsteMes(Cuentas::actual()),
        ]);
    }

    /** Paso 1: razón social, régimen, C.P. y dirección (se pueden llenar con la constancia) */
    public function emisor(Request $request)
    {
        abort_unless(Organizacion::disponible(), 404);
        $request->merge(['cp' => preg_replace('/\D/', '', (string) $request->input('cp'))]);
        $d = $request->validate([
            'razon_social' => 'required|string|max:255',
            'regimen' => ['required', Rule::in(array_keys(config('vandu.sat.regimenes')))],
            'cp' => 'required|digits:5',
            'direccion' => 'nullable|string|max:300',
        ], [
            'razon_social.required' => 'Escribe tu razón social como aparece en tu constancia.', 'regimen.required' => 'Elige tu régimen fiscal.',
            'cp.required' => 'Escribe el código postal de tu domicilio fiscal.', 'cp.digits' => 'El código postal tiene 5 números.',
        ]);
        try {
            Organizacion::guardarEmisor($d);
        } catch (OrganizacionError $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->route('admin.facturacion')->with('ok', empty(Timbrado::ajustes()['certificado']['tiene'])
            ? 'Datos fiscales guardados. Ya puedes hacer facturas de prueba; sube tu sello digital para facturar de verdad.'
            : 'Datos fiscales actualizados.');
    }

    /** Paso 2: el sello digital (CSD). Va directo a Facturapi; Nerby no lo guarda */
    public function sello(Request $request)
    {
        abort_unless(Organizacion::disponible(), 404);
        $request->validate([
            'cer' => ['required', 'file', 'max:100', fn ($at, $f, $fail) => strtolower($f->getClientOriginalExtension()) === 'cer' ?: $fail('El certificado es el archivo que termina en .cer.')],
            'key' => ['required', 'file', 'max:100', fn ($at, $f, $fail) => strtolower($f->getClientOriginalExtension()) === 'key' ?: $fail('La llave privada es el archivo que termina en .key.')],
            'password' => 'required|string|max:100',
            'acepto' => 'accepted',
        ], [
            'cer.required' => 'Elige el archivo .cer de tu sello.', 'key.required' => 'Elige el archivo .key de tu sello.',
            'password.required' => 'Escribe la contraseña de tu sello digital.', 'acepto.accepted' => 'Para continuar, autoriza que se envíe tu sello a Facturapi.',
        ]);
        try {
            $c = Organizacion::subirSello($request->file('cer'), $request->file('key'), (string) $request->input('password'));
        } catch (OrganizacionError $e) {
            return back()->with('error', $e->getMessage());
        }
        return redirect()->route('admin.facturacion')->with('ok', '¡Listo! Tu sello quedó activo' . (! empty($c['vence']) ? ' (vence el ' . \Illuminate\Support\Carbon::parse($c['vence'])->locale('es')->isoFormat('D [de] MMMM [de] YYYY') . ')' : '') . '. Ya puedes facturar desde Nerby.');
    }

    /** Facturar de prueba o de verdad */
    public function modo(Request $request)
    {
        $a = Timbrado::ajustes();
        abort_unless($a['org_id'], 404);
        $pruebas = $request->boolean('pruebas');
        if (! $pruebas && empty($a['certificado']['tiene'])) return back()->with('error', 'Para facturar de verdad, primero sube tu sello digital.');
        Timbrado::guardarAjustes(['pruebas' => $pruebas]);
        return back()->with('ok', $pruebas ? 'Modo de pruebas: las facturas no tienen validez ante el SAT.' : 'Listo: las facturas ya se timbran de verdad.');
    }

    /** Claves del SAT por defecto, serie y (sin la cuenta de Nerby) la clave de su propia cuenta de Facturapi */
    public function guardar(Request $request)
    {
        $d = $request->validate([
            'key' => ['nullable', 'string', 'max:200', 'regex:/^sk_(live|test)_[A-Za-z0-9]+$/'],
            'clave_prod' => 'required|digits:8', 'clave_unidad' => 'required|string|max:3', 'clave_servicio' => 'required|string|max:3',
            'serie' => 'nullable|string|max:10|alpha_num', 'quitar' => 'nullable|boolean',
        ], ['key.regex' => 'La clave secreta de Facturapi empieza con sk_live_ o sk_test_.', 'clave_prod.digits' => 'La clave del SAT tiene 8 números.']);
        Timbrado::guardarAjustes($d);
        if (! empty($d['key'])) {
            [$ok, $msg] = Timbrado::probar();
            return back()->with($ok ? 'ok' : 'error', $msg);
        }
        return back()->with('ok', ! empty($d['quitar']) ? 'Desconectamos Facturapi.' : 'Ajustes de facturación guardados.');
    }
}
