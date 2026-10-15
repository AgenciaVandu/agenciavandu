<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelar proyectos y órdenes de compra con un motivo. Un proyecto cancelado deja de contar
 * como venta y como dinero por cobrar; una orden cancelada deja de contar como gasto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->timestamp('cancelado_at')->nullable()->after('estado');
            $table->text('motivo_cancelacion')->nullable()->after('cancelado_at');
        });
        Schema::table('ordenes_compra', function (Blueprint $table) {
            $table->timestamp('cancelada_at')->nullable()->after('estado');
            $table->text('motivo_cancelacion')->nullable()->after('cancelada_at');
        });
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->timestamp('cancelada_at')->nullable()->after('estado');
            $table->text('motivo_cancelacion')->nullable()->after('cancelada_at');
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', fn (Blueprint $t) => $t->dropColumn(['cancelado_at', 'motivo_cancelacion']));
        Schema::table('ordenes_compra', fn (Blueprint $t) => $t->dropColumn(['cancelada_at', 'motivo_cancelacion']));
        Schema::table('presupuestos', fn (Blueprint $t) => $t->dropColumn(['cancelada_at', 'motivo_cancelacion']));
    }
};
