<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Órdenes de compra: cómo se pagó (efectivo, tarjeta, transferencia u otro), para descontarlo de la cuenta correcta.
 * Las que ya estaban pagadas se quedan como transferencia, que es como se registraban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_compra', fn (Blueprint $table) => $table->string('forma_pago', 20)->nullable()->after('pagada_el'));
        DB::table('ordenes_compra')->whereNotNull('pagada_el')->update(['forma_pago' => 'transferencia']);
    }

    public function down(): void
    {
        Schema::table('ordenes_compra', fn (Blueprint $table) => $table->dropColumn('forma_pago'));
    }
};
