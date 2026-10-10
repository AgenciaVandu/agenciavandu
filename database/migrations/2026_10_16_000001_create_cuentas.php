<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plataforma con varios negocios: cada cuenta (agencia, estudio…) tiene sus propios datos.
 * Todo lo que ya existía queda en la cuenta 1: Agencia Vandu.
 */
return new class extends Migration
{
    /** Tablas con datos de un negocio */
    public const TABLAS = [
        'users', 'roles', 'clientes', 'cliente_constancias', 'cliente_mensajes', 'correos', 'correo_plantillas', 'cotizacions',
        'integraciones', 'notificaciones', 'presupuestos', 'presupuesto_codigos', 'presupuesto_conceptos', 'presupuesto_eventos',
        'proyectos', 'proyecto_archivos', 'proyecto_etapas', 'proyecto_pagos', 'proyecto_tipos', 'push_suscripciones',
        'redes_codigos', 'redes_comentarios', 'redes_medios', 'redes_perfiles', 'redes_posts', 'tareas', 'tarea_archivos', 'tarea_comentarios',
    ];

    /** Únicos que ahora son por cuenta: [tabla, columna] */
    private const UNICOS = [['presupuestos', 'folio'], ['proyecto_tipos', 'clave'], ['correo_plantillas', 'clave'], ['integraciones', 'proveedor']];

    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('giro', 30)->default('agencia');   // agencia | arquitectura
            $table->boolean('activa')->default(true);
            $table->json('ajustes')->nullable();              // marca, datos de contacto, banco, folio…
            $table->text('notas')->nullable();                // solo las ve la plataforma
            $table->timestamps();
        });
        DB::table('cuentas')->insert(['id' => 1, 'nombre' => 'Agencia Vandu', 'giro' => 'agencia', 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);

        foreach (self::TABLAS as $t) {
            if (! Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) {
                $table->foreignId('cuenta_id')->nullable()->after('id')->constrained('cuentas')->cascadeOnDelete();
                $table->index('cuenta_id');
            });
            DB::table($t)->update(['cuenta_id' => 1]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('plataforma')->default(false)->after('activo'); // administra la plataforma (da de alta cuentas)
        });
        // Quien ya administraba el panel de Vandu administra la plataforma
        $super = DB::table('roles')->where('todo', true)->value('id');
        DB::table('users')->where('rol_id', $super)->update(['plataforma' => true]);

        foreach (self::UNICOS as [$t, $col]) {
            Schema::table($t, function (Blueprint $table) use ($t, $col) {
                $table->dropUnique("{$t}_{$col}_unique");
                $table->unique(['cuenta_id', $col]);
            });
        }
    }

    public function down(): void
    {
        foreach (self::UNICOS as [$t, $col]) {
            Schema::table($t, function (Blueprint $table) use ($col) {
                $table->dropUnique(['cuenta_id', $col]);
                $table->unique($col);
            });
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('plataforma'));
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        foreach (self::TABLAS as $t) {
            if (! Schema::hasTable($t)) continue;
            Schema::table($t, function (Blueprint $table) use ($sqlite) {
                if (! $sqlite) $table->dropForeign(['cuenta_id']);
                $table->dropIndex(['cuenta_id']);
            });
            Schema::table($t, fn (Blueprint $table) => $table->dropColumn('cuenta_id'));
        }
        Schema::dropIfExists('cuentas');
    }
};
