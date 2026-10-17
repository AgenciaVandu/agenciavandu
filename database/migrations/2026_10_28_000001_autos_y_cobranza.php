<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lotes de autos: sucursales, unidades con fotos, ventas (contado, crédito del lote o financiera)
 * y la cobranza del crédito: letras con su capital e interés y los pagos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('marca', 60);
            $table->string('modelo', 80);
            $table->string('version', 80)->nullable();
            $table->unsignedSmallInteger('anio')->nullable();
            $table->string('tipo', 30)->nullable();              // sedán, SUV, pickup…
            $table->string('color', 40)->nullable();
            $table->unsignedInteger('kilometraje')->nullable();
            $table->string('transmision', 20)->nullable();
            $table->string('combustible', 20)->nullable();
            $table->string('placas', 20)->nullable();
            $table->string('serie', 40)->nullable();             // VIN
            $table->decimal('precio', 12, 2)->default(0);
            $table->decimal('costo', 12, 2)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('ficha')->nullable();                   // equipamiento, una línea por dato
            $table->string('estado', 12)->default('disponible'); // disponible | apartado | vendido
            $table->string('slug', 220)->unique();
            $table->boolean('publico')->default(true);
            $table->timestamps();
            $table->index(['cuenta_id', 'estado']);
        });

        Schema::create('unidad_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('archivo', 255);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('creditos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('tipo', 12);                          // contado | credito | financiera
            $table->date('fecha');
            $table->decimal('precio', 12, 2);
            $table->decimal('enganche', 12, 2)->default(0);
            $table->decimal('financiado', 12, 2)->default(0);
            $table->decimal('tasa', 6, 3)->default(0);           // % mensual sobre saldo
            $table->unsignedSmallInteger('plazo')->default(0);   // meses
            $table->decimal('mensualidad', 12, 2)->default(0);
            $table->string('moratorio_modo', 12)->default('porcentaje'); // dia | porcentaje
            $table->decimal('moratorio_valor', 10, 2)->default(0);
            $table->unsignedSmallInteger('dias_gracia')->default(0);
            $table->decimal('descuento_adelanto', 10, 2)->default(0);
            $table->string('financiera', 120)->nullable();
            $table->string('estado', 12)->default('activo');     // activo | liquidado | cancelado
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['cuenta_id', 'estado']);
        });

        Schema::create('credito_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('metodo', 30)->nullable();
            $table->string('nota', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('credito_letras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->date('vence_el');
            $table->decimal('capital', 12, 2);
            $table->decimal('interes', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->foreignId('pago_id')->nullable()->constrained('credito_pagos')->nullOnDelete();
            $table->date('pagada_el')->nullable();
            $table->decimal('pagado', 12, 2)->nullable();        // lo que se cobró por esta letra
            $table->decimal('moratorio', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->boolean('adelantada')->default(false);
            $table->unique(['credito_id', 'numero']);
            $table->index(['credito_id', 'pagada_el']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->nullable()->after('cuenta_id')->constrained('sucursales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') $table->dropForeign(['sucursal_id']);
            $table->dropColumn('sucursal_id');
        });
        Schema::dropIfExists('credito_letras');
        Schema::dropIfExists('credito_pagos');
        Schema::dropIfExists('creditos');
        Schema::dropIfExists('unidad_fotos');
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('sucursales');
    }
};
