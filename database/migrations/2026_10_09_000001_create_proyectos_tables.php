<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('presupuesto_id')->nullable()->unique()->constrained('presupuestos')->nullOnDelete();
            $table->string('token', 40)->unique();          // enlace del cliente
            $table->string('tipo', 20);                      // web | audiovisual
            $table->string('nombre');
            $table->string('estado', 12)->default('activo');  // activo | pausado | terminado
            $table->decimal('monto_total', 12, 2)->default(0); // lo que paga el cliente (con IVA si aplica)
            $table->date('fecha_inicio')->nullable();
            $table->text('mensaje_cliente')->nullable();     // nota visible arriba en la vista del cliente
            $table->text('notas_internas')->nullable();
            $table->unsignedInteger('vistas')->default(0);
            $table->timestamp('ultima_vista_at')->nullable();
            $table->timestamps();
        });

        Schema::create('proyecto_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('clave', 40);
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 12)->default('pendiente'); // pendiente | en_curso | completada
            $table->boolean('es_fecha')->default(false);        // se agenda en un día (levantamiento, entrega)
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('proyecto_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('clave', 40);
            $table->string('concepto');
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->decimal('monto', 12, 2)->default(0);
            $table->string('antes_de', 40)->nullable();  // clave de la etapa que depende de este pago
            $table->unsignedSmallInteger('orden')->default(0);
            $table->date('pagado_el')->nullable();
            $table->string('referencia')->nullable();
            $table->timestamps();
        });

        Schema::create('proyecto_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->foreignId('etapa_id')->nullable()->constrained('proyecto_etapas')->nullOnDelete();
            $table->string('grupo', 12)->default('documento'); // documento | galeria
            $table->string('nombre');                          // nombre original para descargar
            $table->string('ruta');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('peso')->default(0);
            $table->string('vista', 255)->nullable();      // versión optimizada (imágenes)
            $table->string('miniatura', 255)->nullable();
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->boolean('visible')->default(true);   // visible para el cliente
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['proyecto_id', 'grupo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proyecto_archivos');
        Schema::dropIfExists('proyecto_pagos');
        Schema::dropIfExists('proyecto_etapas');
        Schema::dropIfExists('proyectos');
    }
};
