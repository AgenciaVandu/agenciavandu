<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forma de pago del proyecto: contado (anticipo y saldo) o crédito (pago diferido a N días).
 * Los pagos a crédito tienen fecha de vencimiento y no frenan ninguna etapa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->string('forma_pago', 20)->default('contado')->after('monto_total');
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('forma_pago');
        });
        Schema::table('proyecto_pagos', function (Blueprint $table) {
            $table->date('vence_el')->nullable()->after('antes_de');
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias_credito')->nullable()->after('metodo_pago');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('dias_credito'));
        Schema::table('proyecto_pagos', fn (Blueprint $table) => $table->dropColumn('vence_el'));
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn(['forma_pago', 'dias_credito']));
    }
};
