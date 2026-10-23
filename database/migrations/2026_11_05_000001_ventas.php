<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venta directa de productos (mostrador o pedido con envío), sin pasar por proyecto.
 * Pago de contado o a crédito con abonos; envío opcional con dirección y ubicación que llena el cliente (por enlace) o el vendedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->string('folio', 20);
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('cliente_nombre', 160)->nullable();     // "Mostrador" si no se captura
            $table->string('cliente_telefono', 30)->nullable();
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();   // si salió de una cotización
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // vendedor
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('subtotal', 12, 2)->default(0);       // productos, antes de descuento e IVA
            $table->decimal('descuento', 12, 2)->default(0);
            $table->boolean('con_iva')->default(true);
            $table->decimal('iva_porcentaje', 5, 2)->default(16);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('envio_costo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('pagado', 12, 2)->default(0);
            $table->boolean('credito')->default(false);
            $table->date('vence_el')->nullable();
            $table->string('estado', 20)->default('entregada');   // por_surtir | listo | en_camino | entregada | cancelada
            $table->text('notas')->nullable();
            // Envío
            $table->boolean('lleva_envio')->default(false);
            $table->string('envio_nombre', 160)->nullable();
            $table->string('envio_telefono', 30)->nullable();
            $table->string('envio_direccion', 300)->nullable();
            $table->string('envio_referencias', 300)->nullable();
            $table->decimal('envio_lat', 10, 7)->nullable();
            $table->decimal('envio_lng', 10, 7)->nullable();
            $table->decimal('envio_km', 8, 1)->nullable();
            $table->boolean('envio_costo_manual')->default(false);
            $table->foreignId('repartidor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token', 40)->unique();                // enlace del cliente: sus datos de envío y su nota
            $table->timestamp('datos_at')->nullable();             // cuándo se llenaron los datos de envío
            $table->string('datos_por', 10)->nullable();           // cliente | vendedor
            $table->timestamp('entregada_at')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamps();
            $table->index(['cuenta_id', 'fecha']);
            $table->unique(['cuenta_id', 'folio']);
        });

        Schema::create('venta_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion', 255);
            $table->string('unidad', 30)->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio', 12, 2);
            $table->decimal('importe', 12, 2);
            $table->unsignedSmallInteger('orden')->default(0);
        });

        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->string('forma', 20)->default('efectivo');      // efectivo | tarjeta | transferencia | otro
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nota', 200)->nullable();
            $table->timestamps();
            $table->index(['cuenta_id', 'fecha']);
        });

        Schema::table('producto_movimientos', function (Blueprint $table) {
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
        });

        // Quien ya vendía con cotizaciones (Vendedor, Almacén, Administración) ahora también puede registrar ventas
        foreach (DB::table('roles')->whereIn('nombre', ['Vendedor', 'Almacén', 'Administración'])->get(['id', 'cuenta_id', 'permisos']) as $r) {
            $cuenta = DB::table('cuentas')->where('id', $r->cuenta_id)->first(['giro', 'ajustes']);
            $modulos = array_merge((array) (config("vandu.giros.{$cuenta?->giro}.modulos") ?? []), (array) (json_decode((string) $cuenta?->ajustes, true)['modulos'] ?? []));
            if (! in_array('stock', $modulos, true)) continue;
            $p = json_decode((string) $r->permisos, true) ?: [];
            if (! in_array('vender', $p, true)) DB::table('roles')->where('id', $r->id)->update(['permisos' => json_encode(array_values(array_merge($p, ['vender'])))]);
        }
    }

    public function down(): void
    {
        Schema::table('producto_movimientos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('venta_id');
        });
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_conceptos');
        Schema::dropIfExists('ventas');
    }
};
