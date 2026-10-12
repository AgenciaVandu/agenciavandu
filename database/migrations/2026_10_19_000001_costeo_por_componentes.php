<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Costeo por componentes configurables (cada giro costea distinto).
 * Cada concepto guarda la lista de costos que usó: [{clave, nombre, modo, clase, monto}].
 * El costeo anterior (proveedor por pieza + gasolina) pasa a esta lista; las columnas viejas se quedan como respaldo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->json('costos')->nullable()->after('precio');
        });

        DB::table('presupuesto_conceptos')
            ->where(fn ($q) => $q->whereNotNull('costo_proveedor')->orWhereNotNull('gasolina'))
            ->orderBy('id')
            ->chunkById(200, function ($filas) {
                foreach ($filas as $f) {
                    $costos = [];
                    if ($f->costo_proveedor !== null) {
                        $costos[] = ['clave' => 'proveedor', 'nombre' => 'Proveedor o maquila', 'modo' => 'unidad', 'clase' => 'tercero', 'monto' => (float) $f->costo_proveedor];
                    }
                    if ($f->gasolina !== null) {
                        $costos[] = ['clave' => 'gasolina', 'nombre' => 'Gasolina y traslados', 'modo' => 'fijo', 'clase' => 'operativo', 'monto' => (float) $f->gasolina];
                    }
                    DB::table('presupuesto_conceptos')->where('id', $f->id)->update(['costos' => json_encode($costos, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('presupuesto_conceptos', fn (Blueprint $table) => $table->dropColumn('costos'));
    }
};
