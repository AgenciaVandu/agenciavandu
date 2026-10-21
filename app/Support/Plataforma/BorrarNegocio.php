<?php

namespace App\Support\Plataforma;

use App\Models\Cuenta;
use App\Support\Cuentas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Borra un negocio por completo: su equipo (los correos quedan libres para darlos de alta otra vez),
 * clientes, cotizaciones, proyectos, productos, gimnasio… y sus archivos en el servidor.
 * Lo que tenga en su propio Dropbox no se toca. La cuenta principal no se puede borrar.
 */
class BorrarNegocio
{
    /** @return array<string, int> filas borradas por tabla */
    public static function borrar(Cuenta $c): array
    {
        abort_if($c->id === Cuentas::principalId(), 422, 'La cuenta principal no se puede eliminar.');
        $id = $c->id;
        $carpetas = self::carpetas($id);
        $tablas = self::tablasConCuenta();
        $borradas = [];

        DB::transaction(function () use ($c, $id, $tablas, &$borradas) {
            // Varias vueltas: si una tabla todavía tiene filas que otras referencian, se intenta de nuevo
            // después de borrar las que dependen de ella (las hijas sin cuenta_id se van en cascada).
            $pendientes = $tablas;
            for ($vuelta = 0; $pendientes && $vuelta < 8; $vuelta++) {
                foreach ($pendientes as $i => $t) {
                    try {
                        $n = DB::transaction(fn () => DB::table($t)->where('cuenta_id', $id)->delete());
                        $borradas[$t] = ($borradas[$t] ?? 0) + $n;
                        unset($pendientes[$i]);
                    } catch (\Illuminate\Database\QueryException) {
                        // otra tabla todavía la referencia: siguiente vuelta
                    }
                }
            }
            if ($pendientes) throw new \RuntimeException('No se pudo borrar: ' . implode(', ', $pendientes));
            DB::table('cuentas')->where('id', $c->id)->delete();
        });

        foreach ($carpetas as $dir) Storage::disk('local')->deleteDirectory($dir);
        foreach ((array) $c->ajuste('marca', []) as $k => $ruta) {
            if (is_string($ruta) && str_starts_with($ruta, 'cuentas/')) Storage::disk('local')->delete($ruta);
        }
        \App\Http\Middleware\CuentaPorDominio::olvidar();
        Cuentas::olvidarDominios();
        return $borradas;
    }

    /** Lo que se borrará, para mostrarlo antes de confirmar */
    public static function resumen(Cuenta $c): array
    {
        $n = fn (string $t) => Schema::hasTable($t) ? DB::table($t)->where('cuenta_id', $c->id)->count() : 0;
        return [
            'equipo' => $n('users'), 'correos' => DB::table('users')->where('cuenta_id', $c->id)->pluck('email')->all(),
            'clientes' => $n('clientes'), 'cotizaciones' => $n('presupuestos'), 'proyectos' => $n('proyectos'), 'productos' => $n('productos'),
        ];
    }

    /** Tablas con columna cuenta_id (las del negocio), sin la de cuentas */
    public static function tablasConCuenta(): array
    {
        $driver = DB::getDriverName();
        $todas = match ($driver) {
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))->pluck('name'),
            'pgsql'  => collect(DB::select("SELECT tablename AS name FROM pg_tables WHERE schemaname = current_schema()"))->pluck('name'),
            default  => collect(DB::select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0]),
        };
        return $todas->reject(fn ($t) => $t === 'cuentas' || $t === 'migrations')
            ->filter(fn ($t) => Schema::hasColumn($t, 'cuenta_id'))
            // El equipo al final: muchas tablas lo referencian
            ->sortBy(fn ($t) => $t === 'users' ? 1 : 0)->values()->all();
    }

    /** Carpetas del servidor con archivos del negocio */
    private static function carpetas(int $id): array
    {
        $ids = fn (string $t) => Schema::hasTable($t) ? DB::table($t)->where('cuenta_id', $id)->pluck('id') : collect();
        return array_values(array_unique(array_merge(
            ["cuentas/$id", "productos/$id", "autos/$id", "atletas/$id"],
            $ids('clientes')->map(fn ($x) => "clientes/$x")->all(),
            $ids('clientes')->map(fn ($x) => "redes/$x")->all(),
            $ids('proyectos')->map(fn ($x) => "proyectos/$x")->all(),
            $ids('presupuestos')->map(fn ($x) => "presupuestos/$x")->all(),
            $ids('proveedores')->map(fn ($x) => "proveedores/$x")->all(),
        )));
    }
}
