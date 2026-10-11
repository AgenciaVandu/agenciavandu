<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Proveedores (impresión, bordado, fotografía…) con órdenes de compra, sus facturas, archivos y comentarios
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('empresa', 160);
            $table->string('nombre', 160)->nullable();       // persona de contacto
            $table->string('rubro', 80)->nullable();         // Impresión, Bordados, Fotografía…
            $table->string('telefono', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('rfc', 20)->nullable();
            $table->string('razon_social', 200)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->text('notas')->nullable();               // solo para el equipo
            $table->string('portal_token', 32)->nullable()->unique();
            $table->timestamp('portal_visto_at')->nullable();
            $table->timestamps();
            $table->index(['cuenta_id', 'empresa']);
        });

        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('folio', 20);
            $table->string('titulo', 160);
            $table->text('indicaciones')->nullable();
            $table->date('fecha')->nullable();
            $table->date('fecha_entrega')->nullable();
            $table->string('lugar_entrega', 200)->nullable();
            $table->boolean('con_iva')->default(true);
            $table->decimal('iva_porcentaje', 5, 2)->default(16);
            $table->string('estado', 12)->default('borrador'); // borrador | enviada | en_proceso | entregada | facturada | pagada | cancelada
            $table->timestamp('enviada_at')->nullable();
            $table->timestamp('vista_at')->nullable();          // la abrió el proveedor
            $table->timestamps();
            $table->unique(['cuenta_id', 'folio']);
        });

        Schema::create('orden_compra_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->string('descripcion', 500);
            $table->decimal('cantidad', 12, 2)->default(1);
            $table->string('unidad', 30)->nullable();
            $table->decimal('precio', 12, 2)->default(0);
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('orden_compra_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->string('tipo', 12);                       // factura | muestra | catalogo | ponchado | diseno | otro
            $table->string('autor', 10);                      // proveedor | agencia
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre', 255);
            $table->string('ruta', 600);
            $table->string('origen', 10)->default('local');   // local | dropbox
            $table->string('dropbox_id', 120)->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('peso')->default(0);
            $table->timestamps();
        });

        Schema::create('orden_compra_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->string('autor', 10);                      // proveedor | agencia
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_comentarios');
        Schema::dropIfExists('orden_compra_archivos');
        Schema::dropIfExists('orden_compra_conceptos');
        Schema::dropIfExists('ordenes_compra');
        Schema::dropIfExists('proveedores');
    }
};
