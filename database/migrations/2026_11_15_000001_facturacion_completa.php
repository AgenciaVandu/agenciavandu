<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación completa: claves SAT en productos y conceptos, series con folio propio, complementos de pago,
 * notas de crédito, factura global y timbres (paquetes que vende la plataforma y su consumo por negocio).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->string('clave_sat', 8)->nullable();
            $t->string('unidad_sat', 3)->nullable();
        });
        Schema::table('venta_conceptos', function (Blueprint $t) {
            $t->string('clave_sat', 8)->nullable();
            $t->string('unidad_sat', 3)->nullable();
        });
        Schema::table('ventas', function (Blueprint $t) {
            $t->string('metodo_pago', 3)->nullable();   // PUE | PPD (al timbrar)
            $t->string('uso_cfdi', 4)->nullable();
        });

        Schema::create('factura_series', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $t->string('serie', 10);
            $t->char('tipo', 1)->default('I');            // I ingreso · E nota de crédito · P complemento de pago · G factura global
            $t->string('descripcion', 120)->nullable();
            $t->unsignedInteger('siguiente')->default(1);
            $t->boolean('predeterminada')->default(false);
            $t->boolean('activa')->default(true);
            $t->timestamps();
            $t->unique(['cuenta_id', 'serie']);
        });

        Schema::create('facturas_globales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $t->string('serie', 10)->nullable();
            $t->unsignedInteger('folio')->nullable();
            $t->string('periodicidad', 12);               // day | week | fortnight | month | two_months
            $t->string('meses', 2);
            $t->unsignedSmallInteger('anio');
            $t->date('desde');
            $t->date('hasta');
            $t->unsignedInteger('ventas')->default(0);
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('iva', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            $t->string('facturapi_id', 60)->nullable();
            $t->string('uuid', 36)->nullable();
            $t->string('pdf', 300)->nullable();
            $t->string('xml', 300)->nullable();
            $t->string('cancelacion', 20)->nullable();
            $t->timestamp('cancelada_at')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::table('ventas', function (Blueprint $t) {
            $t->foreignId('global_id')->nullable()->constrained('facturas_globales')->nullOnDelete();
        });

        Schema::table('venta_facturas', function (Blueprint $t) {
            $t->string('serie', 10)->nullable();
            $t->unsignedInteger('folio')->nullable();
            $t->foreignId('venta_pago_id')->nullable()->constrained('venta_pagos')->nullOnDelete();
            $t->decimal('monto', 12, 2)->nullable();       // complemento: lo pagado · nota de crédito: su total
            $t->string('motivo', 160)->nullable();
        });
        Schema::table('presupuesto_facturas', function (Blueprint $t) {
            $t->string('serie', 10)->nullable();
            $t->unsignedInteger('folio')->nullable();
        });

        // Timbres: paquetes que vende la plataforma y los movimientos de cada negocio
        Schema::create('timbre_paquetes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 80);
            $t->unsignedInteger('timbres');
            $t->decimal('precio', 10, 2);
            $t->boolean('activo')->default(true);
            $t->unsignedSmallInteger('orden')->default(0);
            $t->timestamps();
        });
        Schema::create('timbre_movimientos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $t->string('tipo', 12);                        // compra | regalo | ajuste | uso | uso_plan
            $t->integer('cantidad');                       // + entra, − sale (uso_plan = 0: lo cubrió el plan del mes)
            $t->foreignId('paquete_id')->nullable()->constrained('timbre_paquetes')->nullOnDelete();
            $t->foreignId('licencia_pago_id')->nullable()->constrained('licencia_pagos')->nullOnDelete();
            $t->decimal('monto', 10, 2)->nullable();
            $t->string('referencia', 120)->nullable();
            $t->string('nota', 200)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['cuenta_id', 'tipo', 'created_at']);
        });
        Schema::table('planes', function (Blueprint $t) {
            $t->unsignedInteger('timbres_mes')->default(0);
        });
        Schema::table('licencia_comprobantes', function (Blueprint $t) {
            $t->foreignId('paquete_id')->nullable()->constrained('timbre_paquetes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('licencia_comprobantes', fn (Blueprint $t) => $t->dropConstrainedForeignId('paquete_id'));
        Schema::table('planes', fn (Blueprint $t) => $t->dropColumn('timbres_mes'));
        Schema::dropIfExists('timbre_movimientos');
        Schema::dropIfExists('timbre_paquetes');
        Schema::table('presupuesto_facturas', fn (Blueprint $t) => $t->dropColumn(['serie', 'folio']));
        Schema::table('venta_facturas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('venta_pago_id');
            $t->dropColumn(['serie', 'folio', 'monto', 'motivo']);
        });
        Schema::table('ventas', fn (Blueprint $t) => $t->dropConstrainedForeignId('global_id'));
        Schema::dropIfExists('facturas_globales');
        Schema::dropIfExists('factura_series');
        Schema::table('ventas', fn (Blueprint $t) => $t->dropColumn(['metodo_pago', 'uso_cfdi']));
        Schema::table('venta_conceptos', fn (Blueprint $t) => $t->dropColumn(['clave_sat', 'unidad_sat']));
        Schema::table('productos', fn (Blueprint $t) => $t->dropColumn(['clave_sat', 'unidad_sat']));
    }
};
