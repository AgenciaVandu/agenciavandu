<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Las lecturas con IA ya no son solo de tickets: también constancias fiscales y listas de precios (comparten el tope mensual) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gasto_lecturas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('ticket');   // ticket | constancia | lista
        });
    }

    public function down(): void
    {
        Schema::table('gasto_lecturas', fn (Blueprint $t) => $t->dropColumn('tipo'));
    }
};
