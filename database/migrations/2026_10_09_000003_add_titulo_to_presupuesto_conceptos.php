<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuesto_conceptos', function (Blueprint $table) {
            $table->string('titulo')->nullable()->after('presupuesto_id');
        });
    }

    public function down(): void
    {
        Schema::table('presupuesto_conceptos', fn (Blueprint $table) => $table->dropColumn('titulo'));
    }
};
