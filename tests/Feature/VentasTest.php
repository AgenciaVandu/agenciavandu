<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\EmbudoEtapa;
use App\Models\Presupuesto;
use App\Models\Prospecto;
use App\Models\Rol;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ventas: embudo editable, prospectos con valor, ganados/perdidos, convertir a cliente y tareas con hora */
class VentasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Alvar Buenfil']);
        $this->actingAs($this->admin);
    }

    private function prospecto(array $d = []): Prospecto
    {
        $this->post('/admin/ventas/prospectos', $d + [
            'nombre' => 'Ana López', 'empresa' => 'Hotel X', 'telefono' => '9991234567', 'origen' => 'referido',
            'interes' => 'Video corporativo', 'valor' => 40000, 'etapa_id' => EmbudoEtapa::primera()->id,
        ])->assertRedirect();
        return Prospecto::latest('id')->first();
    }

    public function test_embudo_de_fabrica_y_valores(): void
    {
        $this->get('/admin/ventas')->assertOk()->assertSee('Negociaci', false);
        $this->assertSame(['Nuevo', 'Contactado', 'Reunión o diagnóstico', 'Propuesta enviada', 'Negociación'], EmbudoEtapa::abiertas()->pluck('nombre')->all());

        $a = $this->prospecto();
        $b = $this->prospecto(['nombre' => 'Luis', 'empresa' => 'Café Lima', 'valor' => 10000]);
        $neg = EmbudoEtapa::abiertas()->last();   // 80 %
        $this->patchJson("/admin/ventas/prospectos/{$a->id}/etapa", ['etapa_id' => $neg->id])->assertOk()->assertJson(['ok' => true]);

        $a->refresh();
        $this->assertSame($neg->id, $a->etapa_id);
        $this->assertEquals(32000, $a->ponderado);
        $html = $this->get('/admin/ventas')->assertOk()->getContent();
        $this->assertStringContainsString('$50,000', $html);   // en el embudo
        $this->assertStringContainsString('$33,000', $html);   // pronóstico: 32,000 + 10 % de 10,000
    }

    public function test_ganar_perder_y_resultados(): void
    {
        $a = $this->prospecto();
        $b = $this->prospecto(['nombre' => 'Luis', 'empresa' => 'Café Lima', 'valor' => 10000]);
        $c = $this->prospecto(['nombre' => 'Rosa', 'empresa' => 'Spa Rosa', 'valor' => 5000, 'origen' => 'whatsapp']);
        $contactado = EmbudoEtapa::abiertas()[1];
        $this->patch("/admin/ventas/prospectos/{$b->id}/etapa", ['etapa_id' => $contactado->id]);

        $this->patch("/admin/ventas/prospectos/{$a->id}/etapa", ['etapa_id' => EmbudoEtapa::ganada()->id, 'valor' => 45000])->assertRedirect();
        $this->patch("/admin/ventas/prospectos/{$b->id}/etapa", ['etapa_id' => EmbudoEtapa::perdida()->id, 'motivo' => 'Precio', 'nota' => 'Muy caro'])->assertRedirect();

        $a->refresh(); $b->refresh();
        $this->assertEquals(45000, $a->valor);
        $this->assertNotNull($a->cerrado_at);
        $this->assertSame('Precio', $b->motivo_perdida);

        // (primero perdidos: ahí se lee el aviso de la acción anterior, que menciona a Café Lima)
        $this->get('/admin/ventas?ver=perdidos')->assertOk()->assertSee('Café Lima')->assertSee('Precio');
        $this->get('/admin/ventas?ver=ganados')->assertOk()->assertSee('Hotel X')->assertSee('$45,000')->assertDontSee('Café Lima');
        $this->get('/admin/ventas?ver=resultados')->assertOk()
            ->assertSee('Se convirtieron')->assertSee('Dónde se mueren')->assertSee('Contactado')   // se murió estando en Contactado
            ->assertSee('Recomendación')->assertSee('WhatsApp');
        $this->get('/admin/ventas')->assertSee('50%');                                                // 1 ganado de 2 cerrados

        // Se reactiva: vuelve al embudo y pierde la fecha de cierre
        $this->patch("/admin/ventas/prospectos/{$b->id}/etapa", ['etapa_id' => $contactado->id]);
        $this->assertNull($b->fresh()->cerrado_at);
        $this->assertNull($b->fresh()->motivo_perdida);
    }

    public function test_convertir_en_cliente_cotizar_y_ganar_al_aceptar(): void
    {
        $p = $this->prospecto(['email' => 'ana@hotelx.mx']);
        $r = $this->post("/admin/ventas/prospectos/{$p->id}/convertir");
        $c = Cliente::sole();
        $r->assertRedirect(route('admin.presupuestos.create', ['cliente' => $c->id, 'prospecto' => $p->id]));
        $this->assertSame('Hotel X', $c->empresa);
        $this->assertSame($c->id, $p->fresh()->cliente_id);
        $this->get(route('admin.presupuestos.create', ['cliente' => $c->id, 'prospecto' => $p->id]))->assertOk()->assertSee('name="prospecto_id"', false);

        // La cotización se crea ligada al prospecto
        $this->post('/admin/presupuestos', [
            'prospecto_id' => $p->id, 'cliente_id' => $c->id, 'cliente_nombre' => 'Ana López', 'cliente_empresa' => 'Hotel X', 'fecha' => '2026-10-12',
            'titulo' => 'Cotización', 'tipo' => 'audiovisual', 'emisor_nombre' => 'Alvar', 'modo_iva' => 'mas_iva', 'iva_porcentaje' => 16,
            'vigente_hasta' => now()->addDays(10)->format('Y-m-d\TH:i'), 'estado' => 'enviada',
            'conceptos' => [['titulo' => 'Video corporativo', 'cantidad' => 1, 'precio' => 38000]],
        ])->assertRedirect();
        $cot = Presupuesto::sole();
        $this->assertSame($cot->id, $p->fresh()->presupuesto_id);
        $this->assertSame('abierta', $p->fresh()->etapa->tipo);

        // Al aceptarla, el prospecto pasa solo a Ganado con el valor de la cotización
        $cot->update(['estado' => 'aceptada']);
        $p->refresh();
        $this->assertSame('ganada', $p->etapa->tipo);
        $this->assertEquals(38000, $p->valor);
        $this->get("/admin/ventas/prospectos/{$p->id}")->assertOk()->assertSee('Ganado')->assertSee($cot->folio)->assertSee('Se volvió cliente');
    }

    public function test_seguimiento_con_fecha_y_hora(): void
    {
        $p = $this->prospecto();
        $this->post("/admin/ventas/prospectos/{$p->id}/seguimiento", ['titulo' => 'Llamar a Ana', 'fecha_limite' => now(config('vandu.zona_horaria'))->addDay()->toDateString(), 'hora' => '16:30'])
            ->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, 'mañana'));
        $t = Tarea::sole();
        $this->assertSame($p->id, $t->prospecto_id);
        $this->assertSame($this->admin->id, $t->asignada_a);
        $this->assertSame('Mañana · 4:30 p. m.', $t->cuando);
        $this->assertSame('Hotel X', $t->donde);
        $this->get("/admin/ventas/prospectos/{$p->id}")->assertSee('Llamar a Ana')->assertSee('4:30 p. m.');
        $this->get('/admin/tareas')->assertOk()->assertSee('Llamar a Ana')->assertSee('4:30 p. m.');
        $this->get("/admin/tareas/{$t->id}")->assertOk()->assertSee('Hotel X');

        // Desde el formulario de tareas también se pone hora y prospecto
        $this->post('/admin/tareas', ['titulo' => 'Mandar propuesta', 'fecha_limite' => now(config('vandu.zona_horaria'))->toDateString(), 'hora' => '00:05', 'prospecto_id' => $p->id])->assertRedirect();
        $t2 = Tarea::where('titulo', 'Mandar propuesta')->sole();
        $this->assertSame($p->id, $t2->prospecto_id);
        $this->assertTrue($t2->vencida, 'Hoy, pero ya pasó la hora');
        $this->get('/admin/tareas/create')->assertOk()->assertSee('tf-hora', false)->assertSee('Prospecto de ventas');
    }

    public function test_etapas_editables(): void
    {
        $p = $this->prospecto();
        $etapas = EmbudoEtapa::abiertas();
        $this->get('/admin/ventas/etapas')->assertOk();

        // Renombra, cambia probabilidad, quita "Nuevo" (donde está el prospecto) y agrega una
        $this->put('/admin/ventas/etapas', [
            'etapas' => [
                ['id' => $etapas[1]->id, 'nombre' => 'Primer contacto', 'probabilidad' => 15],
                ['id' => null, 'nombre' => 'Visita', 'probabilidad' => 35],
                ['id' => $etapas[3]->id, 'nombre' => 'Propuesta', 'probabilidad' => 60],
            ],
            'ganada' => 'Cerrado', 'perdida' => 'No se dio',
        ])->assertRedirect();

        $this->assertSame(['Primer contacto', 'Visita', 'Propuesta'], EmbudoEtapa::abiertas()->pluck('nombre')->all());
        $this->assertSame('Primer contacto', $p->fresh()->etapa->nombre, 'Sus prospectos pasan a la primera etapa');
        $this->assertSame('Cerrado', EmbudoEtapa::ganada()->nombre);
        $this->put('/admin/ventas/etapas', ['etapas' => [], 'ganada' => 'x', 'perdida' => 'y'])->assertSessionHasErrors('etapas');
    }

    public function test_permisos_y_buscador(): void
    {
        $this->prospecto();
        $this->getJson('/admin/buscar?q=hotel')->assertOk()->assertJsonFragment(['clave' => 'prospectos']);

        $foto = User::factory()->create(['rol_id' => Rol::where('nombre', 'Fotógrafo')->value('id')]);
        $this->actingAs($foto)->get('/admin/ventas')->assertForbidden();
        $this->assertNotContains('prospectos', collect($this->getJson('/admin/buscar?q=hotel')->json('grupos'))->pluck('clave'));

        // Quien veía Clientes (Project) ahora también ve Ventas
        $this->assertTrue(Rol::where('nombre', 'Project')->first()->puede('ventas'));
    }
}
