<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\LicenciaComprobante;
use App\Models\LicenciaPago;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Licencias\Estado;
use App\Support\Licencias\Licencias;
use App\Support\Licencias\Recibo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/** Mi plan: lo que el dueño tiene contratado, cuánto usa, cuándo paga, sus recibos y "Ya pagué" */
class MiPlanController extends Controller
{
    public function index()
    {
        $c = Cuentas::actual();
        $e = Estado::de($c);
        abort_unless($e->licencia, 404);
        $l = $e->licencia->load(['plan', 'pagos', 'comprobantes']);
        return view('admin.mi-plan', [
            'c' => $c, 'e' => $e, 'l' => $l,
            'pago' => Estado::datosPago(),
            'uso' => [
                'usuarios'   => Licencias::usuariosActivos($c),
                'sucursales' => \App\Models\Sucursal::count(),
                'ia'         => \App\Support\IA\Anthropic::usadasEsteMes($c),
                'iaTope'     => (int) $c->ajuste('gastos_tope', \App\Support\IA\Anthropic::TOPE),
            ],
        ]);
    }

    /** "Ya pagué": sube su comprobante; a la plataforma le llega un aviso para confirmarlo */
    public function comprobante(Request $request)
    {
        $c = Cuentas::actual();
        $l = Estado::de($c)->licencia;
        abort_unless($l, 404);
        $d = $request->validate([
            'monto'      => 'required|numeric|min:1|max:9999999',
            'fecha'      => 'required|date|before_or_equal:' . now(config('vandu.zona_horaria'))->addDay()->toDateString(),
            'referencia' => 'nullable|string|max:120',
            'nota'       => 'nullable|string|max:300',
            'archivo'    => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ], ['archivo.required' => 'Sube la foto o el PDF de tu comprobante.', 'archivo.mimes' => 'El comprobante tiene que ser foto (JPG, PNG) o PDF.', 'archivo.max' => 'El comprobante pesa más de 8 MB.']);
        if (LicenciaComprobante::where('cuenta_id', $c->id)->where('estado', 'pendiente')->count() >= 3) {
            return back()->with('error', 'Ya tienes comprobantes por revisar. En cuanto los confirmemos te avisamos.');
        }
        $ruta = $request->file('archivo')->store('licencias/' . $c->id);
        $comp = LicenciaComprobante::create(['cuenta_id' => $c->id, 'licencia_id' => $l->id, 'user_id' => $request->user()->id, 'fecha' => $d['fecha'], 'monto' => $d['monto'],
            'referencia' => $d['referencia'] ?? null, 'nota' => $d['nota'] ?? null, 'archivo' => $ruta]);

        $url = rtrim(config('app.url'), '/') . route('admin.plataforma.licencia', $c, false);
        foreach (User::sinCuenta()->where('plataforma', true)->where('activo', true)->get() as $u) {
            try {
                Mail::to($u->email, $u->name)->send(new CorreoVandu(
                    asunto: "Comprobante de pago · {$c->nombre}",
                    titulo: "{$c->nombre} dice que ya pagó",
                    cuerpo: "{$request->user()->name} subió un comprobante por $" . number_format($comp->monto, 2) . ' del ' . $comp->fecha->locale('es')->isoFormat('D [de] MMMM') . '.'
                        . ($comp->referencia ? "\nReferencia: {$comp->referencia}" : '') . ($comp->nota ? "\n\n{$comp->nota}" : '') . "\n\nRevísalo y confírmalo para que su plan se recorra.",
                    boton: 'Revisar comprobante', url: $url));
            } catch (\Throwable $ex) {
                report($ex);
            }
        }
        return back()->with('ok', 'Recibimos tu comprobante. En cuanto lo confirmemos, tu plan se actualiza y te llega el recibo.');
    }

    public function recibo(LicenciaPago $pago)
    {
        abort_unless($pago->cuenta_id === Cuentas::id(), 404);
        return Recibo::pdf($pago);
    }

    public function verComprobante(LicenciaComprobante $comprobante)
    {
        abort_unless($comprobante->cuenta_id === Cuentas::id() && $comprobante->archivo && Storage::exists($comprobante->archivo), 404);
        return response()->file(Storage::path($comprobante->archivo));
    }
}
