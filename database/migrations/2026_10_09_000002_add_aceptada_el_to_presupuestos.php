<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->date('aceptada_el')->nullable()->after('estado');
        });

        // Cotizaciones que ya estaban aceptadas: se toma su última edición como fecha
        \Illuminate\Support\Facades\DB::table('presupuestos')->where('estado', 'aceptada')->whereNull('aceptada_el')
            ->update(['aceptada_el' => \Illuminate\Support\Facades\DB::raw('DATE(updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('presupuestos', fn (Blueprint $table) => $table->dropColumn('aceptada_el'));
    }
};
