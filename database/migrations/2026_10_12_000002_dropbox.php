<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Almacenamiento en Dropbox: la conexión (tokens cifrados) y dónde vive cada archivo.
 * Los archivos con origen "dropbox" guardan su id de Dropbox; en el servidor solo quedan vistas previas ligeras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor', 30)->unique();
            $table->string('cuenta')->nullable();
            $table->text('datos')->nullable(); // cifrado
            $table->timestamps();
        });

        Schema::table('proyecto_archivos', function (Blueprint $table) {
            $table->string('origen', 12)->default('local')->after('grupo');
            $table->string('dropbox_id', 120)->nullable()->after('origen');
            $table->index('dropbox_id');
        });

        Schema::table('proyectos', function (Blueprint $table) {
            $table->string('dropbox_carpeta', 500)->nullable()->after('token');
            $table->string('dropbox_zip_url', 500)->nullable()->after('dropbox_carpeta');
        });

        Schema::table('cliente_constancias', function (Blueprint $table) {
            $table->string('origen', 12)->default('local')->after('ruta');
            $table->string('dropbox_id', 120)->nullable()->after('origen');
        });
    }

    public function down(): void
    {
        Schema::table('cliente_constancias', fn (Blueprint $table) => $table->dropColumn(['origen', 'dropbox_id']));
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn(['dropbox_carpeta', 'dropbox_zip_url']));
        Schema::table('proyecto_archivos', function (Blueprint $table) {
            $table->dropIndex(['dropbox_id']);
            $table->dropColumn(['origen', 'dropbox_id']);
        });
        Schema::dropIfExists('integraciones');
    }
};
