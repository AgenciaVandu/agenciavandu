<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de servicio en la cotización y costeo interno por concepto
 * (precio del proveedor, gasolina y utilidad). El cliente nunca lo ve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->string('tipo', 30)->nullable()->after('titulo');
        });

        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->decimal('costo_proveedor', 12, 2)->nullable()->after('precio');
            $table->decimal('gasolina', 12, 2)->nullable()->after('costo_proveedor');
            $table->decimal('utilidad', 12, 2)->nullable()->after('gasolina');
            $table->string('utilidad_modo', 10)->default('pct')->after('utilidad');
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_conceptos', fn (Blueprint $table) => $table->dropColumn(['costo_proveedor', 'gasolina', 'utilidad', 'utilidad_modo']));
        Schema::table('presupuestos', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
