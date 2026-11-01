<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Información SAT de cada producto: descripción de sus claves y si es objeto de impuesto */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->string('clave_sat_texto', 200)->nullable();
            $t->string('unidad_sat_texto', 80)->nullable();
            $t->char('objeto_imp', 2)->nullable();
        });
        Schema::table('venta_conceptos', function (Blueprint $t) {
            $t->char('objeto_imp', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['clave_sat_texto', 'unidad_sat_texto', 'objeto_imp']));
        Schema::table('venta_conceptos', fn (Blueprint $t) => $t->dropColumn('objeto_imp'));
    }
};
