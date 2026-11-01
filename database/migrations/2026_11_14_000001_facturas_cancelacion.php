<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cancelación de facturas timbradas en Nerby: pendiente (espera que el cliente acepte) o cancelada */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['venta_facturas', 'presupuesto_facturas'] as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('cancelacion', 20)->nullable()->after('facturapi_id');
                $table->timestamp('cancelada_at')->nullable()->after('cancelacion');
            });
        }
    }

    public function down(): void
    {
        foreach (['venta_facturas', 'presupuesto_facturas'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn(['cancelacion', 'cancelada_at']));
        }
    }
};
