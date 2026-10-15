<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Razones sociales del cliente: además de su empresa principal (los datos fiscales del cliente),
 * puede tener otras. Cada cotización (y su proyecto) es de una de ellas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_razones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nombre');                       // como se le conoce: "Hotel X Playa"
            $table->string('razon_social')->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('regimen_fiscal', 5)->nullable();
            $table->string('cp_fiscal', 5)->nullable();
            $table->string('uso_cfdi', 5)->nullable();
            $table->string('email_factura')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->foreignId('razon_id')->nullable()->after('cliente_id')->constrained('cliente_razones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', fn (Blueprint $table) => $table->dropConstrainedForeignId('razon_id'));
        Schema::dropIfExists('cliente_razones');
    }
};
