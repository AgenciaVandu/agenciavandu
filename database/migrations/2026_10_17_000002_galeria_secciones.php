<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// La galería de un proyecto se divide en secciones (una por entrega): no se revuelve lo de cada vez
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galeria_secciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->string('carpeta', 120);                 // subcarpeta dentro de Galería (y No publicado) en Dropbox
            $table->unsignedInteger('orden')->default(0);    // la de mayor orden se muestra primero
            $table->string('zip_url', 500)->nullable();
            $table->timestamps();
            $table->index(['proyecto_id', 'orden']);
        });
        Schema::table('proyecto_archivos', function (Blueprint $table) {
            $table->foreignId('seccion_id')->nullable()->after('etapa_id')->constrained('galeria_secciones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_archivos', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') $table->dropForeign(['seccion_id']);
        });
        Schema::table('proyecto_archivos', fn (Blueprint $table) => $table->dropColumn('seccion_id'));
        Schema::dropIfExists('galeria_secciones');
    }
};
