<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\PresupuestoEvento;
use App\Models\User;
use App\Support\Aceptacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** El cliente acepta o pide cambios desde su enlace, solo con su nombre */
class AceptacionEnLineaTest extends TestCase
{
    use RefreshDatabase;

    private Presupuesto $p;
    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->u = User::factory()->create(['name' => 'Alvar']);
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X', 'email' => 'ana@hotel.mx', 'telefono' => '9991234567']);
        $p = Presupuesto::nuevaPara($c);
        $p->save();
        $p->conceptos()->create(['titulo' => 'Video 60 s', 'descripcion' => '', 'cantidad' => 1, 'precio' => 10000, 'orden' => 0]);
        $this->p = $p->fresh('conceptos');
    }

    private function responder(array $datos)
    {
        return $this->postJson('/cotizacion/' . $this->p->token . '/responder', $datos + ['nombre' => 'Ana López']);
    }

    public function test_whatsapp_lleva_el_enlace_sin_codigo_y_queda_en_el_historial(): void
    {
        $r = $this->actingAs($this->u)->get('/admin/presupuestos/' . $this->p->id . '/whatsapp');
        $r->assertRedirect();
        $texto = urldecode(parse_url($r->headers->get('Location'), PHP_URL_QUERY));
        $this->assertStringNotContainsString('código', $texto);
        $this->assertStringContainsString('puedes aceptarla o pedirme cambios', $texto);
        $this->assertStringContainsString($this->p->url_publica, $texto);
        $this->assertSame('enviada', $this->p->fresh()->estado);
        $this->assertSame(['enviada'], PresupuestoEvento::orderBy('id')->pluck('tipo')->all());
    }

    public function test_por_correo_y_se_acepta_desde_el_enlace_una_sola_vez(): void
    {
        $this->actingAs($this->u)->post('/admin/correos', [
            'contexto_tipo' => 'presupuesto', 'contexto_id' => $this->p->id, 'plantilla' => 'cotizacion',
            'para' => 'ana@hotel.mx', 'asunto' => 'Cotización', 'cuerpo' => "Hola Ana,\n\nTe comparto tu cotización.{codigo}",
        ])->assertSessionHasNoErrors();
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->codigo === null && ! str_contains($m->cuerpo, '{codigo}'));
        $this->assertSame('enviada', $this->p->fresh()->estado);

        auth()->logout();
        $this->get('/cotizacion/' . $this->p->token)->assertOk()->assertDontSee('Código de verificación');
        $this->responder(['accion' => 'aceptar'])->assertOk()->assertJson(['ok' => true]);

        $p = $this->p->fresh();
        $this->assertSame('aceptada', $p->estado);
        $this->assertNotNull($p->aceptada_el);
        $ev = $p->eventos()->where('tipo', 'aceptada')->sole();
        $this->assertSame(['cliente', 'Ana López'], [$ev->actor, $ev->autor]);

        // Ya aceptada: el cliente no puede volver a responder
        $this->responder(['accion' => 'cambios', 'mensaje' => 'Quiero otra cosa'])->assertStatus(409);
        $this->get('/cotizacion/' . $this->p->token)->assertOk()->assertSee('Aceptada por')->assertDontSee('data-responder="aceptar"', false);

        // En el panel se habilita crear proyecto y ya no hay códigos
        $this->actingAs($this->u)->get('/admin/presupuestos/' . $this->p->id . '/edit')->assertOk()->assertSee('Aceptada en línea')->assertSee('Crear proyecto')
            ->assertDontSee('Código de verificación');
    }

    public function test_pedir_cambios_pasa_a_negociacion_y_se_ve_en_ambos_lados(): void
    {
        $this->responder(['accion' => 'cambios'])->assertStatus(422); // falta el mensaje
        $this->responder(['accion' => 'cambios', 'mensaje' => 'Agregar 5 fotos aéreas con dron'])->assertOk();

        $this->assertSame('negociacion', $this->p->fresh()->estado);
        $this->get('/cotizacion/' . $this->p->token)->assertSee('Recibimos tus comentarios')->assertSee('Agregar 5 fotos aéreas con dron')->assertSee('Historial de esta cotización');
        $this->actingAs($this->u)->get('/admin/presupuestos/' . $this->p->id . '/edit')->assertSee('Pidió cambios')->assertSee('Agregar 5 fotos aéreas con dron');
    }

    public function test_sin_nombre_no_se_acepta_y_vencida_tampoco(): void
    {
        $this->postJson('/cotizacion/' . $this->p->token . '/responder', ['accion' => 'aceptar', 'nombre' => ''])->assertStatus(422);
        $this->p->update(['vigente_hasta' => now()->subDay()]);
        $this->responder(['accion' => 'aceptar'])->assertStatus(409);
        $this->assertSame('borrador', $this->p->fresh()->estado);
        $this->postJson('/cotizacion/' . $this->p->token . '/codigo')->assertNotFound();
    }

    public function test_editar_deja_version_con_diferencias_y_la_agencia_cambia_estado_a_mano(): void
    {
        $this->actingAs($this->u)->patch('/admin/presupuestos/' . $this->p->id . '/rapido', ['estado' => 'enviada'])->assertRedirect();
        $datos = $this->p->only(['cliente_id', 'cliente_nombre', 'cliente_empresa', 'titulo', 'emisor_nombre', 'modo_iva', 'iva_porcentaje', 'estado']);
        $datos['fecha'] = $this->p->fecha->toDateString();
        $datos['vigente_hasta'] = $this->p->vigencia_local->format('Y-m-d\TH:i');
        $datos['conceptos'] = [['titulo' => 'Video 60 s', 'cantidad' => 1, 'precio' => 12000], ['titulo' => 'Fotos aéreas', 'cantidad' => 5, 'precio' => 800]];
        $this->put('/admin/presupuestos/' . $this->p->id, $datos)->assertSessionHasNoErrors();

        $ev = $this->p->eventos()->where('tipo', 'editada')->sole();
        $this->assertSame(2, $ev->datos['version']);
        $this->assertStringContainsString('Se agregó: Fotos aéreas', $ev->detalle);
        $this->assertStringContainsString('Precio de “Video 60 s”', $ev->detalle);
        $this->assertTrue($this->p->eventos()->where('tipo', 'estado')->exists());
        auth()->logout();
        $this->get('/cotizacion/' . $this->p->token)->assertSee('Versión 2')->assertSee('Se agregó: Fotos aéreas');
    }
}
