<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Un solo enlace por cliente con todas sus cotizaciones y proyectos
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('portal_token', 32)->nullable()->unique()->after('redes_token');
            $table->timestamp('portal_visto_at')->nullable()->after('portal_token');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['portal_token']);
        });
        Schema::table('clientes', fn (Blueprint $table) => $table->dropColumn(['portal_token', 'portal_visto_at']));
    }
};
