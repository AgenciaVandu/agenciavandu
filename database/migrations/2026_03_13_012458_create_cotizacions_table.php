<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('cotizacions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('lastname');
        $table->string('phone');
        $table->string('email');
        $table->string('service');
        $table->timestamps(); // Esto guarda la fecha y hora automáticamente
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cotizacions');
    }
};
