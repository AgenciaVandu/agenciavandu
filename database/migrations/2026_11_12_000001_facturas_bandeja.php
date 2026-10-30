<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bandeja de facturas:
 *  - Facturas de ventas directas (pedidos), igual que las de cotizaciones.
 *  - Lo que se lee del XML (folio fiscal, RFC del cliente, total) y si la factura se subió o se generó en Nerby.
 *  - El cliente puede pedir factura desde su enlace (con qué razón social).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->string('tipo', 20)->default('factura');      // factura | nota
            $table->string('nombre');
            $table->string('ruta');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('peso')->default(0);
            $table->string('uuid', 40)->nullable();              // folio fiscal (del XML)
            $table->string('rfc_receptor', 15)->nullable();
            $table->decimal('total_cfdi', 14, 2)->nullable();
            $table->string('origen', 10)->default('subida');     // subida | nerby
            $table->string('facturapi_id', 40)->nullable();
            $table->timestamp('enviada_at')->nullable();
            $table->timestamps();
        });
        Schema::table('presupuesto_facturas', function (Blueprint $table) {
            $table->string('uuid', 40)->nullable();
            $table->string('rfc_receptor', 15)->nullable();
            $table->decimal('total_cfdi', 14, 2)->nullable();
            $table->string('origen', 10)->default('subida');
            $table->string('facturapi_id', 40)->nullable();
        });
        foreach (['ventas', 'presupuestos'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                $table->timestamp('factura_solicitada_at')->nullable();
                if ($t === 'ventas') $table->foreignId('razon_id')->nullable()->constrained('cliente_razones')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_facturas');
        Schema::table('presupuesto_facturas', fn (Blueprint $t) => $t->dropColumn(['uuid', 'rfc_receptor', 'total_cfdi', 'origen', 'facturapi_id']));
        Schema::table('ventas', function (Blueprint $t) { $t->dropConstrainedForeignId('razon_id'); $t->dropColumn('factura_solicitada_at'); });
        Schema::table('presupuestos', fn (Blueprint $t) => $t->dropColumn('factura_solicitada_at'));
    }
};
