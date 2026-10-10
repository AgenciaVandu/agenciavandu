<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contactos que llegan del formulario del sitio: se guardan como clientes marcados "Nuevo" */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('origen', 20)->default('manual')->after('notas'); // manual | sitio
            $table->boolean('nuevo')->default(false)->after('origen');
            $table->string('interes')->nullable()->after('nuevo');
            $table->timestamp('contacto_at')->nullable()->after('interes');
            $table->index('nuevo');
        });

        Schema::create('cliente_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('servicio')->nullable();
            $table->json('datos')->nullable(); // lo que mandó tal cual: nombre, teléfono, correo…
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_mensajes');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex(['nuevo']);
            $table->dropColumn(['origen', 'nuevo', 'interes', 'contacto_at']);
        });
    }
};
