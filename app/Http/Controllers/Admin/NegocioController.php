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
            'logo_claro'          => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
            'icono'               => 'nullable|image|mimes:png,jpg,jpeg,webp|max:4096|dimensions:min_width=192,min_height=192',
            'icono_fondo'         => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'icono.mimes'           => 'El ícono debe ser PNG o JPG.',
            'icono.dimensions'      => 'El ícono debe medir al menos 192 × 192 px (mejor 512 × 512).',
            'folio_prefijo.regex'   => 'El prefijo del folio solo lleva letras, números y guiones.',
            'dropbox_carpeta.regex' => 'El nombre de la carpeta no puede llevar / ni caracteres especiales.',
            'logo.mimes'            => 'El logo debe ser PNG, JPG o SVG.',
            'logo_claro.mimes'      => 'El logo debe ser PNG, JPG o SVG.',
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

        // Dos versiones: la que va sobre fondo oscuro (marca.logo_oscuro) y la que va sobre blanco (marca.logo)
        foreach (['logo' => ['logo_oscuro', 'logo'], 'logo_claro' => ['logo', 'claro']] as $campo => [$clave, $archivo]) {
            $quitar = $request->boolean($campo === 'logo' ? 'quitar_logo' : 'quitar_logo_claro');
            if (! $request->hasFile($campo) && ! $quitar) continue;
            if (! empty($a['marca'][$clave])) Storage::disk('local')->delete($a['marca'][$clave]);
            unset($a['marca'][$clave]);
            if ($request->hasFile($campo)) {
                $f = $request->file($campo);
                $ext = strtolower($f->getClientOriginalExtension()) === 'svg' ? 'svg' : ($f->extension() === 'png' ? 'png' : 'jpg');
                $a['marca'][$clave] = $f->storeAs("cuentas/{$c->id}/logo", $archivo . '-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5)) . '.' . $ext, 'local');
            }
        }

        // Ícono de la app (panel instalado y portal del atleta): se generan todos los tamaños
        $fondo = strtoupper($d['icono_fondo'] ?? ($a['app']['fondo'] ?? '#FFFFFF'));
        if ($request->boolean('quitar_icono')) {
            \App\Support\IconoApp::quitar($c);
            unset($a['app']);
        } elseif ($request->hasFile('icono')) {
            try {
                $a['app'] = ['icono' => \App\Support\IconoApp::generar($c, $request->file('icono'), $fondo), 'fondo' => $fondo];
            } catch (\RuntimeException $e) {
                return back()->withInput()->withErrors(['icono' => $e->getMessage()]);
            }
        }

        $c->update(['nombre' => $d['nombre'], 'ajustes' => $a]);
        Cuentas::activar($c->fresh());
        return redirect()->route('admin.negocio')->with('ok', 'Datos del negocio guardados. Así aparecen en tus cotizaciones y correos.');
    }
}
