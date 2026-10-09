<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos de facturación completos y expediente de Constancias de Situación Fiscal por cliente */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('regimen_fiscal', 10)->nullable()->after('razon_social');
            $table->string('cp_fiscal', 10)->nullable()->after('regimen_fiscal');
            $table->string('email_factura')->nullable()->after('uso_cfdi');
        });

        Schema::create('cliente_constancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained()->cascadeOnDelete();
            $table->string('nombre');           // nombre original del archivo
            $table->string('ruta');             // storage/app privado
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('peso')->default(0);
            $table->date('emitida_el')->nullable(); // fecha de emisión de la constancia (opcional)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_constancias');
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn(['regimen_fiscal', 'cp_fiscal', 'email_factura']));
    }
};
