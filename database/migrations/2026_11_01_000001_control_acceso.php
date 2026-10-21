<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Control de acceso del gimnasio: torniquetes o puertas con lector (Control iD iDFace en modo online).
 * El equipo le pregunta a Nerby en cada lectura; Nerby decide (reserva de una clase en la ventana de entrada) y lo registra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceso_dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->string('nombre', 80);
            $table->string('token', 40)->unique();                  // va en la dirección que se configura en el equipo
            $table->string('accion', 30)->default('sec_box');       // cómo abre: módulo de accionamiento externo del iDFace
            $table->string('parametros', 120)->default('id=65793,reason=3');
            $table->boolean('reingreso')->default(false);           // dejar volver a entrar a la misma clase
            $table->boolean('credencial_fija')->default(false);     // aceptar la credencial impresa (QR que no cambia)
            $table->boolean('activo')->default(true);
            $table->timestamp('ultimo_contacto_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('dispositivo_id')->nullable()->constrained('acceso_dispositivos')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('reservacion_id')->nullable()->constrained('reservaciones')->nullOnDelete();
            $table->boolean('permitido');
            $table->string('motivo', 30);       // adelante | reingreso | sin_reserva | vencido | desconocido | repetido | inactivo | boton | otro_metodo | prueba
            $table->string('metodo', 12)->default('qr');   // qr | rostro | tarjeta | boton | prueba
            $table->string('mensaje', 160)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cuenta_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos');
        Schema::dropIfExists('acceso_dispositivos');
    }
};
