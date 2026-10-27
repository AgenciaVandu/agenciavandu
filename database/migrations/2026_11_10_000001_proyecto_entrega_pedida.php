<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La dirección de entrega solo se le muestra al cliente cuando el equipo se la pide.
 * Los proyectos donde el cliente ya la mandó quedan como pedidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', fn (Blueprint $table) => $table->timestamp('entrega_pedida_at')->nullable()->after('entrega_datos_por'));
        DB::table('proyectos')->where('entrega_datos_por', 'cliente')->update(['entrega_pedida_at' => DB::raw('entrega_datos_at')]);
    }

    public function down(): void
    {
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn('entrega_pedida_at'));
    }
};
