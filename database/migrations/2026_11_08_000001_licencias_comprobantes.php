<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - licencia_comprobantes: el "Ya pagué" del dueño (comprobante de transferencia) que la plataforma confirma o rechaza.
 * - licencia_pagos.iva y cuenta_dinero_id: cada pago de licencia entra a las Finanzas de la cuenta principal
 *   (flujo, contabilidad, IVA y paquete del contador) en la cuenta de dinero de su forma de pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licencia_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('licencia_id')->constrained('licencias')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 10, 2);
            $table->string('referencia', 120)->nullable();
            $table->string('nota', 300)->nullable();
            $table->string('archivo')->nullable();                   // ruta en storage/app
            $table->string('estado', 10)->default('pendiente');      // pendiente | aprobado | rechazado
            $table->string('motivo', 300)->nullable();               // por qué se rechazó
            $table->foreignId('licencia_pago_id')->nullable()->constrained('licencia_pagos')->nullOnDelete();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
        });

        Schema::table('licencia_pagos', function (Blueprint $table) {
            $table->decimal('iva', 10, 2)->default(0)->after('monto');
            $table->foreignId('cuenta_dinero_id')->nullable()->after('referencia')->constrained('cuentas_dinero')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('licencia_pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_dinero_id');
            $table->dropColumn('iva');
        });
        Schema::dropIfExists('licencia_comprobantes');
    }
};
