<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Los mensajes del formulario del sitio quedan como clientes "Nuevo" en el panel */
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

    public function test_crea_el_contacto_como_nuevo_y_no_duplica(): void
    {
        $this->enviar()->assertOk()->assertJson(['success' => true]);
        $c = Cliente::sole();
        $this->assertTrue($c->nuevo);
        $this->assertSame('sitio', $c->origen);
        $this->assertSame('Laura Canché', $c->nombre);
        $this->assertSame('laura@ejemplo.mx', $c->email);
        $this->assertSame('Video y fotografía', $c->interes);

        // Lo atiendes, lo editas, y vuelve a escribir con otro servicio y otro correo pero el mismo celular
        $c->update(['nuevo' => false, 'empresa' => 'Hotel Laura']);
        $this->enviar(['email' => 'otra@ejemplo.mx', 'phone' => '+52 1 9991234567', 'service' => 'Diseño web'])->assertOk();

        $c->refresh();
        $this->assertSame(1, Cliente::count());
        $this->assertTrue($c->nuevo);
        $this->assertSame('Hotel Laura', $c->empresa);
        $this->assertSame('laura@ejemplo.mx', $c->email); // no pisa lo que ya tenías
        $this->assertSame('Diseño web', $c->interes);
        $this->assertSame(2, $c->mensajes()->count());
    }

    public function test_si_falla_el_correo_igual_se_guarda_y_responde_bien(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->enviar()->assertOk()->assertJson(['success' => true]);
        $this->assertTrue(Cliente::sole()->nuevo);
    }

    public function test_panel_filtra_nuevos_y_se_atienden(): void
    {
        $this->enviar()->assertOk();
        Cliente::create(['nombre' => 'Cliente viejo']);
        $c = Cliente::where('nuevo', true)->sole();
        $u = User::factory()->create();

        $this->actingAs($u)->get('/admin/clientes?ver=nuevos')->assertOk()->assertSee('Laura Canché')->assertDontSee('Cliente viejo')->assertSee('1 nuevo');
        $this->get('/admin')->assertOk()->assertSee('1 contacto nuevo desde el sitio');
        $this->get('/admin/clientes/' . $c->id)->assertOk()->assertSee('Contacto nuevo desde agenciavandu.com')->assertSee('Mensajes desde el sitio');

        $this->patch('/admin/clientes/' . $c->id . '/atendido')->assertRedirect();
        $this->assertFalse($c->fresh()->nuevo);
        $this->get('/admin/clientes?ver=nuevos')->assertSee('No hay contactos nuevos');
    }
}
