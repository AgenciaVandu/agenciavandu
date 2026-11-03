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
            'series' => \App\Models\FacturaSerie::orderBy('tipo')->orderByDesc('predeterminada')->orderBy('serie')->get(),
            'timbres' => Organizacion::disponible() && ! filled($a['key']) && ! Cuentas::esPrincipal() ? \App\Support\Facturas\Timbres::resumen(Cuentas::actual()) : null,
            'paquetes' => \App\Support\Facturas\Timbres::paquetes(),
            'pagoNerby' => \App\Support\Licencias\Estado::datosPago(),
            'licencia' => \App\Support\Licencias\Estado::de(Cuentas::actual())->licencia,
            'pedidos' => \App\Models\LicenciaComprobante::where('cuenta_id', Cuentas::id())->whereNotNull('paquete_id')->where('estado', 'pendiente')->get(),
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

    /* ---------------- Series ---------------- */

    public function nuevaSerie(Request $request)
    {
        $request->merge(['serie' => mb_strtoupper(trim((string) $request->input('serie')))]);
        $d = $request->validate([
            'serie' => ['required', 'alpha_num', 'max:10', Rule::unique('factura_series', 'serie')->where('cuenta_id', Cuentas::id())],
            'tipo' => ['required', Rule::in(array_keys(\App\Models\FacturaSerie::TIPOS))],
            'descripcion' => 'nullable|string|max:120',
            'siguiente' => 'nullable|integer|min:1|max:99999999',
        ], ['serie.unique' => 'Ya tienes una serie con ese nombre.', 'serie.alpha_num' => 'La serie lleva solo letras y números.']);
        $s = \App\Models\FacturaSerie::create($d + ['siguiente' => $d['siguiente'] ?? 1, 'activa' => true,
            'predeterminada' => ! \App\Models\FacturaSerie::where('tipo', $d['tipo'])->where('activa', true)->exists()]);
        return redirect(route('admin.facturacion') . '#series')->with('ok', "Serie {$s->serie} creada para " . mb_strtolower($s->tipo_texto) . '.');
    }

    public function serie(Request $request, \App\Models\FacturaSerie $serie)
    {
        $d = $request->validate([
            'descripcion' => 'nullable|string|max:120',
            'siguiente' => 'required|integer|min:1|max:99999999',
            'activa' => 'nullable|boolean', 'predeterminada' => 'nullable|boolean',
        ]);
        $serie->update(['descripcion' => $d['descripcion'] ?? null, 'siguiente' => $d['siguiente'], 'activa' => $request->boolean('activa') || $request->boolean('predeterminada')]);
        if ($request->boolean('predeterminada')) \App\Support\Facturas\Series::predeterminar($serie);
        return redirect(route('admin.facturacion') . '#series')->with('ok', "Serie {$serie->serie} guardada.");
    }

    /* ---------------- Comprar timbres ---------------- */

    /** Pide un paquete: sube su comprobante y a la plataforma le llega para confirmarlo (al confirmar se cargan) */
    public function comprar(Request $request)
    {
        $c = Cuentas::actual();
        $l = \App\Support\Licencias\Estado::de($c)->licencia;
        if (! $l) return back()->with('error', 'Escríbenos para comprar timbres: tu cuenta aún no tiene licencia.');
        $d = $request->validate([
            'paquete_id' => ['required', Rule::exists('timbre_paquetes', 'id')->where('activo', true)],
            'fecha' => 'required|date|before_or_equal:' . now(config('vandu.zona_horaria'))->addDay()->toDateString(),
            'referencia' => 'nullable|string|max:120',
            'archivo' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ], ['paquete_id.required' => 'Elige el paquete.', 'archivo.required' => 'Sube la foto o el PDF de tu comprobante.', 'archivo.mimes' => 'El comprobante tiene que ser foto (JPG, PNG) o PDF.']);
        $paq = \App\Models\TimbrePaquete::find($d['paquete_id']);
        if (\App\Models\LicenciaComprobante::where('cuenta_id', $c->id)->where('estado', 'pendiente')->count() >= 3) {
            return back()->with('error', 'Ya tienes comprobantes por revisar. En cuanto los confirmemos te avisamos.');
        }
        $ruta = $request->file('archivo')->store('licencias/' . $c->id);
        $comp = \App\Models\LicenciaComprobante::create(['cuenta_id' => $c->id, 'licencia_id' => $l->id, 'user_id' => $request->user()->id, 'fecha' => $d['fecha'],
            'monto' => $paq->precio, 'referencia' => $d['referencia'] ?? null, 'nota' => "Paquete de timbres: {$paq->nombre} ({$paq->timbres})", 'archivo' => $ruta, 'paquete_id' => $paq->id]);
        $url = rtrim(config('app.url'), '/') . route('admin.plataforma.licencia', $c, false);
        foreach (\App\Models\User::sinCuenta()->where('plataforma', true)->where('activo', true)->get() as $u) {
            try {
                \Illuminate\Support\Facades\Mail::to($u->email, $u->name)->send(new \App\Mail\CorreoVandu(
                    asunto: "Compra de timbres · {$c->nombre}", titulo: "{$c->nombre} compró {$paq->timbres} timbres",
                    cuerpo: "{$request->user()->name} subió su comprobante por $" . number_format($paq->precio, 2) . " del paquete {$paq->nombre}.\n\nConfírmalo y los timbres se cargan solos.",
                    boton: 'Revisar comprobante', url: $url));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        return back()->with('ok', "Recibimos tu comprobante del paquete {$paq->nombre}. En cuanto lo confirmemos, tus timbres se cargan y te avisamos.");
    }
}
