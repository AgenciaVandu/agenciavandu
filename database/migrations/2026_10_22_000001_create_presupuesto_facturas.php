<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Facturas (PDF y XML) de cada cotización: se suben en el panel, el cliente las descarga y se envían por correo */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuesto_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('ruta');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('peso')->default(0);
            $table->timestamp('enviada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_facturas');
    }
};
