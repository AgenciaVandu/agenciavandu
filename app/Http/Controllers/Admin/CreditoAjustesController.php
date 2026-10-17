<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Credito;
use App\Support\Cuentas;
use Illuminate\Http\Request;

/** Condiciones de crédito del lote: tasa, plazo, enganche, moratorio, descuento por adelanto y termómetro */
class CreditoAjustesController extends Controller
{
    public function show()
    {
        return view('admin.credito-ajustes', ['a' => Credito::ajustes()]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'tasa'               => 'required|numeric|min:0|max:20',
            'plazo'              => 'required|integer|min:1|max:120',
            'enganche_pct'       => 'required|numeric|min:0|max:100',
            'moratorio_modo'     => 'required|in:dia,porcentaje',
            'moratorio_valor'    => 'required|numeric|min:0|max:100000',
            'dias_gracia'        => 'required|integer|min:0|max:60',
            'descuento_adelanto' => 'required|numeric|min:0|max:1000000',
            'termometro'         => 'required|array|min:2|max:6',
            'termometro.*.nombre' => 'required|string|max:30',
            'termometro.*.hasta' => 'nullable|integer|min:0|max:120',
            'termometro.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['termometro.*.nombre.required' => 'Cada nivel del termómetro necesita un nombre.']);

        // Rangos en orden; el último no tiene tope
        $rangos = collect($d['termometro'])->values()->map(fn ($r, $i) => ['nombre' => trim($r['nombre']), 'hasta' => isset($r['hasta']) && $r['hasta'] !== '' ? (int) $r['hasta'] : null, 'color' => strtolower($r['color'])]);
        $ultimo = $rangos->count() - 1;
        $rangos = $rangos->map(fn ($r, $i) => $i === $ultimo ? array_merge($r, ['hasta' => null]) : array_merge($r, ['hasta' => $r['hasta'] ?? $i]))
            ->sortBy(fn ($r) => $r['hasta'] ?? PHP_INT_MAX)->values()->all();

        $c = Cuentas::actual();
        $a = $c->ajustes ?? [];
        $a['credito'] = ['tasa' => (float) $d['tasa'], 'plazo' => (int) $d['plazo'], 'enganche_pct' => (float) $d['enganche_pct'],
            'moratorio_modo' => $d['moratorio_modo'], 'moratorio_valor' => (float) $d['moratorio_valor'], 'dias_gracia' => (int) $d['dias_gracia'],
            'descuento_adelanto' => (float) $d['descuento_adelanto'], 'termometro' => $rangos];
        $c->update(['ajustes' => $a]);
        Cuentas::activar($c->fresh());
        return back()->with('ok', 'Condiciones guardadas. Las ventas nuevas toman estos valores; las que ya existen conservan las suyas.');
    }
}
