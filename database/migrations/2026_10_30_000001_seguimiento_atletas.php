<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gimnasio: seguimiento de interesados y renovaciones.
 * La etapa de cada persona sale de sus paquetes (interesado → prueba → inscrito → por renovar → vencido);
 * lo único que se guarda a mano es si se descartó y por qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->timestamp('descartado_at')->nullable();
            $table->string('motivo_descarte', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['descartado_at', 'motivo_descarte']);
        });
    }
};
