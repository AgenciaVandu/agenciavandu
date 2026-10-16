<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Cuenta;
use App\Models\Rol;
use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Cada negocio conecta su propio correo; se prueba antes de usarse */
class CorreoEnvioTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $losos;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->losos = Cuenta::create(['nombre' => 'Losos', 'giro' => 'comercio', 'activa' => true]);
        Cuentas::activar($this->losos);
        $rol = Rol::create(['nombre' => 'Super admin', 'todo' => true]);
        $this->dueno = User::factory()->create(['cuenta_id' => $this->losos->id, 'rol_id' => $rol->id, 'email' => 'luis@losos.mx']);
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['proveedor' => 'hosting', 'desde' => 'ventas@losos.mx', 'usuario' => 'ventas@losos.mx', 'clave' => 'secreta-123', 'host' => 'mail.losos.mx', 'puerto' => 465, 'cifrado' => 'ssl'];
    }

    public function test_se_conecta_si_la_prueba_sale_y_todo_se_envia_desde_su_correo(): void
    {
        Mail::fake();
        $this->actingAs($this->dueno)->get('/admin/negocio/correo')->assertOk()->assertSee('Usando el correo de la plataforma');
        $this->post('/admin/negocio/correo', $this->datos())->assertRedirect('/admin/negocio/correo')->assertSessionHas('ok');
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('luis@losos.mx') && $m->asunto === 'Correo de prueba de tu panel');

        $s = $this->losos->fresh()->ajuste('smtp');
        $this->assertTrue($s['activo']);
        $this->assertNotSame('secreta-123', $s['clave']);
        $this->assertSame('secreta-123', Crypt::decryptString($s['clave']));

        Cuentas::activar($this->losos->fresh());
        $this->assertSame('cuenta', config('mail.default'));
        $this->assertSame('mail.losos.mx', config('mail.mailers.cuenta.host'));
        $this->assertSame('ventas@losos.mx', config('vandu.correo.desde'));

        // Otra cuenta sigue con el correo de la plataforma
        Cuentas::activar(1);
        $this->assertNotSame('cuenta', config('mail.default'));

        // Cambiar datos sin volver a escribir la contraseña
        Cuentas::activar($this->losos->fresh());
        $this->post('/admin/negocio/correo', $this->datos(['clave' => '', 'desde' => 'hola@losos.mx', 'usuario' => 'hola@losos.mx']))->assertRedirect();
        $this->assertSame('secreta-123', Crypt::decryptString($this->losos->fresh()->ajuste('smtp.clave')));
        $this->get('/admin/negocio/correo')->assertSee('Conectado: hola@losos.mx');
    }

    public function test_si_la_prueba_falla_no_se_activa(): void
    {
        $this->actingAs($this->dueno)->from('/admin/negocio/correo')
            ->post('/admin/negocio/correo', $this->datos(['host' => '127.0.0.1', 'puerto' => 2525, 'cifrado' => 'ninguno']))
            ->assertRedirect('/admin/negocio/correo')->assertSessionHas('smtp_error');
        $s = $this->losos->fresh()->ajuste('smtp');
        $this->assertFalse($s['activo']);
        Cuentas::activar($this->losos->fresh());
        $this->assertNotSame('cuenta', config('mail.default'));
    }

    public function test_desconectar(): void
    {
        Mail::fake();
        $this->actingAs($this->dueno)->post('/admin/negocio/correo', $this->datos());
        $this->delete('/admin/negocio/correo')->assertRedirect('/admin/negocio/correo');
        $this->assertNull($this->losos->fresh()->ajuste('smtp'));
        Cuentas::activar($this->losos->fresh());
        $this->assertNotSame('cuenta', config('mail.default'));
    }

    public function test_solo_quien_configura_el_negocio(): void
    {
        $rol = Rol::create(['nombre' => 'Vendedor', 'permisos' => ['cotizaciones']]);
        $v = User::factory()->create(['cuenta_id' => $this->losos->id, 'rol_id' => $rol->id]);
        $this->actingAs($v)->get('/admin/negocio/correo')->assertForbidden();
    }
}
