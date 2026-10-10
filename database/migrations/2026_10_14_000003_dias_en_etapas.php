<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cada proyecto puede tener sus propias etapas: se guarda su duración estimada para proponer fechas
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyecto_etapas', function (Blueprint $table) {
            $table->unsignedSmallInteger('dias')->nullable()->after('es_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('proyecto_etapas', function (Blueprint $table) {
            $table->dropColumn('dias');
        });
    }
};
