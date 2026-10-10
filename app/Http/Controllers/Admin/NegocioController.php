<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Cuentas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** "Mi negocio": nombre, logo y los datos que ven los clientes en cotizaciones, correos y pagos */
class NegocioController extends Controller
{
    public function show()
    {
        $c = Cuentas::actual();
        return view('admin.negocio', [
            'c'         => $c,
            'v'         => config('vandu'),   // ya trae lo de la cuenta encima de lo de fábrica
            'a'         => $c->ajustes ?? [],
            'principal' => Cuentas::esPrincipal(),
        ]);
    }

    public function update(Request $request)
    {
        $c = Cuentas::actual();
        $d = $request->validate([
            'nombre'              => 'required|string|max:120',
            'marca.ciudad'        => 'nullable|string|max:80',
            'marca.sitio'         => 'nullable|string|max:120',
            'emisor.nombre'       => 'nullable|string|max:120',
            'emisor.telefono'     => 'nullable|string|max:40',
            'emisor.email'        => 'nullable|email|max:190',
            'whatsapp'            => 'nullable|string|max:20',
            'pago.banco'          => 'nullable|string|max:80',
            'pago.clabe'          => 'nullable|string|max:30',
            'pago.beneficiario'   => 'nullable|string|max:120',
            'pago.nota_comprobante' => 'nullable|string|max:400',
            'pago.nota_factura'   => 'nullable|string|max:400',
            'titulo'              => 'nullable|string|max:80',
            'folio_prefijo'       => ['nullable', 'string', 'max:8', 'regex:/^[A-Za-z0-9-]*$/'],
            'vigencia_dias'       => 'nullable|integer|min:1|max:365',
            'correo_responder'    => 'nullable|email|max:190',
            'dropbox_carpeta'     => ['nullable', 'string', 'max:60', 'regex:/^[^\\\\\/<>:"|?*]+$/'],
            'logo'                => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ], [
            'folio_prefijo.regex'   => 'El prefijo del folio solo lleva letras, números y guiones.',
            'dropbox_carpeta.regex' => 'El nombre de la carpeta no puede llevar / ni caracteres especiales.',
            'logo.mimes'            => 'El logo debe ser PNG, JPG o SVG.',
        ]);

        $a = $c->ajustes ?? [];
        $limpio = fn ($x) => is_string($x) ? (trim($x) === '' ? null : trim($x)) : $x;
        foreach (['marca', 'emisor', 'pago'] as $k) {
            $a[$k] = array_map($limpio, array_merge($a[$k] ?? [], $d[$k] ?? []));
        }
        $a['marca']['nombre'] = $d['nombre'];
        foreach (['whatsapp', 'titulo', 'folio_prefijo', 'vigencia_dias', 'correo_responder', 'dropbox_carpeta'] as $k) {
            $a[$k] = $limpio($d[$k] ?? null);
        }
        if (! empty($a['whatsapp'])) {
            $tel = preg_replace('/\D/', '', $a['whatsapp']);
            $a['whatsapp'] = strlen($tel) === 10 ? '52' . $tel : $tel;
        }
        $a['correo_nombre'] = $d['nombre'];

        if ($request->hasFile('logo')) {
            $f = $request->file('logo');
            $ext = strtolower($f->getClientOriginalExtension()) === 'svg' ? 'svg' : ($f->extension() === 'png' ? 'png' : 'jpg');
            Storage::disk('local')->deleteDirectory("cuentas/{$c->id}/logo");
            $a['marca']['logo_oscuro'] = $f->storeAs("cuentas/{$c->id}/logo", 'logo.' . $ext, 'local');
        } elseif ($request->boolean('quitar_logo')) {
            Storage::disk('local')->deleteDirectory("cuentas/{$c->id}/logo");
            unset($a['marca']['logo_oscuro']);
        }

        $c->update(['nombre' => $d['nombre'], 'ajustes' => $a]);
        Cuentas::activar($c->fresh());
        return redirect()->route('admin.negocio')->with('ok', 'Datos del negocio guardados. Así aparecen en tus cotizaciones y correos.');
    }
}
