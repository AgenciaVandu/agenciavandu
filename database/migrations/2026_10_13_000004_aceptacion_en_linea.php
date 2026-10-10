<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aceptación en línea: códigos de verificación (24 h) y el historial de cada cotización
 * (enviada, editada, cambios solicitados, aceptada…) para tener trazabilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->string('codigo_hash');
            $table->string('canal', 20);            // correo | whatsapp | manual | cliente (lo pidió desde la página)
            $table->timestamp('expira_at');
            $table->timestamp('usado_at')->nullable();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamps();
            $table->index(['presupuesto_id', 'expira_at']);
        });

        Schema::create('presupuesto_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('actor', 10);            // cliente | agencia | sistema
            $table->string('autor')->nullable();    // quién (nombre del usuario o del cliente)
            $table->text('detalle')->nullable();
            $table->json('datos')->nullable();
            $table->boolean('visible_cliente')->default(true);
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['presupuesto_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_eventos');
        Schema::dropIfExists('presupuesto_codigos');
    }
};
