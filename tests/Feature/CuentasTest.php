<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Varios negocios en la plataforma: cada uno ve solo lo suyo, con su marca y su giro */
class CuentasTest extends TestCase
{
    use RefreshDatabase;

    private User $alvar;
    private Cliente $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->alvar = User::factory()->create(['name' => 'Alvar Buenfil', 'plataforma' => true]);
        $this->hotel = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel Casa Lecanda']);
        $p = Presupuesto::nuevaPara($this->hotel);
        $p->save();
        $p->conceptos()->create(['descripcion' => 'Sitio web', 'cantidad' => 1, 'precio' => 20000, 'orden' => 0]);
    }

    /** Da de alta un estudio desde la plataforma y regresa [cuenta, dueño] */
    private function estudio(string $nombre = 'Estudio Ceiba', string $correo = 'mario@ceiba.mx'): array
    {
        $this->actingAs($this->alvar)->post('/admin/plataforma', [
            'nombre' => $nombre, 'giro' => 'arquitectura', 'dueno_nombre' => 'Mario Ek', 'dueno_email' => $correo, 'enviar_correo' => 1,
        ])->assertRedirect('/admin/plataforma')->assertSessionHas('enlace');
        $c = Cuenta::where('nombre', $nombre)->firstOrFail();
        $dueno = User::sinCuenta()->where('email', $correo)->firstOrFail();
        return [$c, $dueno];
    }

    public function test_alta_de_cuenta_con_roles_y_tipos_de_su_giro(): void
    {
        [$c, $mario] = $this->estudio();
        $this->assertSame($c->id, $mario->cuenta_id);
        $this->assertTrue($mario->esSuperAdmin());
        $this->assertTrue($mario->pendiente);
        $roles = \App\Models\Rol::sinCuenta()->where('cuenta_id', $c->id)->pluck('nombre')->all();
        $this->assertSame(['Super admin', 'Arquitecto líder', 'Residente de obra', 'Proyectista', 'Administración'], $roles);
        Mail::assertSent(\App\Mail\CorreoVandu::class, fn ($m) => $m->hasTo('mario@ceiba.mx') && str_contains($m->asunto, 'Estudio Ceiba'));
        // Quien no administra la plataforma no puede crear cuentas
        $this->actingAs($mario)->get('/admin/plataforma')->assertForbidden();
    }

    public function test_cada_negocio_ve_solo_lo_suyo(): void
    {
        [$c, $mario] = $this->estudio();
        $this->actingAs($mario);

        $this->get('/admin/clientes')->assertOk()->assertDontSee('Hotel Casa Lecanda');
        $this->get("/admin/clientes/{$this->hotel->id}")->assertNotFound();
        $this->get('/admin/presupuestos')->assertOk()->assertDontSee('CT-0001');
        $this->post('/admin/clientes', ['nombre' => 'Luis', 'empresa' => 'Casa Montejo'])->assertRedirect();
        $casa = Cliente::sinCuenta()->where('empresa', 'Casa Montejo')->firstOrFail();
        $this->assertSame($c->id, $casa->cuenta_id);

        // Su folio empieza en 1 aunque Vandu ya tenga cotizaciones
        $p = Cuentas::como($c, function () use ($casa) { $p = Presupuesto::nuevaPara($casa); $p->save(); return $p; });
        $this->assertSame('CT-0001', $p->folio);
        $this->assertSame(2, Presupuesto::sinCuenta()->where('folio', 'CT-0001')->count());

        // No puede asignar a una tarea el cliente de otro negocio
        $this->post('/admin/tareas', ['titulo' => 'X', 'cliente_id' => $this->hotel->id])->assertSessionHasErrors('cliente_id');

        // Vandu tampoco ve lo del estudio
        $this->actingAs($this->alvar->fresh());
        $this->get('/admin/clientes')->assertSee('Hotel Casa Lecanda')->assertDontSee('Casa Montejo');
        $this->get("/admin/clientes/{$casa->id}")->assertNotFound();
    }

    public function test_el_giro_define_tipos_y_modulos(): void
    {
        [, $mario] = $this->estudio();
        $this->actingAs($mario);
        $this->get('/admin/tipos-de-proyecto')->assertOk()->assertSee('Construcción de obra')->assertSee('Proyecto arquitectónico')->assertDontSee('Desarrollo web');
        $this->get('/admin/redes')->assertForbidden(); // redes es de agencias
        $this->get('/admin/usuarios?tab=roles')->assertOk()->assertDontSee('Calendario de contenido');
        $this->get('/admin/tareas')->assertOk()->assertDontSee('Redes sociales');

        // Editar sus tipos no cambia los de Vandu
        $datos = ['nombre' => 'Obra nueva', 'icono' => 'bi-kanban', 'etapas' => [['ref' => 'a', 'clave' => '', 'nombre' => 'Todo', 'dias' => 5]], 'pagos' => [['concepto' => 'Pago', 'porcentaje' => 100, 'antes_de' => '']]];
        $this->put('/admin/tipos-de-proyecto/construccion', ['datos' => json_encode($datos)])->assertRedirect();
        $this->actingAs($this->alvar->fresh())->get('/admin/tipos-de-proyecto'); // el aviso de guardado se queda en la sesión
        $this->actingAs($this->alvar->fresh())->get('/admin/tipos-de-proyecto')->assertSee('Desarrollo web')->assertDontSee('Obra nueva');
        $this->get('/admin/redes')->assertOk();
    }

    public function test_enlace_del_cliente_sale_con_la_marca_del_estudio(): void
    {
        $clabeVandu = config('vandu.pago.clabe');
        [$c, $mario] = $this->estudio();
        $this->actingAs($mario)->post('/admin/negocio', [
            'nombre' => 'Estudio Ceiba', 'marca' => ['ciudad' => 'Mérida'], 'emisor' => ['nombre' => 'Mario Ek', 'email' => 'mario@ceiba.mx', 'telefono' => '999 000 1111'],
            'pago' => ['banco' => 'BBVA', 'clabe' => '012345678901234567'], 'folio_prefijo' => 'EC-',
        ])->assertRedirect('/admin/negocio');

        $p = Cuentas::como($c->fresh(), function () {
            $cli = Cliente::create(['nombre' => 'Luis', 'empresa' => 'Casa Montejo']);
            $p = Presupuesto::nuevaPara($cli);
            $p->estado = 'enviada';
            $p->save();
            $p->conceptos()->create(['descripcion' => 'Proyecto ejecutivo', 'cantidad' => 1, 'precio' => 50000, 'orden' => 0]);
            return $p;
        });
        $this->assertSame('EC-0001', $p->folio);
        $this->assertSame('BBVA', $p->banco);

        auth()->logout();
        $html = $this->get("/cotizacion/{$p->token}")->assertOk()->getContent();
        $this->assertStringContainsString('Estudio Ceiba', $html);
        $this->assertStringNotContainsString('Agencia Vandu', $html);
        $this->assertStringNotContainsString($clabeVandu, $html); // ni la CLABE de Vandu
        // Y el de Vandu sigue saliendo como Vandu
        $vandu = Presupuesto::sinCuenta()->where('cuenta_id', 1)->first();
        $this->get("/cotizacion/{$vandu->token}")->assertOk()->assertSee('Agencia Vandu');
    }

    public function test_plataforma_entra_a_otra_cuenta_y_suspende(): void
    {
        [$c, $mario] = $this->estudio();
        Cuentas::como($c, fn () => Cliente::create(['nombre' => 'Luis', 'empresa' => 'Casa Montejo']));

        $this->actingAs($this->alvar)->post("/admin/plataforma/{$c->id}/entrar")->assertRedirect();
        $this->get('/admin/clientes')->assertSee('Casa Montejo')->assertDontSee('Hotel Casa Lecanda')->assertSee('Estás viendo el panel de');
        $this->post('/admin/plataforma/salir')->assertRedirect('/admin/plataforma');
        $this->get('/admin/clientes')->assertSee('Hotel Casa Lecanda');

        // Suspender: su equipo ya no entra
        $this->put("/admin/plataforma/{$c->id}", ['nombre' => $c->nombre, 'giro' => 'arquitectura'])->assertRedirect();
        $this->assertFalse($c->fresh()->activa);
        $this->actingAs($mario->fresh())->get('/admin/tareas')->assertRedirect(route('login'));
        // La cuenta principal no se puede suspender
        $this->actingAs($this->alvar->fresh())->put('/admin/plataforma/1', ['nombre' => 'Agencia Vandu', 'giro' => 'agencia']);
        $this->assertTrue(Cuenta::find(1)->activa);
    }

    public function test_la_invitacion_del_estudio_lleva_su_marca_y_entra_a_su_cuenta(): void
    {
        $this->estudio();
        $url = session('enlace')['url'];
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Mario');
        $this->post($url, ['name' => 'Mario Ek', 'password' => 'secreta123', 'password_confirmation' => 'secreta123'])->assertRedirect();
        $this->get('/admin')->assertOk()->assertDontSee('Hotel Casa Lecanda');
        $this->post('/admin/logout');
        $this->post('/admin/login', ['email' => 'mario@ceiba.mx', 'password' => 'secreta123'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
