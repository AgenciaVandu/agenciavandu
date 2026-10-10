<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\CorreoPlantilla;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\Cliente;
use App\Support\Correos;
use App\Support\PlantillasCorreo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Editar las plantillas de correo desde el panel, con vista previa en vivo */
class PlantillaCorreoController extends Controller
{
    private const NO_EDITABLES = ['libre'];

    public function index(Request $request)
    {
        $todas = array_diff_key(PlantillasCorreo::todas(), array_flip(self::NO_EDITABLES));
        $clave = $request->query('p');
        if (! isset($todas[$clave])) $clave = array_key_first($todas);
        $pl = $todas[$clave];
        [, , $ejemplo] = $this->ejemplo($pl, $clave);

        return view('admin.correos.plantillas', [
            'todas'     => $todas,
            'clave'     => $clave,
            'pl'        => $pl,
            'original'  => PlantillasCorreo::original($clave),
            'variables' => PlantillasCorreo::variables(),
            'ejemplo'   => $ejemplo,
            'usos'      => \App\Models\Correo::where('plantilla', $clave)->count(),
            'iconos'    => PlantillasCorreo::ICONOS,
        ]);
    }

    public function update(Request $request, string $clave)
    {
        $pl = PlantillasCorreo::una($clave);
        abort_if(! $pl || in_array($clave, self::NO_EDITABLES, true), 404);
        $d = $this->validar($request, ! $pl['fabrica']);

        if ($pl['fabrica']) {
            $orig = PlantillasCorreo::original($clave);
            $igual = $d['activa']
                && $d['nombre'] === $orig['nombre'] && $d['asunto'] === $orig['asunto']
                && $d['titulo'] === (string) ($orig['titulo'] ?? '') && $this->norm($d['cuerpo']) === $this->norm($orig['cuerpo'])
                && (string) $d['boton'] === (string) ($orig['boton'] ?? '');
            if ($igual) {
                CorreoPlantilla::where('clave', $clave)->delete();
                PlantillasCorreo::limpiar();
            } else {
                CorreoPlantilla::updateOrCreate(['clave' => $clave], [
                    'propia' => false, 'nombre' => $d['nombre'], 'asunto' => $d['asunto'], 'titulo' => $d['titulo'],
                    'cuerpo' => $d['cuerpo'], 'boton' => $d['boton'], 'activa' => $d['activa'],
                ]);
            }
        } else {
            CorreoPlantilla::where('clave', $clave)->update([
                'nombre' => $d['nombre'], 'asunto' => $d['asunto'], 'titulo' => $d['titulo'], 'cuerpo' => $d['cuerpo'],
                'boton' => $d['boton'], 'activa' => $d['activa'], 'para' => json_encode($d['para']), 'resumen' => $d['resumen'],
                'icono' => $d['icono'], 'updated_at' => now(),
            ]);
        }
        PlantillasCorreo::limpiar();

        return redirect()->route('admin.correos.plantillas', ['p' => $clave])->with('ok', "Plantilla “{$d['nombre']}” guardada.");
    }

    public function restaurar(string $clave)
    {
        abort_unless(PlantillasCorreo::original($clave), 404);
        CorreoPlantilla::where('clave', $clave)->where('propia', false)->delete();
        PlantillasCorreo::limpiar();

        return redirect()->route('admin.correos.plantillas', ['p' => $clave])->with('ok', 'Se restauró el texto original.');
    }

    public function store()
    {
        $p = CorreoPlantilla::create([
            'clave' => 'propia_' . bin2hex(random_bytes(4)), 'propia' => true, 'nombre' => 'Plantilla nueva', 'icono' => 'bi-envelope',
            'para' => ['cliente', 'presupuesto', 'proyecto'], 'asunto' => '', 'titulo' => '', 'cuerpo' => "Hola {nombre},\n\n",
        ]);
        $p->update(['clave' => 'propia_' . $p->id]);

        return redirect()->route('admin.correos.plantillas', ['p' => $p->clave])->with('ok', 'Plantilla creada. Ponle nombre y texto.');
    }

    public function destroy(string $clave)
    {
        $p = CorreoPlantilla::where('clave', $clave)->where('propia', true)->firstOrFail();
        $nombre = $p->nombre;
        $p->delete();

        return redirect()->route('admin.correos.plantillas')->with('ok', "Se eliminó la plantilla “{$nombre}”.");
    }

    /** HTML de la vista previa con los textos que se están escribiendo */
    public function previa(Request $request, string $clave)
    {
        $base = PlantillasCorreo::una($clave);
        abort_unless($base, 404);
        $pl = array_merge($base, array_filter($request->only(['asunto', 'titulo', 'cuerpo', 'boton']), fn ($v) => $v !== null));
        if ($request->has('boton') && ! $request->filled('boton')) $pl['boton'] = null;
        if (! $base['fabrica']) {
            $pl['resumen'] = $request->boolean('resumen');
            $pl['para'] = array_values(array_intersect((array) $request->input('para', []), Correos::CONTEXTOS)) ?: $base['para'];
        }

        [$ctx, $pago] = $this->ejemplo($pl, $clave);
        $vars = $ctx ? Correos::variables($ctx, $pago) : [];
        foreach (PlantillasCorreo::variables() as $v => $info) {
            if (($vars[$v] ?? '') === '') $vars[$v] = $info['ejemplo'];
        }
        $r = fn ($t) => strtr((string) $t, $vars);
        $url = $ctx ? Correos::enlace($ctx, $clave) : url('/');

        $correo = new CorreoVandu(
            asunto: $r($pl['asunto']),
            titulo: $r($pl['titulo'] ?? ''),
            cuerpo: $r($pl['cuerpo'] ?? ''),
            boton: ! empty($pl['boton']) ? $r($pl['boton']) : null,
            url: $url,
            resumen: ! empty($pl['resumen']) && $ctx ? Correos::resumen($ctx, $pago?->id) : [],
            banco: ! empty($pl['banco']) ? Correos::banco($ctx['presupuesto'] ?? null) : [],
            miniaturas: ! empty($pl['miniaturas']) && $ctx ? Correos::miniaturas($ctx) : [],
            vistaPrevia: true,
        );

        return response($correo->render())->header('X-Robots-Tag', 'noindex')->header('X-Asunto', rawurlencode($r($pl['asunto'])));
    }

    /**
     * Datos reales de ejemplo para la vista previa: el proyecto o cotización más reciente que aplique.
     * @return array{0: ?array, 1: ?\App\Models\ProyectoPago, 2: ?string}
     */
    private function ejemplo(array $pl, string $clave): array
    {
        $para = $pl['para'] ?? ['cliente'];
        $proyecto = null;
        if (in_array('proyecto', $para, true)) {
            $q = Proyecto::latest('id');
            if ($clave === 'recordatorio_pago') $q->whereHas('pagos', fn ($w) => $w->whereNull('pagado_el'));
            if ($clave === 'entrega_digital') $q->whereHas('archivos', fn ($w) => $w->where('grupo', 'galeria')->where('visible', true));
            $proyecto = $q->first() ?? (in_array($clave, ['recordatorio_pago', 'entrega_digital'], true) ? Proyecto::latest('id')->first() : null);
        }
        if ($proyecto) {
            $ctx = Correos::contexto('proyecto', $proyecto->id);
            $pago = $clave === 'recordatorio_pago' ? $ctx['proyecto']->pagos->whereNull('pagado_el')->first() : null;
            return [$ctx, $pago, 'el proyecto ' . $proyecto->nombre];
        }
        if (in_array('presupuesto', $para, true) && ($p = Presupuesto::latest('id')->first())) {
            return [Correos::contexto('presupuesto', $p->id), null, 'la cotización ' . $p->folio];
        }
        if ($c = Cliente::latest('id')->first()) {
            return [Correos::contexto('cliente', $c->id), null, ($c->empresa ?: $c->nombre)];
        }
        return [null, null, null];
    }

    private function validar(Request $request, bool $propia): array
    {
        $d = $request->validate([
            'nombre' => 'required|string|max:60',
            'asunto' => 'required|string|max:200',
            'titulo' => 'nullable|string|max:120',
            'cuerpo' => 'required|string|max:10000',
            'boton'  => 'nullable|string|max:60',
            'activa' => 'nullable|boolean',
            'para'   => [$propia ? 'required' : 'nullable', 'array'],
            'para.*' => [Rule::in(Correos::CONTEXTOS)],
            'resumen' => 'nullable|boolean',
            'icono'  => ['nullable', Rule::in(PlantillasCorreo::ICONOS)],
        ], [
            'nombre.required' => 'Ponle un nombre a la plantilla.',
            'asunto.required' => 'La plantilla necesita un asunto.',
            'cuerpo.required' => 'La plantilla necesita un mensaje.',
            'para.required'   => 'Elige desde dónde se podrá usar.',
        ]);
        $d['titulo'] = (string) ($d['titulo'] ?? '');
        $d['boton'] = filled($d['boton'] ?? null) ? $d['boton'] : null;
        $d['activa'] = $request->boolean('activa');
        $d['resumen'] = $request->boolean('resumen');
        $d['para'] = array_values($d['para'] ?? []);
        $d['icono'] = $d['icono'] ?? 'bi-envelope';
        $d['cuerpo'] = str_replace("\r\n", "\n", $d['cuerpo']);
        return $d;
    }

    private function norm(string $t): string
    {
        return trim(str_replace("\r\n", "\n", $t));
    }
}
