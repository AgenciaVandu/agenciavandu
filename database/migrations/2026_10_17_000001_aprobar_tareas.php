<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Al aprobar una tarea, lo entregado pasa solo al proyecto (galería o documentos de una etapa)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->string('destino', 12)->nullable()->after('carpeta');      // galeria | documento | null (no se incluye)
            $table->foreignId('etapa_id')->nullable()->after('destino')->constrained('proyecto_etapas')->nullOnDelete();
            $table->boolean('completar_etapa')->default(false)->after('etapa_id');
            $table->unsignedSmallInteger('ronda')->default(1)->after('completar_etapa'); // cuántas veces se ha entregado
            $table->foreignId('aprobada_por')->nullable()->after('terminada_at')->constrained('users')->nullOnDelete();
        });
        Schema::table('tarea_archivos', function (Blueprint $table) {
            $table->unsignedSmallInteger('ronda')->default(1)->after('tamano');
            $table->foreignId('proyecto_archivo_id')->nullable()->after('ronda')->constrained('proyecto_archivos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $sqlite = Schema::getConnection()->getDriverName() === 'sqlite';
        Schema::table('tarea_archivos', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) $table->dropForeign(['proyecto_archivo_id']);
        });
        Schema::table('tarea_archivos', fn (Blueprint $table) => $table->dropColumn(['ronda', 'proyecto_archivo_id']));
        Schema::table('tareas', function (Blueprint $table) use ($sqlite) {
            if (! $sqlite) { $table->dropForeign(['etapa_id']); $table->dropForeign(['aprobada_por']); }
        });
        Schema::table('tareas', fn (Blueprint $table) => $table->dropColumn(['destino', 'etapa_id', 'completar_etapa', 'ronda', 'aprobada_por']));
    }
};
