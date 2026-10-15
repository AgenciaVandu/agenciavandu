<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Prospecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** El botón de WhatsApp del sitio guarda al prospecto, avisa por correo y devuelve el enlace con el mensaje */
class WhatsappSitioTest extends TestCase
{
    use RefreshDatabase;

    private float $puntaje = 0.9;

    private function enviar(array $extra = [])
    {
        return $this->postJson('/whatsapp', $extra + [
            'nombre' => 'Laura Canché', 'telefono' => '999 123 4567', 'empresa' => 'Hotel Laura',
            'email' => 'Laura@Ejemplo.mx', 'servicio' => 'Página web', 'mensaje' => 'Quiero renovar mi sitio.',
            'pagina' => '/', 'g-recaptcha-response' => 'ok',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['www.google.com/recaptcha/*' => fn () => Http::response(['success' => true, 'score' => $this->puntaje])]);
        Mail::fake();
    }

    public function test_guarda_el_prospecto_y_devuelve_el_mensaje_listo(): void
    {
        $r = $this->enviar()->assertOk()->assertJson(['success' => true]);

        $p = Prospecto::sole();
        $this->assertSame('whatsapp', $p->origen);
        $this->assertTrue($p->nuevo);
        $this->assertSame('Laura Canché', $p->nombre);
        $this->assertSame('Hotel Laura', $p->empresa);
        $this->assertSame('laura@ejemplo.mx', $p->email);
        $this->assertSame('Página web', $p->interes);
        $this->assertSame('Llegó desde el botón de WhatsApp del sitio', $p->eventos()->where('tipo', 'alta')->value('texto'));

        $url = $r->json('whatsapp');
        $this->assertStringStartsWith('https://wa.me/' . config('vandu.whatsapp') . '?text=', $url);
        $texto = rawurldecode(explode('?text=', $url)[1]);
        $this->assertStringContainsString('Hola, soy Laura Canché de Hotel Laura.', $texto);
        $this->assertStringContainsString('Me interesa: Página web.', $texto);
        $this->assertStringContainsString('Quiero renovar mi sitio.', $texto);
        $this->assertStringContainsString('Mi correo: Laura@Ejemplo.mx', $texto);
    }

    public function test_manda_la_copia_por_correo_con_la_plantilla_de_vandu(): void
    {
        $this->enviar()->assertOk();

        Mail::assertSent(CorreoVandu::class, function (CorreoVandu $m) {
            return $m->hasTo(config('vandu.correo.avisos'))
                && str_contains($m->asunto, 'Laura Canché')
                && $m->resumen['WhatsApp'] === '999 123 4567'
                && str_contains($m->cuerpo, 'Me interesa: Página web.')
                && $m->boton === 'Abrir en el portal'
                && str_contains((string) $m->url, '/admin/ventas/prospectos/');
        });
    }

    public function test_si_vuelve_a_escribir_no_se_duplica(): void
    {
        $this->enviar()->assertOk();
        $this->enviar(['email' => '', 'telefono' => '+52 1 9991234567', 'servicio' => 'Video con IA'])->assertOk();

        $this->assertSame(1, Prospecto::count());
        $this->assertSame('Video con IA', Prospecto::sole()->interes);
        $this->assertSame(2, Prospecto::sole()->eventos()->where('tipo', 'mensaje')->count());
    }

    public function test_pide_nombre_whatsapp_y_servicio(): void
    {
        $this->postJson('/whatsapp', [])->assertStatus(422)->assertJsonValidationErrors(['nombre', 'telefono', 'servicio']);
        $this->assertSame(0, Prospecto::count());
    }

    public function test_si_parece_bot_no_guarda_pero_igual_abre_whatsapp(): void
    {
        putenv('RECAPTCHA_SECRET_KEY=secreto');
        $_ENV['RECAPTCHA_SECRET_KEY'] = $_SERVER['RECAPTCHA_SECRET_KEY'] = 'secreto';
        $this->puntaje = 0.1;

        try {
            $this->enviar()->assertOk()->assertJsonPath('success', true);
            $this->assertSame(0, Prospecto::count());
            Mail::assertNothingSent();
        } finally {
            putenv('RECAPTCHA_SECRET_KEY');
            unset($_ENV['RECAPTCHA_SECRET_KEY'], $_SERVER['RECAPTCHA_SECRET_KEY']);
        }
    }

    public function test_si_falla_el_correo_igual_guarda_y_abre_whatsapp(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));
        $this->enviar()->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, Prospecto::count());
    }
}
