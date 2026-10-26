<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Licenciamiento de la plataforma.
 *  - planes: catálogo de planes (Fundador, Esencial, Negocio, Pro) y extras (IA, usuario, sucursal…), con precio y límites.
 *  - licencias: el acuerdo de cada negocio (plan, precio, extras, implementación, vencimiento y reglas de gracia).
 *  - licencia_pagos: cada pago registrado a mano; recorre el vencimiento.
 * Un negocio sin licencia no tiene límites ni vencimiento (así siguen los que ya existen hasta que se les asigne una).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 30)->unique();
            $table->string('tipo', 10)->default('plan');               // plan | extra
            $table->string('nombre', 80);
            $table->string('descripcion', 255)->nullable();
            $table->decimal('precio_mensual', 10, 2)->default(0);
            $table->decimal('precio_anual', 10, 2)->nullable();        // vacío = 10 mensualidades
            $table->unsignedSmallInteger('usuarios')->nullable();      // plan: máximo (vacío = sin límite) · extra: cuántos suma
            $table->unsignedSmallInteger('sucursales')->nullable();
            $table->json('incluye')->nullable();                       // funciones extra que prende: gastos, ia
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('licencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->unique()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
            $table->string('tipo', 12)->default('suscripcion');        // suscripcion | perpetua | cortesia
            $table->string('periodo', 8)->default('mensual');          // mensual | anual
            $table->decimal('precio', 10, 2)->default(0);              // lo acordado por periodo (plan + extras, o lo negociado)
            $table->json('extras')->nullable();                        // [{clave, cantidad, precio}]
            $table->decimal('implementacion', 10, 2)->default(0);
            $table->date('implementacion_pagada_el')->nullable();
            $table->decimal('mantenimiento', 10, 2)->nullable();       // licencia de por vida: cuota anual de mantenimiento
            $table->date('inicio');
            $table->date('vence_el')->nullable();                      // pagado hasta (perpetua: mantenimiento hasta)
            $table->unsignedSmallInteger('dias_gracia')->default(5);
            $table->unsignedSmallInteger('dias_lectura')->default(10);
            $table->text('notas')->nullable();
            $table->json('avisos')->nullable();                        // avisos ya mandados por vencimiento
            $table->timestamps();
        });

        Schema::create('licencia_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('licencia_id')->constrained('licencias')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('concepto', 16);                            // periodo | implementacion | mantenimiento | otro
            $table->decimal('monto', 10, 2);
            $table->string('forma', 20)->nullable();
            $table->string('referencia', 120)->nullable();
            $table->unsignedSmallInteger('periodos')->default(0);
            $table->date('cubre_desde')->nullable();
            $table->date('cubre_hasta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['cuenta_id', 'fecha']);
        });

        $ahora = now();
        $p = fn ($o, $clave, $tipo, $nombre, $desc, $mes, $usuarios, $sucursales, $incluye) => [
            'clave' => $clave, 'tipo' => $tipo, 'nombre' => $nombre, 'descripcion' => $desc, 'precio_mensual' => $mes, 'precio_anual' => $tipo === 'plan' ? $mes * 10 : null,
            'usuarios' => $usuarios, 'sucursales' => $sucursales, 'incluye' => json_encode($incluye), 'activo' => true, 'orden' => $o, 'created_at' => $ahora, 'updated_at' => $ahora,
        ];
        DB::table('planes')->insert([
            $p(0, 'fundador', 'plan', 'Fundador', 'Primeros 10 clientes: todas las funciones', 1000, null, 1, ['gastos', 'ia']),
            $p(1, 'esencial', 'plan', 'Esencial', 'Hasta 3 usuarios y una sucursal', 600, 3, 1, []),
            $p(2, 'negocio', 'plan', 'Negocio', 'Hasta 10 usuarios, con control de gastos', 1200, 10, 1, ['gastos']),
            $p(3, 'pro', 'plan', 'Pro', 'Sin límite de usuarios, 2 sucursales, gastos y lectura con IA', 2500, null, 2, ['gastos', 'ia']),
            $p(10, 'ia', 'extra', 'Lectura con IA', 'Tickets, constancias y listas de precios (150 al mes)', 299, null, null, ['gastos', 'ia']),
            $p(11, 'usuario', 'extra', 'Usuario adicional', null, 150, 1, null, []),
            $p(12, 'sucursal', 'extra', 'Sucursal adicional', null, 500, null, 1, []),
            $p(13, 'almacenamiento', 'extra', 'Almacenamiento', 'Carpeta propia en Dropbox', 349, null, null, []),
            $p(14, 'dominio', 'extra', 'Dominio propio', 'app.sunegocio.mx con su marca', 200, null, null, []),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('licencia_pagos');
        Schema::dropIfExists('licencias');
        Schema::dropIfExists('planes');
    }
};
