<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');               // Persona de contacto: "Jessica Estefani"
            $table->string('empresa')->nullable();  // "Corporativo C&S"
            $table->string('email')->nullable();
            $table->string('telefono', 30)->nullable();
            // Datos fiscales (para la factura)
            $table->string('rfc', 13)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('uso_cfdi', 10)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('nombre');
            $table->index('empresa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
