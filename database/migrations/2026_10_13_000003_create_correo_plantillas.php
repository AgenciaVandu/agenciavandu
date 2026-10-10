<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plantillas de correo editables desde el panel.
 * Las de fábrica viven en config/vandu.php; aquí se guardan tus cambios y las plantillas que crees.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correo_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60)->unique();
            $table->boolean('propia')->default(false); // creada por ti (no de fábrica)
            $table->string('nombre', 60);
            $table->string('icono', 40)->nullable();
            $table->json('para')->nullable();          // cliente / presupuesto / proyecto (solo las propias)
            $table->string('asunto', 200)->nullable();
            $table->string('titulo', 120)->nullable();
            $table->text('cuerpo')->nullable();
            $table->string('boton', 60)->nullable();
            $table->boolean('resumen')->default(false); // solo las propias
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_plantillas');
    }
};
