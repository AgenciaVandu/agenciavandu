<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redes sociales: calendario de contenido por cliente, vistas previas por red,
 * comentarios del cliente en cada post y aprobación con código de verificación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('redes_token', 40)->nullable()->unique()->after('contacto_at'); // enlace del cliente para su contenido
        });

        // Cómo se ve el perfil en cada red (para las vistas previas y el moodboard)
        Schema::create('redes_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('red', 20);                 // instagram | facebook | tiktok | linkedin
            $table->string('usuario', 80)->nullable(); // @hotelxcanatun
            $table->string('nombre', 120)->nullable();
            $table->string('bio', 500)->nullable();
            $table->string('enlace', 255)->nullable();
            $table->unsignedInteger('seguidores')->nullable();
            $table->unsignedInteger('seguidos')->nullable();
            $table->string('avatar', 500)->nullable(); // ruta local o id de Dropbox
            $table->string('avatar_origen', 12)->nullable();
            $table->timestamps();
            $table->unique(['cliente_id', 'red']);
        });

        Schema::create('redes_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('titulo', 120)->nullable(); // nombre interno ("Promo fin de semana")
            $table->json('redes');                      // ["instagram","facebook"]
            $table->string('formato', 20)->default('post'); // post | carrusel | reel | historia | video
            $table->dateTime('fecha')->nullable();       // cuándo se publica (hora local guardada en UTC)
            $table->text('texto')->nullable();
            $table->string('estado', 20)->default('borrador'); // borrador | revision | cambios | aprobado | publicado
            $table->timestamp('aprobado_at')->nullable();
            $table->string('aprobado_por', 120)->nullable();
            $table->timestamps();
            $table->index(['cliente_id', 'fecha']);
        });

        Schema::create('redes_medios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('redes_posts')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('tipo', 10);                 // imagen | video
            $table->string('origen', 12);               // local | dropbox
            $table->string('ruta', 600);                // ruta local o id:... de Dropbox
            $table->string('nombre', 255);
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->timestamps();
        });

        Schema::create('redes_comentarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('redes_posts')->cascadeOnDelete();
            $table->string('tipo', 20)->default('comentario'); // comentario | cambios | aprobado | estado | revision
            $table->string('actor', 10);                       // cliente | agencia
            $table->string('autor', 120)->nullable();
            $table->text('texto')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('redes_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('codigo_hash');
            $table->string('canal', 20);
            $table->timestamp('expira_at');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redes_codigos');
        Schema::dropIfExists('redes_comentarios');
        Schema::dropIfExists('redes_medios');
        Schema::dropIfExists('redes_posts');
        Schema::dropIfExists('redes_perfiles');
        Schema::table('clientes', fn (Blueprint $t) => $t->dropColumn('redes_token'));
    }
};
