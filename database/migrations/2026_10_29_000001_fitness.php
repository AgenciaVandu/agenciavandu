<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Giro Fitness (gimnasio / HYROX):
 *  - El atleta es un Cliente: se le agregan foto, estado, contraseña para su portal y su código QR de entrada.
 *  - Los paquetes de clases son Productos (tipo "paquete") con créditos y vigencia.
 *  - Tipos de clase, ejercicios, clases (sueltas o de una serie que se repite), membresías y reservaciones.
 *  - Los avisos push también pueden ir a un atleta (push_suscripciones.cliente_id).
 *  - Cada negocio tiene un nombre corto (slug) para su portal sin dominio propio: /atleta/{slug}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->string('slug', 80)->nullable()->unique()->after('nombre');
        });
        foreach (DB::table('cuentas')->orderBy('id')->get(['id', 'nombre']) as $c) {
            $base = Str::slug($c->nombre) ?: 'negocio';
            $slug = $base;
            for ($i = 2; DB::table('cuentas')->where('slug', $slug)->exists(); $i++) $slug = "$base-$i";
            DB::table('cuentas')->where('id', $c->id)->update(['slug' => $slug]);
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('foto')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('password')->nullable();               // portal del atleta
            $table->rememberToken();
            $table->string('acceso_hash', 64)->nullable();        // enlace para crear su contraseña
            $table->timestamp('acceso_expira')->nullable();
            $table->timestamp('ultimo_acceso_at')->nullable();
            $table->string('qr_codigo', 40)->nullable()->unique(); // credencial de entrada
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->string('tipo', 12)->default('producto')->after('categoria_id'); // producto | paquete
            $table->unsignedInteger('creditos')->nullable();      // paquete: clases incluidas (vacío = ilimitado)
            $table->unsignedInteger('vigencia_dias')->nullable(); // paquete: días desde que se asigna
            $table->boolean('es_prueba')->default(false);         // clase de prueba: una vez por atleta
            $table->unsignedTinyInteger('max_por_dia')->nullable(); // cuántas clases al día como máximo
            $table->json('tipos_clase')->nullable();              // tipos de clase permitidos (vacío = todas)
            $table->index(['cuenta_id', 'tipo']);
        });

        Schema::create('clase_tipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->unsignedSmallInteger('duracion')->default(60);   // minutos
            $table->unsignedSmallInteger('cupo')->default(12);       // cupo que se propone al programar
            $table->string('color', 7)->default('#13161D');
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
        });

        Schema::create('ejercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('categoria', 60)->nullable();   // Cardio, Fuerza, Estación HYROX…
            $table->string('unidad', 20)->default('reps'); // m, cal, reps, kg, seg
            $table->text('instrucciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
        });

        Schema::create('clase_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('clase_tipo_id')->constrained('clase_tipos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('dias');                         // 1 = lunes … 7 = domingo
            $table->string('hora', 5);                    // 07:00
            $table->unsignedSmallInteger('duracion');
            $table->unsignedSmallInteger('cupo');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('clases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('clase_tipo_id')->constrained('clase_tipos')->cascadeOnDelete();
            $table->foreignId('serie_id')->nullable()->constrained('clase_series')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('inicia_at');                 // UTC
            $table->dateTime('termina_at');
            $table->unsignedSmallInteger('cupo');          // lo pone el gerente
            $table->string('estado', 12)->default('programada'); // programada | cancelada
            $table->text('motivo_cancelacion')->nullable();
            $table->json('entrenamiento')->nullable();     // bloques con sus ejercicios
            $table->text('notas')->nullable();
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
            $table->index(['cuenta_id', 'inicia_at']);
            $table->unique(['serie_id', 'inicia_at']);
        });

        Schema::create('membresias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('nombre', 200);                 // como se llamaba el paquete al asignarlo
            $table->decimal('precio', 12, 2)->default(0);  // lo que se le vendió: cuenta en Finanzas
            $table->unsignedInteger('creditos')->nullable(); // vacío = ilimitado
            $table->unsignedInteger('usados')->default(0);
            $table->boolean('es_prueba')->default(false);
            $table->unsignedTinyInteger('max_por_dia')->nullable();
            $table->json('tipos_clase')->nullable();
            $table->date('inicia_el');
            $table->date('vence_el');
            $table->string('estado', 12)->default('activa'); // activa | cancelada
            $table->date('pagado_el')->nullable();          // vacío = pendiente de pago
            $table->string('metodo_pago', 20)->nullable();  // efectivo | transferencia | tarjeta
            $table->text('notas')->nullable();
            $table->foreignId('asignada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aviso_credito_at')->nullable();
            $table->timestamp('aviso_vence_at')->nullable();
            $table->boolean('ejemplo')->default(false);
            $table->timestamps();
            $table->index(['cuenta_id', 'cliente_id', 'estado']);
            $table->index(['cuenta_id', 'vence_el']);
        });

        Schema::create('reservaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('clase_id')->constrained('clases')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('membresia_id')->nullable()->constrained('membresias')->nullOnDelete();
            $table->string('estado', 12)->default('reservada'); // reservada | cancelada | asistio | falta
            $table->string('origen', 12)->default('panel');     // panel | atleta | escaner
            $table->boolean('credito_usado')->default(false);   // si descontó crédito (ilimitada no)
            $table->boolean('credito_devuelto')->default(false);
            $table->timestamp('checkin_at')->nullable();
            $table->foreignId('checkin_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelada_at')->nullable();
            $table->timestamp('aviso_at')->nullable();          // recordatorio "tu clase empieza en…"
            $table->foreignId('reservada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['clase_id', 'cliente_id']);
            $table->index(['cuenta_id', 'cliente_id', 'estado']);
        });

        Schema::table('push_suscripciones', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('user_id')->constrained('clientes')->cascadeOnDelete();
        });
        // El aviso puede ser para alguien del equipo o para un atleta
        $this->usuarioOpcional();
    }

    private function usuarioOpcional(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE push_suscripciones MODIFY user_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE push_suscripciones ALTER COLUMN user_id DROP NOT NULL');
        } else {
            // SQLite: se reescribe la definición guardada de la tabla
            $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'push_suscripciones'")->sql;
            $nuevo = preg_replace('/("user_id" integer) not null/i', '$1', $sql, 1);
            if ($nuevo !== $sql) {
                $version = DB::selectOne('PRAGMA schema_version')->schema_version;
                DB::statement('PRAGMA writable_schema = ON');
                DB::update("UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'push_suscripciones'", [$nuevo]);
                DB::statement('PRAGMA schema_version = ' . ((int) $version + 1));
                DB::statement('PRAGMA writable_schema = OFF');
            }
        }
    }

    public function down(): void
    {
        Schema::table('push_suscripciones', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') $table->dropForeign(['cliente_id']);
            $table->dropColumn('cliente_id');
        });
        Schema::dropIfExists('reservaciones');
        Schema::dropIfExists('membresias');
        Schema::dropIfExists('clases');
        Schema::dropIfExists('clase_series');
        Schema::dropIfExists('ejercicios');
        Schema::dropIfExists('clase_tipos');
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['cuenta_id', 'tipo']);
            $table->dropColumn(['tipo', 'creditos', 'vigencia_dias', 'es_prueba', 'max_por_dia', 'tipos_clase']);
        });
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['qr_codigo']);
            $table->dropColumn(['foto', 'activo', 'password', 'remember_token', 'acceso_hash', 'acceso_expira', 'ultimo_acceso_at', 'qr_codigo']);
        });
        Schema::table('cuentas', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
