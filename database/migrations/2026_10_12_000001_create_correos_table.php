<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Historial de correos enviados desde el panel */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('plantilla', 40);
            $table->string('para');
            $table->string('cc')->nullable();
            $table->string('asunto');
            $table->text('cuerpo');
            $table->json('adjuntos')->nullable();
            $table->string('estado', 20)->default('enviado'); // enviado | fallido
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correos');
    }
};
