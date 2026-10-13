<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas: embudo con etapas editables por negocio, prospectos (del formulario del sitio o capturados a mano)
 * y su historial. Las tareas ganan hora y pueden ligarse a un prospecto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embudo_etapas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 60);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unsignedTinyInteger('probabilidad')->default(0);   // % de que se cierre estando aquí
            $table->string('tipo', 10)->default('abierta');            // abierta | ganada | perdida
            $table->timestamps();
        });

        Schema::create('prospectos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->string('nombre', 160);
            $table->string('empresa', 160)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('origen', 20)->default('manual');           // sitio | manual | whatsapp | referido | redes | otro
            $table->string('interes', 160)->nullable();                // servicio que le interesa
            $table->decimal('valor', 12, 2)->nullable();               // cuánto vale la venta (antes de IVA)
            $table->foreignId('etapa_id')->nullable()->constrained('embudo_etapas')->nullOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->boolean('nuevo')->default(false);                  // llegó del sitio y no lo has abierto
            $table->text('notas')->nullable();
            $table->string('motivo_perdida', 60)->nullable();
            $table->timestamp('etapa_at')->nullable();                 // desde cuándo está en su etapa
            $table->timestamp('cerrado_at')->nullable();               // cuándo se ganó o se perdió
            $table->timestamp('contacto_at')->nullable();              // último mensaje desde el sitio
            $table->timestamps();
            $table->index(['cuenta_id', 'etapa_id']);
        });

        Schema::create('prospecto_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('prospecto_id')->constrained('prospectos')->cascadeOnDelete();
            $table->string('tipo', 12);                                // alta | etapa | nota | mensaje | convertido | cotizacion
            $table->foreignId('de_etapa_id')->nullable()->constrained('embudo_etapas')->nullOnDelete();
            $table->foreignId('a_etapa_id')->nullable()->constrained('embudo_etapas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('texto')->nullable();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('tareas', function (Blueprint $table) {
            $table->time('hora')->nullable()->after('fecha_limite');
            $table->foreignId('prospecto_id')->nullable()->after('proyecto_id')->constrained('prospectos')->nullOnDelete();
        });

        // Quien atendía los contactos del sitio (Clientes) ahora también ve Ventas
        foreach (DB::table('roles')->get() as $r) {
            $p = json_decode($r->permisos ?? '[]', true) ?: [];
            if (in_array('clientes', $p, true) && ! in_array('ventas', $p, true)) {
                $p[] = 'ventas';
                DB::table('roles')->where('id', $r->id)->update(['permisos' => json_encode($p)]);
            }
        }

        // Los contactos "Nuevo" del sitio que aún no tienen cotización pasan al embudo como prospectos
        $ahora = now();
        $clientes = DB::table('clientes')->where('nuevo', true)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('presupuestos')->whereColumn('presupuestos.cliente_id', 'clientes.id'))
            ->get();
        foreach ($clientes as $c) {
            $etapa = \App\Models\EmbudoEtapa::primeraDe((int) $c->cuenta_id);
            $id = DB::table('prospectos')->insertGetId([
                'cuenta_id' => $c->cuenta_id, 'nombre' => $c->nombre, 'empresa' => $c->empresa, 'email' => $c->email, 'telefono' => $c->telefono,
                'origen' => $c->origen === 'sitio' ? 'sitio' : 'manual', 'interes' => $c->interes, 'etapa_id' => $etapa,
                'cliente_id' => $c->id, 'nuevo' => true, 'notas' => $c->notas,
                'etapa_at' => $c->contacto_at ?? $c->created_at, 'contacto_at' => $c->contacto_at, 'created_at' => $c->contacto_at ?? $c->created_at, 'updated_at' => $ahora,
            ]);
            DB::table('prospecto_eventos')->insert(['cuenta_id' => $c->cuenta_id, 'prospecto_id' => $id, 'tipo' => 'alta', 'a_etapa_id' => $etapa,
                'texto' => 'Llegó como contacto del sitio', 'created_at' => $c->contacto_at ?? $c->created_at]);
            foreach (DB::table('cliente_mensajes')->where('cliente_id', $c->id)->get() as $m) {
                DB::table('prospecto_eventos')->insert(['cuenta_id' => $c->cuenta_id, 'prospecto_id' => $id, 'tipo' => 'mensaje',
                    'texto' => $m->servicio, 'datos' => $m->datos, 'created_at' => $m->created_at]);
            }
            DB::table('clientes')->where('id', $c->id)->update(['nuevo' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') $table->dropForeign(['prospecto_id']);
            $table->dropColumn(['hora', 'prospecto_id']);
        });
        Schema::dropIfExists('prospecto_eventos');
        Schema::dropIfExists('prospectos');
        Schema::dropIfExists('embudo_etapas');
    }
};
