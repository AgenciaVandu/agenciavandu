<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gastos que no salen de una orden de compra: tickets (foto leída con IA), facturas (XML del SAT) o captura a mano.
 * Módulo opcional que la plataforma prende por negocio. Alimenta Finanzas y la rentabilidad real de cada proyecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();         // quién lo registró
            $table->date('fecha');
            $table->string('concepto', 160);
            $table->string('categoria', 30);
            $table->string('proveedor', 160)->nullable();
            $table->string('rfc', 13)->nullable();                     // del emisor
            $table->decimal('subtotal', 12, 2);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('forma_pago', 20)->nullable();              // efectivo | tarjeta | transferencia | otro
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('origen', 10)->default('manual');           // manual | foto | xml
            $table->string('foto')->nullable();                        // ruta en storage/app
            $table->string('xml')->nullable();
            $table->string('uuid', 36)->nullable();                    // folio fiscal: la misma factura no entra dos veces
            $table->text('notas')->nullable();
            $table->json('lectura')->nullable();                       // lo que leyó la IA (para revisar diferencias)
            $table->timestamps();
            $table->index(['cuenta_id', 'fecha']);
            $table->unique(['cuenta_id', 'uuid']);
        });

        // Cada lectura de ticket con IA (para el tope mensual y saber cuánto se usa)
        Schema::create('gasto_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ok');
            $table->unsignedInteger('tokens_entrada')->default(0);
            $table->unsignedInteger('tokens_salida')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cuenta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_lecturas');
        Schema::dropIfExists('gastos');
    }
};
