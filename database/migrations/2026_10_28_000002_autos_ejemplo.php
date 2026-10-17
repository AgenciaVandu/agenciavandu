<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Unidades y clientes de ejemplo para el arranque de un lote de autos: se marcan para quitarlos con un botón */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', fn (Blueprint $t) => $t->boolean('ejemplo')->default(false)->after('publico'));
        Schema::table('clientes', fn (Blueprint $t) => $t->boolean('ejemplo')->default(false));
    }

    public function down(): void
    {
        Schema::table('unidades', fn (Blueprint $t) => $t->dropColumn('ejemplo'));
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('ejemplo'));
    }
};
