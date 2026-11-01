<?php

namespace App\Support\Facturas;

use App\Models\FacturaSerie;

/** Series de facturación: cada tipo de CFDI tiene una predeterminada; el folio lo lleva Nerby para tener la secuencia completa */
class Series
{
    /** Las activas de un tipo, la predeterminada primero (si no hay ninguna se crea la sugerida) */
    public static function de(string $tipo)
    {
        if (! FacturaSerie::where('tipo', $tipo)->exists()) self::crearSugerida($tipo);
        return FacturaSerie::where('tipo', $tipo)->where('activa', true)->orderByDesc('predeterminada')->orderBy('serie')->get();
    }

    public static function elegir(string $tipo, ?int $id = null): FacturaSerie
    {
        $s = $id ? FacturaSerie::where('tipo', $tipo)->where('activa', true)->find($id) : null;
        return $s ?? self::de($tipo)->first() ?? self::crearSugerida($tipo);
    }

    private static function crearSugerida(string $tipo): FacturaSerie
    {
        // La serie que ya usaban en Facturación pasa a ser la de facturas
        $serie = $tipo === 'I' && ($vieja = trim((string) Timbrado::ajustes()['serie'])) !== '' ? mb_strtoupper($vieja) : FacturaSerie::SUGERIDAS[$tipo];
        $libre = $serie; $n = 2;
        while (FacturaSerie::where('serie', $libre)->exists()) $libre = $serie . $n++;
        return FacturaSerie::create(['serie' => $libre, 'tipo' => $tipo, 'descripcion' => FacturaSerie::TIPOS[$tipo], 'siguiente' => 1, 'predeterminada' => true, 'activa' => true]);
    }

    /** Marca una como predeterminada de su tipo */
    public static function predeterminar(FacturaSerie $s): void
    {
        FacturaSerie::where('tipo', $s->tipo)->where('id', '!=', $s->id)->update(['predeterminada' => false]);
        $s->update(['predeterminada' => true, 'activa' => true]);
    }
}
