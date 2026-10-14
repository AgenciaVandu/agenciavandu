<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complementos de pago (REP) para facturas PPD: se suben junto a la factura de la cotización,
 * cada uno ligado al pago que ampara, y se envían por correo igual que la factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_facturas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('factura')->after('presupuesto_id');      // factura | complemento
            $table->string('lote', 40)->nullable()->after('tipo');                          // los archivos que se subieron juntos (PDF + XML)
            $table->foreignId('pago_id')->nullable()->after('lote')->constrained('proyecto_pagos')->nullOnDelete();
            $table->date('fecha_pago')->nullable()->after('pago_id');
            $table->index(['presupuesto_id', 'tipo']);
        });
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->string('metodo_sat', 3)->nullable()->after('modo_iva');                  // PUE | PPD
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_facturas', function (Blueprint $table) {
            $table->dropIndex(['presupuesto_id', 'tipo']);
            $table->dropConstrainedForeignId('pago_id');
            $table->dropColumn(['tipo', 'lote', 'fecha_pago']);
        });
        Schema::table('presupuestos', fn (Blueprint $table) => $table->dropColumn('metodo_sat'));
    }
};
