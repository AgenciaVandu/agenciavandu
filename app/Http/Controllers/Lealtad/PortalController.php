<?php

namespace App\Http\Controllers\Lealtad;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Support\IconoApp;
use App\Support\Lealtad\Codigo;
use App\Support\Lealtad\Programa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** App del comensal: sus restaurantes con su avance, su QR que cambia cada minuto, la puerta de cada restaurante y su perfil */
class PortalController extends Controller
{
    private function yo() { return Auth::guard('comensal')->user(); }

    public function inicio()
    {
        if (! $this->yo()) return app(AccesoController::class)->entrar(request());
        $p = $this->yo();
        $lugares = Programa::restaurantesDe($p)->map(fn ($i) => ['cuenta' => $i->cuenta, 'avance' => Programa::avance($i->cuenta, $p)])
            ->sortByDesc(fn ($x) => $x['avance']->sum(fn ($a) => $a['disponibles']->count()))->values();
        return view('lealtad.inicio', ['p' => $p, 'lugares' => $lugares]);
    }

    public function qr()
    {
        return view('lealtad.qr', ['p' => $this->yo(), 'codigo' => $this->datosCodigo()]);
    }

    public function codigo()
    {
        return response()->json($this->datosCodigo())->header('Cache-Control', 'no-store');
    }

    private function datosCodigo(): array
    {
        $p = $this->yo();
        return ['qr' => Codigo::qr($p), 'corto' => Codigo::corto($p), 'restante' => Codigo::restante()];
    }

    /** La puerta de un restaurante: su campaña, sus premios y unirse (pública; es a donde lleva el QR del cartel) */
    public function restaurante(string $slug)
    {
        $c = Cuenta::where('slug', $slug)->firstOrFail();
        abort_unless($c->activa && $c->tiene('lealtad'), 404);
        $p = $this->yo();
        $inscrito = $p && Programa::inscrito($c, $p);
        return view('lealtad.restaurante', [
            'c' => $c, 'p' => $p, 'inscrito' => $inscrito,
            'campanas' => Programa::campanas($c)->filter->vigente->values(),
            'avance' => $inscrito ? Programa::avance($c, $p) : collect(),
        ]);
    }

    public function unirme(Request $request, string $slug)
    {
        $c = Cuenta::where('slug', $slug)->firstOrFail();
        abort_unless($c->activa && $c->tiene('lealtad'), 404);
        if (! $this->yo()) return redirect()->route('lealtad.registro', ['r' => $slug]);
        $request->validate(['compartir' => 'accepted'], ['compartir.accepted' => 'Para unirte necesitas aceptar compartir tus datos con el restaurante.']);
        Programa::unir($c, $this->yo());
        return redirect()->route('lealtad.restaurante', $slug)->with('ok', "¡Listo! Ya estás en el programa de {$c->nombre}. Muestra tu QR en cada visita.");
    }

    public function baja(string $slug)
    {
        $c = Cuenta::where('slug', $slug)->firstOrFail();
        Programa::baja($c, $this->yo());
        return redirect()->route('lealtad.perfil')->with('ok', "Saliste del programa de {$c->nombre}. Ya no verá tus datos de contacto.");
    }

    public function perfil()
    {
        return view('lealtad.perfil', ['p' => $this->yo(), 'lugares' => Programa::restaurantesDe($this->yo())]);
    }

    public function guardarPerfil(Request $request)
    {
        $p = $this->yo();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $d = $request->validate([
            'nombre'   => 'required|string|max:120',
            'telefono' => 'required|string|max:30',
            'email'    => ['required', 'email', 'max:190', Rule::unique('comensales', 'email')->ignore($p->id)],
            'actual'   => 'nullable|required_with:password|string',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], ['email.unique' => 'Ese correo ya lo usa otra cuenta.', 'actual.required_with' => 'Escribe tu contraseña actual para cambiarla.']);
        if (! empty($d['password'])) {
            if (! \Illuminate\Support\Facades\Hash::check($d['actual'], $p->password)) return back()->withErrors(['actual' => 'Tu contraseña actual no coincide.']);
            $p->password = $d['password'];
        }
        $p->fill(['nombre' => trim($d['nombre']), 'telefono' => trim($d['telefono']), 'email' => $d['email']])->save();
        return back()->with('ok', 'Datos guardados. Tus restaurantes ven la versión nueva.');
    }

    public function manifiesto()
    {
        $n = config('vandu.plataforma.nombre');
        return response()->json([
            'name' => $n . ' · Lealtad', 'short_name' => $n, 'description' => 'Tus restaurantes, tus visitas y tus premios',
            'id' => '/lealtad', 'start_url' => '/lealtad', 'scope' => '/lealtad', 'display' => 'standalone',
            'background_color' => '#FFF4EC', 'theme_color' => '#1A103D', 'lang' => 'es-MX',
            'icons' => [
                ['src' => IconoApp::url(null, 'icono-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => IconoApp::url(null, 'icono-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => IconoApp::url(null, 'icono-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }
}
