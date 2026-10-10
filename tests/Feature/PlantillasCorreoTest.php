<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\CorreoPlantilla;
use App\Models\User;
use App\Support\Correos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Plantillas de correo editables desde el panel */
class PlantillasCorreoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Cliente::create(['nombre' => 'Laura Canché', 'empresa' => 'Hotel Laura', 'email' => 'laura@x.mx']);
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['nombre' => 'Bienvenida', 'asunto' => 'Hola {nombre}, bienvenida', 'titulo' => 'Qué gusto',
            'cuerpo' => "Hola {nombre},\n\nGracias por elegir a Vandu.", 'boton' => '', 'activa' => 1];
    }

    public function test_editar_una_de_fabrica_se_usa_al_enviar_y_se_puede_restaurar(): void
    {
        $this->get('/admin/correos/plantillas?p=bienvenida')->assertOk()->assertSee('Plantillas de correo')->assertSee('{empresa}');

        $this->put('/admin/correos/plantillas/bienvenida', $this->datos())->assertRedirect('/admin/correos/plantillas?p=bienvenida');
        $ctx = Correos::contexto('cliente', Cliente::first()->id);
        $pl = Correos::plantillas($ctx)['bienvenida'];
        $this->assertSame('Hola Laura, bienvenida', $pl['asunto']);
        $this->assertStringContainsString('Gracias por elegir a Vandu.', $pl['cuerpo']);
        $this->get('/admin/correos/plantillas?p=bienvenida')->assertSee('Editada')->assertSee('Restaurar original');

        $this->post('/admin/correos/plantillas/bienvenida/restaurar')->assertRedirect();
        $this->assertSame(0, CorreoPlantilla::count());
        $this->assertSame('Bienvenido a Agencia Vandu', Correos::plantillas($ctx)['bienvenida']['asunto']);
    }

    public function test_ocultar_y_dejar_igual_que_el_original_no_guarda_nada(): void
    {
        $orig = config('vandu.correo.plantillas.bienvenida');
        $this->put('/admin/correos/plantillas/bienvenida', ['nombre' => $orig['nombre'], 'asunto' => $orig['asunto'], 'titulo' => $orig['titulo'], 'cuerpo' => str_replace("\n", "\r\n", $orig['cuerpo']), 'activa' => 1]);
        $this->assertSame(0, CorreoPlantilla::count());

        $this->put('/admin/correos/plantillas/bienvenida', $this->datos(['activa' => 0]));
        $ctx = Correos::contexto('cliente', Cliente::first()->id);
        $this->assertArrayNotHasKey('bienvenida', Correos::plantillas($ctx));
    }

    public function test_crear_editar_y_borrar_una_propia(): void
    {
        $this->post('/admin/correos/plantillas')->assertRedirect();
        $p = CorreoPlantilla::sole();
        $this->assertSame('propia_' . $p->id, $p->clave);

        $this->put('/admin/correos/plantillas/' . $p->clave, $this->datos(['nombre' => 'Seguimiento', 'para' => ['cliente'], 'icono' => 'bi-telephone', 'boton' => 'Agendar llamada', 'resumen' => 1]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $ctx = Correos::contexto('cliente', Cliente::first()->id);
        $pl = Correos::plantillas($ctx)[$p->clave];
        $this->assertSame('Seguimiento', $pl['etiqueta']);
        $this->assertSame('bi-telephone', $pl['icono']);

        $this->put('/admin/correos/plantillas/' . $p->clave, $this->datos(['para' => []]))->assertSessionHasErrors('para');

        $this->post('/admin/correos/plantillas/' . $p->clave . '/previa', ['asunto' => 'Hola {nombre}', 'cuerpo' => 'Texto {empresa}', 'boton' => 'Ir', 'para' => ['cliente']])
            ->assertOk()->assertSee('Texto Hotel Laura')->assertHeader('X-Asunto', rawurlencode('Hola Laura'));

        $this->delete('/admin/correos/plantillas/' . $p->clave)->assertRedirect('/admin/correos/plantillas');
        $this->assertSame(0, CorreoPlantilla::count());
        $this->delete('/admin/correos/plantillas/bienvenida')->assertNotFound();
    }

    public function test_vista_previa_de_fabrica_con_datos_de_ejemplo(): void
    {
        $this->post('/admin/correos/plantillas/recordatorio_pago/previa', ['asunto' => 'Pago de {proyecto}', 'cuerpo' => 'El {pago} de {monto_pago}'])
            ->assertOk()->assertSee('El anticipo de $10,000.00')->assertSee('CLABE');
    }
}
