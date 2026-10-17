<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\FotoUnidad;

/** Página pública de cada auto: fotos, datos, ficha, precio, mensualidad estimada y WhatsApp al vendedor */
class AutosController extends Controller
{
    public function unidad(string $slug)
    {
        $u = Unidad::sinCuenta()->where('slug', $slug)->firstOrFail();
        $c = Cuentas::activar((int) $u->cuenta_id);
        abort_unless($c && $c->activa && $c->tiene('autos'), 404);
        $previa = ! $u->publico || $u->estado === 'vendido';
        $equipo = auth()->user() && (auth()->user()->cuenta_id === $c->id || auth()->user()->plataforma);
        abort_if($previa && ! $equipo, 404);

        $u->load(['fotos', 'sucursal', 'vendedor']);
        $vendedor = $this->vendedor($c, (string) request()->query('v')) ?? ($u->vendedor?->whatsapp ? $u->vendedor : null);
        $tel = $vendedor?->whatsapp ?? $this->whatsappNegocio();
        $mensaje = 'Hola' . ($vendedor ? ' ' . $vendedor->primer_nombre : '') . ', me interesa el ' . $u->nombre . "\n" . $u->url;

        $otras = Unidad::where('id', '!=', $u->id)->where('publico', true)->where('estado', '!=', 'vendido')->with('fotos')->latest()->limit(4)->get();

        return response()->view('autos.unidad', [
            'u' => $u, 'vendedor' => $vendedor, 'previa' => $previa, 'otras' => $otras,
            'whatsapp' => $tel ? 'https://wa.me/' . $tel . '?text=' . rawurlencode($mensaje) : null,
            'v' => $vendedor?->codigo_vendedor, 'estimada' => $u->mensualidad_estimada,
        ])->header('Cache-Control', 'private, max-age=0');
    }

    public function foto(int $unidad, string $archivo)
    {
        $u = Unidad::sinCuenta()->find($unidad);
        $ruta = $u ? FotoUnidad::ruta($u, $archivo) : null;
        abort_unless($ruta, 404);
        return response()->file($ruta, ['Content-Type' => 'image/webp', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    private function vendedor(Cuenta $c, string $codigo): ?User
    {
        if (! preg_match('/^[a-f0-9]{8}$/', $codigo)) return null;
        return $c->usuarios()->whereNotNull('telefono')->get()->first(fn (User $u) => hash_equals($u->codigo_vendedor, $codigo) && $u->whatsapp);
    }

    private function whatsappNegocio(): ?string
    {
        $tel = preg_replace('/\D/', '', (string) (config('vandu.whatsapp') ?: config('vandu.emisor.telefono')));
        return $tel === '' ? null : (strlen($tel) === 10 ? '52' . $tel : $tel);
    }
}
