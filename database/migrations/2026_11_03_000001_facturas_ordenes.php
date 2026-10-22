<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La factura (XML) de una orden de compra comprueba el gasto: sus montos pasan a ser el costo real de la orden
 * (si el proveedor cambió el precio o hubo un error al capturarla). Cada factura lleva su folio fiscal para no contarla dos veces.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_compra_archivos', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->index();
            $table->json('cfdi')->nullable();
        });
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->decimal('factura_subtotal', 12, 2)->nullable();
            $table->decimal('factura_iva', 12, 2)->nullable();
            $table->decimal('factura_total', 12, 2)->nullable();
        });

        // Facturas que ya estaban en el servidor: se leen ahora (las de Dropbox se leen al subir una nueva o con "Leer XML")
        foreach (DB::table('orden_compra_archivos')->where('tipo', 'factura')->where('origen', 'local')->where('nombre', 'like', '%.xml')->get() as $a) {
            try {
                $ruta = storage_path('app/' . $a->ruta);
                $d = is_file($ruta) ? \App\Support\Gastos\Cfdi::leer((string) file_get_contents($ruta)) : null;
                if ($d && ! DB::table('orden_compra_archivos')->where('cuenta_id', $a->cuenta_id)->where('uuid', $d['uuid'])->exists()) {
                    DB::table('orden_compra_archivos')->where('id', $a->id)->update(['uuid' => $d['uuid'], 'cfdi' => json_encode($d)]);
                }
            } catch (\Throwable) {}
        }
        foreach (DB::table('orden_compra_archivos')->whereNotNull('uuid')->distinct()->pluck('orden_compra_id') as $id) {
            $fs = DB::table('orden_compra_archivos')->where('orden_compra_id', $id)->whereNotNull('uuid')->pluck('cfdi')->map(fn ($c) => json_decode($c, true));
            DB::table('ordenes_compra')->where('id', $id)->update([
                'factura_subtotal' => round($fs->sum('subtotal'), 2), 'factura_iva' => round($fs->sum('iva'), 2), 'factura_total' => round($fs->sum('total'), 2),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', fn (Blueprint $t) => $t->dropColumn(['factura_subtotal', 'factura_iva', 'factura_total']));
        Schema::table('orden_compra_archivos', function (Blueprint $t) {
            $t->dropIndex(['uuid']);
            $t->dropColumn(['uuid', 'cfdi']);
        });
    }
};
