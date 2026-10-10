<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Equipo: roles con permisos por sección, usuarios con puesto e invitación, y tareas con entregas a Dropbox
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->string('descripcion', 200)->nullable();
            $table->json('permisos')->nullable();
            $table->boolean('todo')->default(false);     // super admin: todo, siempre
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('rol_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->string('puesto', 80)->nullable()->after('rol_id');
            $table->string('telefono', 30)->nullable()->after('puesto');
            $table->boolean('activo')->default(true)->after('telefono');
            $table->string('invitacion_hash', 64)->nullable()->unique()->after('activo');
            $table->timestamp('invitacion_expira')->nullable()->after('invitacion_hash');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('invitacion_expira');
        });

        $ahora = now();
        $roles = [
            ['nombre' => 'Super admin', 'descripcion' => 'Puede hacer todo, incluido invitar personas y cambiar permisos.', 'todo' => true, 'permisos' => null],
            ['nombre' => 'Project', 'descripcion' => 'Gestiona las tareas del equipo, los proyectos y el contenido de redes.', 'todo' => false, 'permisos' => ['proyectos', 'tareas', 'clientes', 'redes', 'archivos']],
            ['nombre' => 'Fotógrafo', 'descripcion' => 'Ve sus tareas y sube fotos y videos a la carpeta de cada una.', 'todo' => false, 'permisos' => []],
            ['nombre' => 'Diseñador', 'descripcion' => 'Ve sus tareas y sube sus diseños a la carpeta que le asignen.', 'todo' => false, 'permisos' => []],
        ];
        foreach ($roles as $i => $r) {
            DB::table('roles')->insert(array_merge($r, ['orden' => $i, 'permisos' => json_encode($r['permisos']), 'created_at' => $ahora, 'updated_at' => $ahora]));
        }
        // Quien ya usaba el panel es super admin
        $super = DB::table('roles')->where('todo', true)->value('id');
        DB::table('users')->update(['rol_id' => $super]);

        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->text('descripcion')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('asignada_a')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_limite')->nullable();
            $table->boolean('urgente')->default(false);
            $table->string('estado', 12)->default('pendiente'); // pendiente | en_curso | revision | terminada
            $table->string('carpeta', 500)->nullable();          // carpeta de Dropbox donde se entrega
            $table->timestamp('entregada_at')->nullable();
            $table->timestamp('terminada_at')->nullable();
            $table->timestamps();
            $table->index(['asignada_a', 'estado']);
        });

        Schema::create('tarea_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('ruta', 600);
            $table->string('dropbox_id', 120)->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->timestamps();
        });

        Schema::create('tarea_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarea_id')->constrained('tareas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarea_comentarios');
        Schema::dropIfExists('tarea_archivos');
        Schema::dropIfExists('tareas');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') $table->dropForeign(['rol_id']);
            $table->dropUnique(['invitacion_hash']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rol_id', 'puesto', 'telefono', 'activo', 'invitacion_hash', 'invitacion_expira', 'ultimo_acceso_at']);
        });
        Schema::dropIfExists('roles');
    }
};
