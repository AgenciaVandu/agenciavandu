<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * "Presupuestos" = las cotizaciones formales que se envían a clientes.
 * (La tabla "cotizacions" que ya existe son los prospectos del formulario web.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('folio', 20)->unique();
            $table->string('token', 40)->unique();      // enlace público para el cliente

            // Encabezado (copia de los datos del cliente al crear; editable)
            $table->string('cliente_nombre');
            $table->string('cliente_empresa')->nullable();
            $table->date('fecha');
            $table->string('titulo')->default('Presupuesto de servicios');

            // Emisor (el bloque negro del encabezado)
            $table->string('emisor_nombre');
            $table->string('emisor_telefono')->nullable();
            $table->string('emisor_sitio')->nullable();
            $table->string('emisor_email')->nullable();

            // Importes
            $table->string('modo_iva', 12)->default('mas_iva'); // mas_iva | desglosado | sin_iva
            $table->decimal('iva_porcentaje', 5, 2)->default(16);

            // Consideraciones: [{titulo, items: [texto, ...]}, ...]
            $table->json('consideraciones')->nullable();

            // Pago
            $table->boolean('mostrar_pago')->default(true);
            $table->text('pago_intro')->nullable();
            $table->string('banco')->nullable();
            $table->string('clabe', 30)->nullable();
            $table->string('beneficiario')->nullable();
            $table->text('nota_comprobante')->nullable();
            $table->text('nota_factura')->nullable();

            // Vigencia y seguimiento
            $table->dateTime('vigente_hasta');
            $table->string('estado', 12)->default('borrador'); // borrador | enviada | aceptada | rechazada
            $table->unsignedInteger('vistas')->default(0);
            $table->timestamp('ultima_vista_at')->nullable();
            $table->text('notas_internas')->nullable();

            $table->timestamps();

            $table->index(['estado', 'vigente_hasta']);
        });

        Schema::create('presupuesto_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->text('descripcion');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio', 12, 2)->default(0);  // precio unitario
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuesto_conceptos');
        Schema::dropIfExists('presupuestos');
    }
};
