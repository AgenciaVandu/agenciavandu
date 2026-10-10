<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notificaciones push: los dispositivos donde se activaron y un historial de lo enviado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('p256dh', 120);
            $table->string('auth', 60);
            $table->string('dispositivo', 120)->nullable();
            $table->json('eventos')->nullable(); // null = todos
            $table->timestamp('ultimo_envio_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 40);
            $table->string('titulo');
            $table->string('cuerpo', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('entregadas')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
        Schema::dropIfExists('push_suscripciones');
    }
};
