<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lealtad para restaurantes (giro Restaurante):
 *  - comensales: personas de la plataforma (no de un negocio). Una cuenta y un QR para todos los restaurantes.
 *  - lealtad_inscripciones: el puente; el comensal se une al programa de un restaurante y acepta compartirle sus datos.
 *  - campañas con vigencia y premios que se desbloquean por número de visitas; visitas y canjes por restaurante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comensales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('email', 190)->unique();
            $table->string('telefono', 30)->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('qr_secreto', 64);                 // firma su QR que cambia cada minuto
            $table->string('acceso_hash', 64)->nullable();     // enlace para crear una contraseña nueva
            $table->timestamp('acceso_expira')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
        });

        Schema::create('lealtad_inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('comensal_id')->constrained('comensales')->cascadeOnDelete();
            $table->timestamp('consentido_at');               // aceptó compartir nombre, teléfono y correo con este restaurante
            $table->timestamp('baja_at')->nullable();          // se salió del programa: el restaurante deja de ver sus datos
            $table->string('origen', 12)->default('portal');   // portal | caja
            $table->foreignId('inscrito_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
            $table->unique(['cuenta_id', 'comensal_id']);
        });

        Schema::create('lealtad_campanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->date('inicia_el');
            $table->date('termina_el');
            $table->unsignedSmallInteger('meta_visitas');
            $table->unsignedSmallInteger('dias_canje')->default(15);   // días para canjear después de que termina
            $table->boolean('activa')->default(true);
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
            $table->index(['cuenta_id', 'inicia_el', 'termina_el']);
        });

        Schema::create('lealtad_premios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('campana_id')->constrained('lealtad_campanas')->cascadeOnDelete();
            $table->unsignedSmallInteger('visitas');      // se desbloquea al llegar a estas visitas
            $table->string('nombre', 120);
            $table->string('descripcion', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('lealtad_visitas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('comensal_id')->constrained('comensales')->cascadeOnDelete();
            $table->date('fecha');                           // día del restaurante (una visita por día)
            $table->decimal('monto', 12, 2)->nullable();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
            $table->unique(['cuenta_id', 'comensal_id', 'fecha']);
        });

        Schema::create('lealtad_canjes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('comensal_id')->constrained('comensales')->cascadeOnDelete();
            $table->foreignId('premio_id')->constrained('lealtad_premios')->cascadeOnDelete();
            $table->foreignId('canjeado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
            $table->unique(['premio_id', 'comensal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lealtad_canjes');
        Schema::dropIfExists('lealtad_visitas');
        Schema::dropIfExists('lealtad_premios');
        Schema::dropIfExists('lealtad_campanas');
        Schema::dropIfExists('lealtad_inscripciones');
        Schema::dropIfExists('comensales');
    }
};
