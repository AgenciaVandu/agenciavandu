<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de proyecto editables por la agencia: etapas y pagos propios.
 * Los de fábrica viven en config/vandu.php; aquí se guardan los cambios y los tipos nuevos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->boolean('propio')->default(false);
            $table->string('nombre', 80);
            $table->string('icono', 40)->nullable();
            $table->json('etapas');
            $table->json('pagos');
            $table->json('opciones')->nullable(); // galeria, costeo, redes (solo los propios)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_tipos');
    }
};
