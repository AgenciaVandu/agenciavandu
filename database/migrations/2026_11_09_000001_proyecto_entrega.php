<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos y servicios: dónde se entrega o se instala un proyecto. Lo llena el cliente desde el enlace de su proyecto
 * (con su ubicación en el mapa) o alguien del equipo desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proyectos', function (Blueprint $table) {
            $table->string('entrega_nombre', 160)->nullable();          // quién recibe
            $table->string('entrega_telefono', 30)->nullable();
            $table->string('entrega_direccion', 300)->nullable();
            $table->string('entrega_referencias', 300)->nullable();
            $table->text('entrega_notas')->nullable();                  // horario, indicaciones, lo que debamos saber
            $table->decimal('entrega_lat', 10, 7)->nullable();
            $table->decimal('entrega_lng', 10, 7)->nullable();
            $table->timestamp('entrega_datos_at')->nullable();
            $table->string('entrega_datos_por', 10)->nullable();        // cliente | equipo
        });
    }

    public function down(): void
    {
        Schema::table('proyectos', fn (Blueprint $table) => $table->dropColumn(['entrega_nombre', 'entrega_telefono', 'entrega_direccion', 'entrega_referencias',
            'entrega_notas', 'entrega_lat', 'entrega_lng', 'entrega_datos_at', 'entrega_datos_por']));
    }
};
