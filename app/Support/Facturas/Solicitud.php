<?php

namespace App\Support\Facturas;

use App\Models\Cliente;
use App\Models\ClienteRazon;
use App\Models\Presupuesto;
use App\Models\Venta;
use App\Support\Push\Notificar;
use Illuminate\Validation\Rule;

/** El cliente pide factura desde su enlace: guarda sus datos fiscales y avisa al negocio */
class Solicitud
{
    public static function reglas(): array
    {
        return [
            'rfc' => ['required', 'string', 'regex:/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/'],
            'razon_social' => 'required|string|max:200',
            'regimen_fiscal' => ['required', Rule::in(array_keys(config('vandu.sat.regimenes', [])))],
            'cp_fiscal' => 'required|digits:5',
            'uso_cfdi' => ['required', Rule::in(array_keys(config('vandu.sat.usos_cfdi', [])))],
            'email_factura' => 'required|email:filter|max:190',
        ];
    }

    public const MENSAJES = [
        'rfc.required' => 'Escribe tu RFC.', 'rfc.regex' => 'Revisa tu RFC: son 12 o 13 letras y números, sin guiones.',
        'razon_social.required' => 'Escribe tu nombre o razón social como aparece en tu constancia.',
        'regimen_fiscal.required' => 'Elige tu régimen fiscal.', 'regimen_fiscal.in' => 'Elige tu régimen fiscal.',
        'cp_fiscal.required' => 'Escribe el código postal de tu constancia.', 'cp_fiscal.digits' => 'El código postal tiene 5 números.',
        'uso_cfdi.required' => 'Elige el uso de la factura.', 'uso_cfdi.in' => 'Elige el uso de la factura.',
        'email_factura.required' => 'Escribe el correo donde quieres recibirla.', 'email_factura.email' => 'Revisa tu correo.',
    ];

    /** Normaliza lo que escribió (mayúsculas, sin espacios) antes de validar */
    public static function limpiar(array $in): array
    {
        $in['rfc'] = preg_replace('/[\s-]/', '', mb_strtoupper((string) ($in['rfc'] ?? '')));
        $in['razon_social'] = trim(mb_strtoupper((string) ($in['razon_social'] ?? '')));
        $in['email_factura'] = trim(mb_strtolower((string) ($in['email_factura'] ?? '')));
        return $in;
    }

    /**
     * Guarda los datos en el cliente (o en otra razón social si ya tenía un RFC distinto) y marca la venta o cotización.
     * Si la venta no tiene cliente, se crea uno con esos datos.
     */
    public static function guardar(Presupuesto|Venta $m, array $d): void
    {
        $campos = ['rfc' => $d['rfc'], 'razon_social' => $d['razon_social'], 'regimen_fiscal' => $d['regimen_fiscal'],
            'cp_fiscal' => $d['cp_fiscal'], 'uso_cfdi' => $d['uso_cfdi'], 'email_factura' => $d['email_factura']];

        $cliente = $m->cliente;
        if (! $cliente && $m instanceof Presupuesto) $cliente = $m->asegurarCliente(false);
        if (! $cliente) {
            $cliente = Cliente::create(['nombre' => $m->cliente_nombre ?: $d['razon_social'], 'telefono' => $m->cliente_telefono ?? null, 'origen' => 'ventas'] + $campos);
            $m->cliente_id = $cliente->id;
        }

        $razonId = null;
        if (blank($cliente->rfc) || mb_strtoupper($cliente->rfc) === $d['rfc']) {
            $cliente->fill($campos)->save();
        } else {
            // Ya tenía otro RFC: se guarda como otra razón social del mismo cliente
            $r = ClienteRazon::where('cliente_id', $cliente->id)->where('rfc', $d['rfc'])->first()
                ?? new ClienteRazon(['nombre' => $d['razon_social']]);
            $r->cliente_id = $cliente->id;
            $r->fill($campos)->save();
            $razonId = $r->id;
        }

        $m->razon_id = $razonId;
        $m->factura_solicitada_at = now();
        $m->save();

        $quien = $m instanceof Venta ? $m->quien : ($m->cliente_empresa ?: $m->cliente_nombre);
        Notificar::evento('factura_solicitada', "{$m->folio}: {$quien} pidió factura", "{$d['rfc']} · {$d['razon_social']}",
            route('admin.facturas', ['ver' => 'por_facturar']), 'factura-pedida-' . $m->getTable() . '-' . $m->id);
    }
}
