<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Producto;
use App\Models\ProductoSlug;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\FotoProducto;

/**
 * Página pública de cada producto: con la marca del negocio, su foto, descripción y un botón
 * para pedirlo por WhatsApp. Si el enlace lo compartió un vendedor (?v=), el mensaje le llega a él.
 */
class CatalogoController extends Controller
{
    public function producto(string $slug)
    {
        $p = Producto::sinCuenta()->where('slug', $slug)->first();
        if (! $p) {
            // Enlace de cuando el producto tenía otro nombre
            $viejo = ProductoSlug::where('slug', $slug)->first();
            $actual = $viejo ? Producto::sinCuenta()->find($viejo->producto_id) : null;
            abort_unless($actual, 404);
            $q = request()->getQueryString();
            return redirect()->to(route('catalogo.producto', $actual->slug) . ($q ? '?' . $q : ''), 301);
        }

        $cuenta = $this->activar($p);
        $vistaPrevia = ! ($p->publico && $p->activo);
        abort_if($vistaPrevia && ! $this->esDelEquipo($cuenta), 404);

        $vendedor = $this->vendedor($cuenta, (string) request()->query('v'));
        $whatsapp = $vendedor?->whatsapp ?? $this->whatsappNegocio();
        $mensaje = 'Hola' . ($vendedor ? ' ' . $vendedor->primer_nombre : '') . ', me interesa: ' . $p->nombre
            . ($p->clave ? ' (' . $p->clave . ')' : '') . "\n" . $p->url;

        $relacionados = Producto::where('id', '!=', $p->id)->activos()->where('publico', true)
            ->when($p->categoria_id, fn ($q) => $q->where('categoria_id', $p->categoria_id))
            ->orderByRaw('foto IS NULL')->inRandomOrder()->limit(4)->get();

        return response()->view('catalogo.producto', [
            'p' => $p->load('categoria'), 'cuenta' => $cuenta, 'vendedor' => $vendedor, 'vistaPrevia' => $vistaPrevia,
            'whatsapp' => $whatsapp ? 'https://wa.me/' . $whatsapp . '?text=' . rawurlencode($mensaje) : null,
            'relacionados' => $relacionados, 'v' => $vendedor ? $vendedor->codigo_vendedor : null,
        ])->header('Cache-Control', 'private, max-age=0');
    }

    public function foto(int $producto, string $archivo)
    {
        $p = Producto::sinCuenta()->find($producto);
        $ruta = $p ? FotoProducto::ruta($p, $archivo) : null;
        abort_unless($ruta, 404);
        // El nombre del archivo cambia con cada foto nueva, así que se puede guardar en caché mucho tiempo
        return response()->file($ruta, ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    private function activar(Producto $p): Cuenta
    {
        $c = $p->cuenta_id ? Cuentas::activar((int) $p->cuenta_id) : Cuentas::actual();
        abort_unless($c && $c->activa && $c->tiene('stock'), 404);
        return $c;
    }

    /** Alguien del negocio con sesión puede ver la página aunque esté apagada (vista previa) */
    private function esDelEquipo(Cuenta $c): bool
    {
        $u = auth()->user();
        return $u && ($u->cuenta_id === $c->id || $u->plataforma || $u->esSuperAdmin());
    }

    private function vendedor(Cuenta $c, string $codigo): ?User
    {
        if (! preg_match('/^[a-f0-9]{8}$/', $codigo)) return null;
        return $c->usuarios()->whereNotNull('telefono')->get()->first(fn (User $u) => hash_equals($u->codigo_vendedor, $codigo) && $u->whatsapp);
    }

    private function whatsappNegocio(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) (config('vandu.whatsapp') ?: config('vandu.emisor.telefono')));
        if ($tel === '') return null;
        return strlen($tel) === 10 ? '52' . $tel : $tel;
    }
}
