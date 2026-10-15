<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Comprobante de cada cotización: factura (CFDI) o nota de venta para quien paga sin factura */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->string('comprobante', 10)->default('factura')->after('metodo_sat');   // factura | nota
            $table->string('nota_folio', 20)->nullable()->after('comprobante');           // NV-0001
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', fn (Blueprint $table) => $table->dropColumn(['comprobante', 'nota_folio']));
    }
};
