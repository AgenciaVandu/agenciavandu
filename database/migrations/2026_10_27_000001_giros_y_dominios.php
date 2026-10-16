<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de empresa creados desde Plataforma (además de los de fábrica en config/vandu.php)
 * y el dominio propio de cada negocio (app.sunegocio.mx).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giros', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('nombre', 80);
            $table->string('icono', 40)->default('bi-briefcase');
            $table->string('descripcion', 255)->nullable();
            $table->json('modulos')->nullable();     // ['stock', 'redes']
            $table->json('roles')->nullable();       // [{nombre, descripcion, permisos[]}]
            $table->json('proyectos')->nullable();   // tipos de proyecto de arranque
            $table->json('costeo')->nullable();      // componentes de costeo y utilidad
            $table->timestamps();
        });

        Schema::table('cuentas', function (Blueprint $table) {
            $table->string('dominio', 190)->nullable()->unique()->after('giro');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->dropUnique(['dominio']);
            $table->dropColumn('dominio');
        });
        Schema::dropIfExists('giros');
    }
};
