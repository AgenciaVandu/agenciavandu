<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones para prospectos: una cotización puede ser para alguien que todavía no es cliente.
 * Al aceptarla (o al crear su proyecto) el prospecto se vuelve cliente solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->foreignId('prospecto_id')->nullable()->after('cliente_id')->constrained('prospectos')->nullOnDelete();
        });
        $this->clienteOpcional();

        // Las cotizaciones que ya estaban ligadas a un prospecto
        foreach (DB::table('prospectos')->whereNotNull('presupuesto_id')->get(['id', 'presupuesto_id']) as $p) {
            DB::table('presupuestos')->where('id', $p->presupuesto_id)->whereNull('prospecto_id')->update(['prospecto_id' => $p->id]);
        }
    }

    /** cliente_id deja de ser obligatorio (sin depender de doctrine/dbal) */
    private function clienteOpcional(): void
    {
        $driver = DB::getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE presupuestos MODIFY cliente_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE presupuestos ALTER COLUMN cliente_id DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite no altera columnas: se ajusta la definición guardada de la tabla
            $sql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'presupuestos'")->sql;
            $nuevo = preg_replace('/("cliente_id" integer) not null/i', '$1', $sql, 1);
            if ($nuevo !== $sql) {
                $version = DB::selectOne('PRAGMA schema_version')->schema_version;
                DB::statement('PRAGMA writable_schema = ON');
                DB::update("UPDATE sqlite_master SET sql = ? WHERE type = 'table' AND name = 'presupuestos'", [$nuevo]);
                DB::statement('PRAGMA schema_version = ' . ((int) $version + 1));
                DB::statement('PRAGMA writable_schema = OFF');
            }
        }
    }

    public function down(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('prospecto_id');
        });
    }
};
