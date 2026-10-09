<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Método de pago: el habitual del cliente y el de cada pago registrado */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('metodo_pago', 30)->nullable()->after('uso_cfdi');
        });
        Schema::table('proyecto_pagos', function (Blueprint $table) {
            $table->string('metodo', 30)->nullable()->after('pagado_el');
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_pagos', fn (Blueprint $table) => $table->dropColumn('metodo'));
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn('metodo_pago'));
    }
};
