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

/** El cliente acepta o pide cambios desde su enlace, con código de verificación de 24 h */
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

    public function test_whatsapp_lleva_el_codigo_y_queda_en_el_historial(): void
    {
        $r = $this->actingAs($this->u)->get('/admin/presupuestos/' . $this->p->id . '/whatsapp');
        $r->assertRedirect();
        $texto = urldecode(parse_url($r->headers->get('Location'), PHP_URL_QUERY));
        $this->assertMatchesRegularExpression('/código de verificación: \*\d{3} \d{3}\*/u', $texto);
        $this->assertStringContainsString($this->p->url_publica, $texto);
        $this->assertSame('enviada', $this->p->fresh()->estado);
        $this->assertSame(['codigo', 'enviada'], PresupuestoEvento::orderBy('id')->pluck('tipo')->all());
    }

    public function test_por_correo_va_el_codigo_y_con_el_se_acepta_una_sola_vez(): void
    {
        $this->actingAs($this->u)->post('/admin/correos', [
            'contexto_tipo' => 'presupuesto', 'contexto_id' => $this->p->id, 'plantilla' => 'cotizacion',
            'para' => 'ana@hotel.mx', 'asunto' => 'Cotización', 'cuerpo' => "Hola Ana,\n\nTu código es {codigo}.", 'incluir_codigo' => 1,
        ])->assertSessionHasNoErrors();

        $codigo = null;
        Mail::assertSent(CorreoVandu::class, function ($m) use (&$codigo) {
            $codigo = $m->codigo['formateado'] ?? null;
            return $codigo && str_contains($m->cuerpo, $codigo);
        });
        $this->assertSame('enviada', $this->p->fresh()->estado);

        auth()->logout();
        $this->responder(['accion' => 'aceptar', 'codigo' => '000 000'])->assertStatus(422)->assertJson(['campo' => 'codigo']);
        $this->responder(['accion' => 'aceptar', 'codigo' => $codigo])->assertOk()->assertJson(['ok' => true]);

        $p = $this->p->fresh();
        $this->assertSame('aceptada', $p->estado);
        $this->assertNotNull($p->aceptada_el);
        $ev = $p->eventos()->where('tipo', 'aceptada')->sole();
        $this->assertSame(['cliente', 'Ana López'], [$ev->actor, $ev->autor]);

        // Ya aceptada: el cliente no puede volver a responder
        $this->responder(['accion' => 'cambios', 'mensaje' => 'Quiero otra cosa', 'codigo' => $codigo])->assertStatus(409);
        $this->get('/cotizacion/' . $this->p->token)->assertOk()->assertSee('Aceptada por')->assertDontSee('data-responder="aceptar"', false);

        // En el panel se habilita crear proyecto
        $this->actingAs($this->u)->get('/admin/presupuestos/' . $this->p->id . '/edit')->assertOk()->assertSee('Aceptada en línea')->assertSee('Crear proyecto');
    }

    public function test_pedir_cambios_pasa_a_negociacion_y_se_ve_en_ambos_lados(): void
    {
        $c = Aceptacion::generarCodigo($this->p, 'manual');
        $this->responder(['accion' => 'cambios', 'codigo' => $c['codigo']])->assertStatus(422); // falta el mensaje
        $this->responder(['accion' => 'cambios', 'mensaje' => 'Agregar 5 fotos aéreas con dron', 'codigo' => $c['formateado']])->assertOk();

        $this->assertSame('negociacion', $this->p->fresh()->estado);
        $this->get('/cotizacion/' . $this->p->token)->assertSee('Recibimos tus comentarios')->assertSee('Agregar 5 fotos aéreas con dron')->assertSee('Historial de esta cotización');
        $this->actingAs($this->u)->get('/admin/presupuestos/' . $this->p->id . '/edit')->assertSee('Pidió cambios')->assertSee('Agregar 5 fotos aéreas con dron');
    }

    public function test_el_codigo_vence_a_las_24_horas_y_se_bloquea_tras_5_intentos(): void
    {
        $c = Aceptacion::generarCodigo($this->p, 'manual');
        $this->travel(25)->hours();
        $this->responder(['accion' => 'aceptar', 'codigo' => $c['codigo']])->assertStatus(422)->assertJsonFragment(['campo' => 'codigo']);
        $this->travelBack();

        $c2 = Aceptacion::generarCodigo($this->p, 'manual');
        $mal = $c2['codigo'] === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) $this->responder(['accion' => 'aceptar', 'codigo' => $mal]);
        $this->responder(['accion' => 'aceptar', 'codigo' => $c2['codigo']])->assertStatus(422);
        $this->assertSame('borrador', $this->p->fresh()->estado);
    }

    public function test_el_cliente_puede_pedir_su_codigo_por_correo_con_limite(): void
    {
        $this->postJson('/cotizacion/' . $this->p->token . '/codigo')->assertOk()->assertJsonFragment(['ok' => true]);
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('ana@hotel.mx') && preg_match('/\d{3} \d{3}/', $m->asunto));
        $this->postJson('/cotizacion/' . $this->p->token . '/codigo');
        $this->postJson('/cotizacion/' . $this->p->token . '/codigo');
        $this->postJson('/cotizacion/' . $this->p->token . '/codigo')->assertStatus(429);
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
