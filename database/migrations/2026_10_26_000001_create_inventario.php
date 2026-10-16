<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario: catálogo de productos con existencias, sus movimientos (entradas, salidas, ajustes y lo que sale
 * al aceptar una cotización) y el enlace público de cada producto. Los conceptos de la cotización pueden ligarse a un producto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 80);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['cuenta_id', 'nombre']);
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('producto_categorias')->nullOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('clave', 60)->nullable();                  // SKU o código del negocio
            $table->string('nombre', 200);
            $table->string('slug', 220)->unique();                   // enlace público, se genera solo
            $table->text('descripcion')->nullable();
            $table->string('unidad', 30)->default('pieza');
            $table->decimal('precio', 12, 2)->default(0);            // precio de venta antes de IVA
            $table->decimal('costo', 12, 2)->nullable();             // lo que le cuesta al negocio; los vendedores no lo ven
            $table->boolean('controla_existencia')->default(true);   // los servicios no llevan inventario
            $table->decimal('existencia', 12, 2)->default(0);
            $table->decimal('minimo', 12, 2)->nullable();            // avisa cuando queda igual o menos
            $table->string('foto', 255)->nullable();
            $table->boolean('publico')->default(true);               // aparece en el catálogo público
            $table->boolean('mostrar_precio')->nullable();           // null = lo que diga el negocio
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['cuenta_id', 'clave']);
            $table->index(['cuenta_id', 'activo']);
        });

        // Enlaces anteriores: si cambia el nombre, el enlace viejo redirige al nuevo
        Schema::create('producto_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('slug', 220)->unique();
            $table->timestamps();
        });

        Schema::create('producto_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('tipo', 12);                              // entrada | salida | ajuste | venta | devolucion | importacion
            $table->decimal('cantidad', 12, 2);                      // positivo entra, negativo sale
            $table->decimal('existencia', 12, 2);                    // cómo quedó después del movimiento
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nota', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['producto_id', 'id']);
            $table->index(['presupuesto_id']);
        });

        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('presupuesto_id')->constrained('productos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') $table->dropForeign(['producto_id']);
            $table->dropColumn('producto_id');
        });
        Schema::dropIfExists('producto_movimientos');
        Schema::dropIfExists('producto_slugs');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('producto_categorias');
    }
};
