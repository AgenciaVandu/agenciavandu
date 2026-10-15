<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de archivos: le pides al cliente lo que necesitas (logos, fotos, audios…)
 * y él los sube desde la página de su proyecto. Lo que sube va a Dropbox (o al servidor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyecto_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('instrucciones')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('estado', 12)->default('abierta');   // abierta | cerrada
            $table->timestamp('recibida_at')->nullable();       // cuándo llegó el último archivo
            $table->timestamps();
        });
        Schema::table('proyecto_archivos', function (Blueprint $table) {
            $table->foreignId('solicitud_id')->nullable()->after('seccion_id')->constrained('proyecto_solicitudes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_archivos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitud_id');
        });
        Schema::dropIfExists('proyecto_solicitudes');
    }
};
