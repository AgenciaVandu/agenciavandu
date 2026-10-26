<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dónde está el dinero: caja, banco o terminal. Cada cobro y cada pago dice a qué cuenta entró o de cuál salió
 * (se elige solo por la forma de pago) y así el panel sabe cuánto debería haber en cada una.
 *  - movimientos_dinero: traspasos entre cuentas (depositar la caja al banco), retiros del dueño, aportaciones y ajustes.
 *  - conciliaciones: cada vez que alguien compara el saldo del banco con el del panel.
 *  - ordenes_compra.pagada_el: la fecha en que se le pagó al proveedor (para el flujo de efectivo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_dinero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 80);
            $table->string('tipo', 12);                              // efectivo | banco | terminal | otro
            $table->string('banco', 60)->nullable();
            $table->json('formas')->nullable();                      // formas de pago que caen aquí sin elegir
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->date('saldo_desde');                             // lo anterior a esta fecha no cuenta
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('movimientos_dinero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('tipo', 12);                              // traspaso | retiro | aportacion | ajuste
            $table->foreignId('de_id')->nullable()->constrained('cuentas_dinero')->cascadeOnDelete();
            $table->foreignId('a_id')->nullable()->constrained('cuentas_dinero')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->string('nota', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cuenta_id', 'fecha']);
        });

        Schema::create('conciliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('cuenta_dinero_id')->constrained('cuentas_dinero')->cascadeOnDelete();
            $table->date('fecha');
            $table->decimal('saldo_real', 12, 2);
            $table->decimal('saldo_panel', 12, 2);
            $table->decimal('diferencia', 12, 2);
            $table->boolean('ajustada')->default(false);
            $table->string('nota', 200)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (['proyecto_pagos', 'venta_pagos', 'gastos', 'ordenes_compra'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->foreignId('cuenta_dinero_id')->nullable()->constrained('cuentas_dinero')->nullOnDelete());
        }
        Schema::table('ordenes_compra', fn (Blueprint $table) => $table->date('pagada_el')->nullable());
        // Las que ya estaban pagadas: la última vez que cambiaron
        DB::table('ordenes_compra')->where('estado', 'pagada')->orderBy('id')->each(function ($o) {
            DB::table('ordenes_compra')->where('id', $o->id)->update(['pagada_el' => substr((string) $o->updated_at, 0, 10)]);
        });
    }

    public function down(): void
    {
        foreach (['proyecto_pagos', 'venta_pagos', 'gastos', 'ordenes_compra'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->dropConstrainedForeignId('cuenta_dinero_id'));
        }
        Schema::table('ordenes_compra', fn (Blueprint $table) => $table->dropColumn('pagada_el'));
        Schema::dropIfExists('conciliaciones');
        Schema::dropIfExists('movimientos_dinero');
        Schema::dropIfExists('cuentas_dinero');
    }
};
