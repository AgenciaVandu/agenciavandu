<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presupuesto;
use App\Models\PresupuestoConcepto;
use App\Support\Cuentas;
use App\Support\DisenoDocumento;
use App\Support\PresupuestoPdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Diseño de las cotizaciones del negocio: plantilla, hoja membretada, color, letra y papel */
class DocumentoController extends Controller
{
    public function show()
    {
        $d = DisenoDocumento::de();
        return view('admin.documento', [
            'd' => $d,
            'membrete' => DisenoDocumento::dataUri($d['membrete']),
            'logoOscuro' => DisenoDocumento::logo(true),
            'logoClaro' => DisenoDocumento::logo(false),
        ]);
    }

    public function update(Request $request)
    {
        $v = $request->validate([
            'plantilla'      => ['required', Rule::in(array_keys(DisenoDocumento::PLANTILLAS))],
            'color'          => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'letra'          => ['required', Rule::in(array_keys(DisenoDocumento::LETRAS))],
            'papel'          => ['required', Rule::in(array_keys(DisenoDocumento::PAPELES))],
            'membrete'       => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'quitar_membrete' => 'boolean',
            'en_todas'       => 'boolean',
            'con_encabezado' => 'boolean',
            'sup'            => 'required|numeric|min:5|max:120',
            'inf'            => 'required|numeric|min:5|max:80',
            'izq'            => 'required|numeric|min:5|max:50',
            'der'            => 'required|numeric|min:5|max:50',
        ], ['membrete.mimes' => 'La hoja membretada debe ser PNG, JPG o PDF.', 'membrete.max' => 'La hoja membretada pesa más de 10 MB.', 'color.regex' => 'Elige un color válido.']);

        $c = Cuentas::actual();
        $antes = DisenoDocumento::de($c);
        $d = array_merge($antes, [
            'plantilla' => $v['plantilla'], 'color' => strtolower($v['color']), 'letra' => $v['letra'], 'papel' => $v['papel'],
            'en_todas' => $request->boolean('en_todas'), 'con_encabezado' => $request->boolean('con_encabezado'),
            'sup' => (float) $v['sup'], 'inf' => (float) $v['inf'], 'izq' => (float) $v['izq'], 'der' => (float) $v['der'],
        ]);

        if ($request->hasFile('membrete')) {
            try {
                $nuevo = DisenoDocumento::guardarMembrete($c, $request->file('membrete'));
            } catch (\RuntimeException $e) {
                return back()->withErrors(['membrete' => $e->getMessage()])->withInput();
            }
            DisenoDocumento::borrarArchivos($antes);
            $d['membrete'] = $nuevo;
            $d['franja_sup'] = $d['franja_inf'] = null;
        } elseif ($request->boolean('quitar_membrete')) {
            DisenoDocumento::borrarArchivos($antes);
            $d['membrete'] = $d['franja_sup'] = $d['franja_inf'] = null;
        }
        $d = DisenoDocumento::recortarFranjas($d);

        $a = $c->ajustes ?? [];
        $a['documento'] = $d;
        $c->update(['ajustes' => $a]);
        Cuentas::activar($c->fresh());

        return redirect()->route('admin.negocio.documento')->with('ok', 'Diseño guardado. Así salen ya tus cotizaciones en PDF y en el enlace.');
    }

    /** PDF de muestra con el diseño guardado (la última cotización, o una de ejemplo) */
    public function muestra()
    {
        $p = Presupuesto::with('conceptos')->latest('id')->first() ?? $this->ejemplo();
        return PresupuestoPdf::generar($p)->stream('muestra-cotizacion.pdf');
    }

    private function ejemplo(): Presupuesto
    {
        $p = new Presupuesto([
            'folio' => 'MUESTRA', 'titulo' => 'Cotización de ejemplo', 'cliente_nombre' => 'Ana Pérez', 'cliente_empresa' => 'Empresa del Cliente',
            'fecha' => now(), 'vigente_hasta' => now()->addDays(15), 'modo_iva' => 'desglosado', 'iva_porcentaje' => 16, 'estado' => 'borrador',
            'emisor_nombre' => config('vandu.emisor.nombre'), 'emisor_telefono' => config('vandu.emisor.telefono'),
            'emisor_sitio' => config('vandu.emisor.sitio'), 'emisor_email' => config('vandu.emisor.email'),
            'observaciones' => "Precios en pesos mexicanos.\nTiempo de entrega: 10 días hábiles.",
            'consideraciones' => [['titulo' => 'Forma de pago', 'items' => ['50 % de anticipo y 50 % contra entrega.']]],
            'mostrar_pago' => false,
        ]);
        $p->setRelation('conceptos', collect([
            new PresupuestoConcepto(['titulo' => 'Producto o servicio principal', 'descripcion' => 'Descripción con medidas, materiales o alcance.', 'cantidad' => 2, 'precio' => 3490]),
            new PresupuestoConcepto(['titulo' => 'Instalación', 'descripcion' => 'Dentro de la ciudad.', 'cantidad' => 1, 'precio' => 650]),
        ]));
        return $p;
    }
}
