<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Prospecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Los mensajes del formulario del sitio entran a Ventas como prospectos nuevos */
class ContactosSitioTest extends TestCase
{
    use RefreshDatabase;

    private function enviar(array $extra = [])
    {
        return $this->postJson('/mensaje-enviado', $extra + [
            'name' => 'Laura', 'lastname' => 'Canché', 'phone' => '999 123 4567',
            'email' => 'Laura@Ejemplo.mx', 'service' => 'Video y fotografía', 'g-recaptcha-response' => 'ok',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true, 'score' => 0.9])]);
        Mail::fake();
    }

    public function test_crea_el_prospecto_nuevo_y_no_duplica(): void
    {
        $this->enviar()->assertOk()->assertJson(['success' => true]);
        $p = Prospecto::sole();
        $this->assertTrue($p->nuevo);
        $this->assertSame('sitio', $p->origen);
        $this->assertSame('Laura Canché', $p->nombre);
        $this->assertSame('laura@ejemplo.mx', $p->email);
        $this->assertSame('Video y fotografía', $p->interes);
        $this->assertSame('Nuevo', $p->etapa->nombre);
        $this->assertSame(0, Cliente::count(), 'Todavía no es cliente');

        // Vuelve a escribir con otro correo pero el mismo celular: se actualiza, no se duplica
        $p->update(['nuevo' => false, 'empresa' => 'Hotel Laura']);
        $this->enviar(['email' => 'otra@ejemplo.mx', 'phone' => '+52 1 9991234567', 'service' => 'Diseño web'])->assertOk();
        $p->refresh();
        $this->assertSame(1, Prospecto::count());
        $this->assertTrue($p->nuevo);
        $this->assertSame('Hotel Laura', $p->empresa);
        $this->assertSame('laura@ejemplo.mx', $p->email);
        $this->assertSame('Diseño web', $p->interes);
        $this->assertSame(2, $p->eventos()->where('tipo', 'mensaje')->count());
    }

    public function test_si_ya_es_cliente_el_prospecto_queda_ligado(): void
    {
        $c = Cliente::create(['nombre' => 'Laura Canché', 'empresa' => 'Hotel Laura', 'email' => 'laura@ejemplo.mx']);
        $this->enviar()->assertOk();
        $p = Prospecto::sole();
        $this->assertSame($c->id, $p->cliente_id);
        $this->assertSame('Hotel Laura', $p->empresa);
    }

    public function test_si_falla_el_correo_igual_se_guarda_y_responde_bien(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->enviar()->assertOk()->assertJson(['success' => true]);
        $this->assertTrue(Prospecto::sole()->nuevo);
    }

    public function test_el_panel_avisa_y_al_abrirlo_deja_de_ser_nuevo(): void
    {
        $this->enviar()->assertOk();
        $p = Prospecto::sole();
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertOk()->assertSee('1 prospecto nuevo desde el sitio');
        $this->get('/admin/ventas')->assertOk()->assertSee('Laura Canch', false)->assertSee('1 nuevo');
        $this->get('/admin/ventas/prospectos/' . $p->id)->assertOk()->assertSee('Escribió desde el sitio')->assertSee('Video y fotografía');
        $this->assertFalse($p->fresh()->nuevo);
        $this->get('/admin')->assertDontSee('prospecto nuevo desde el sitio');
    }
}
