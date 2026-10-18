<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('codigo_barras', 80)->nullable();
            $table->unique(['cuenta_id', 'codigo_barras']);
        });
        Schema::create('pos_ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->foreignId('user_id')->constrained('users');
            $table->string('folio', 40);
            $table->uuid('solicitud_id');
            $table->string('estado', 20)->default('pagada');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('impuestos', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->string('forma_pago', 20);
            $table->decimal('recibido', 14, 2)->nullable();
            $table->decimal('cambio', 14, 2)->default(0);
            $table->timestamp('cancelada_at')->nullable();
            $table->foreignId('cancelada_por')->nullable()->constrained('users');
            $table->string('motivo_cancelacion', 255)->nullable();
            $table->timestamps();
            $table->unique(['cuenta_id', 'folio']);
            $table->unique(['cuenta_id', 'solicitud_id']);
        });
        Schema::create('pos_venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas');
            $table->foreignId('pos_venta_id')->constrained('pos_ventas');
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('nombre', 255);
            $table->string('codigo', 80)->nullable();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio', 14, 2);
            $table->decimal('importe', 14, 2);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('pos_venta_detalles');
        Schema::dropIfExists('pos_ventas');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropUnique(['cuenta_id', 'codigo_barras']);
            $table->dropColumn('codigo_barras');
        });
    }
};