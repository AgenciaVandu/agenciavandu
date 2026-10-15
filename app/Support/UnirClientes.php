<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\ClienteRazon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Une dos clientes que en realidad son la misma persona (p. ej. el mismo representante
 * registrado dos veces, una por cada empresa). Todo lo del duplicado pasa al cliente que se
 * queda y el duplicado se borra. Si se pide, la empresa del duplicado se guarda como otra
 * razón social y sus cotizaciones quedan a nombre de ella.
 */
class UnirClientes
{
    /** Campos de contacto y facturación que se toman del duplicado si al que se queda le faltan */
    private const COMPLETAR = ['email', 'telefono', 'empresa', 'rfc', 'razon_social', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'metodo_pago', 'dias_credito', 'email_factura'];
    private const FISCALES = ['razon_social', 'rfc', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi', 'email_factura'];

    /** @return array{cotizaciones: int, proyectos: int, razon: ?ClienteRazon} */
    public static function unir(Cliente $queda, Cliente $dup, bool $comoRazon): array
    {
        abort_if($queda->id === $dup->id, 422, 'Elige otro cliente.');

        return DB::transaction(function () use ($queda, $dup, $comoRazon) {
            $cot = DB::table('presupuestos')->where('cliente_id', $dup->id)->count();
            $pry = DB::table('proyectos')->where('cliente_id', $dup->id)->count();

            // Su empresa se vuelve otra razón social (solo si tiene una empresa distinta o datos fiscales)
            $razon = null;
            $tieneEmpresa = $dup->empresa && mb_strtolower(trim($dup->empresa)) !== mb_strtolower(trim((string) $queda->empresa));
            $tieneFiscales = $dup->rfc && $dup->rfc !== $queda->rfc;
            if ($comoRazon && ($tieneEmpresa || $tieneFiscales)) {
                $razon = $queda->razones()->make(array_merge(
                    ['nombre' => $dup->empresa ?: $dup->razon_social ?: $dup->nombre, 'orden' => (int) $queda->razones()->max('orden') + 1],
                    collect(self::FISCALES)->mapWithKeys(fn ($k) => [$k => $dup->{$k}])->all(),
                ));
                $razon->cuenta_id = $queda->cuenta_id;
                $razon->save();
                DB::table('presupuestos')->where('cliente_id', $dup->id)->whereNull('razon_id')->update(['razon_id' => $razon->id]);
            }

            // Sus otras razones sociales también pasan (con sus cotizaciones)
            DB::table('cliente_razones')->where('cliente_id', $dup->id)->update(['cliente_id' => $queda->id]);

            foreach (['presupuestos', 'proyectos', 'correos', 'cliente_mensajes', 'redes_posts', 'tareas', 'prospectos'] as $tabla) {
                DB::table($tabla)->where('cliente_id', $dup->id)->update(['cliente_id' => $queda->id]);
            }
            // Perfiles de redes: solo los de redes que el que se queda no tenga
            $redes = DB::table('redes_perfiles')->where('cliente_id', $queda->id)->pluck('red');
            DB::table('redes_perfiles')->where('cliente_id', $dup->id)->whereNotIn('red', $redes)->update(['cliente_id' => $queda->id]);
            DB::table('redes_codigos')->where('cliente_id', $dup->id)->delete();

            // Constancias: el archivo se mueve a la carpeta del cliente que se queda (la del duplicado se borra)
            foreach ($dup->constancias()->get() as $c) {
                if ($c->origen !== 'dropbox' && Storage::disk('local')->exists($c->ruta)) {
                    $nueva = "clientes/{$queda->id}/constancias/" . basename($c->ruta);
                    Storage::disk('local')->move($c->ruta, $nueva);
                    $c->ruta = $nueva;
                }
                $c->cliente_id = $queda->id;
                $c->saveQuietly();
            }

            // Completa lo que le falte; si su empresa se guardó como razón, sus datos fiscales no se mezclan
            foreach (self::COMPLETAR as $k) {
                if ($razon && (in_array($k, self::FISCALES, true) || $k === 'empresa')) continue;
                if (blank($queda->{$k}) && filled($dup->{$k})) $queda->{$k} = $dup->{$k};
            }
            if (filled($dup->notas)) {
                $queda->notas = trim(($queda->notas ? $queda->notas . "\n\n" : '') . 'De ' . ($dup->empresa ?: $dup->nombre) . ': ' . $dup->notas);
            }
            $dup->refresh();
            $tokenViejo = ! $queda->portal_token ? $dup->portal_token : null;
            $dup->delete();
            // Si el que se queda no tenía enlace propio, hereda el del duplicado (los enlaces ya enviados siguen sirviendo)
            if ($tokenViejo) $queda->portal_token = $tokenViejo;
            $queda->save();

            return ['cotizaciones' => $cot, 'proyectos' => $pry, 'razon' => $razon];
        });
    }
}
