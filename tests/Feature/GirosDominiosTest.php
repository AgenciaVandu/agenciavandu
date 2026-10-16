<?php

namespace Tests\Feature;

use App\Http\Middleware\CuentaPorDominio;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Giro;
use App\Models\Presupuesto;
use App\Models\Rol;
use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Tipos de empresa creados en Plataforma y el dominio propio de cada negocio */
class GirosDominiosTest extends TestCase
{
    use RefreshDatabase;

    private User $alvar;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        CuentaPorDominio::olvidar();
        $this->alvar = User::factory()->create(['name' => 'Alvar Buenfil', 'plataforma' => true, 'password' => bcrypt('secreto123')]);
    }

    private function crearGiro(array $extra = []): Giro
    {
        $this->actingAs($this->alvar)->post('/admin/plataforma/giros', $extra + [
            'nombre' => 'Distribuidora', 'icono' => 'bi-truck', 'descripcion' => 'Venden por mayoreo', 'modulos' => ['stock'], 'origen' => 'blanco',
            'roles' => [
                ['nombre' => 'Gerente', 'descripcion' => 'Todo menos usuarios', 'permisos' => ['resumen', 'ventas', 'cotizaciones', 'productos', 'redes']],
                ['nombre' => 'Vendedor', 'permisos' => ['ventas', 'cotizaciones', 'clientes']],
            ],
        ])->assertRedirect();
        return Giro::latest('id')->firstOrFail();
    }

    private function negocio(string $giro, string $nombre = 'Losos Mobiliario', string $correo = 'luis@losos.mx'): array
    {
        $this->actingAs($this->alvar)->post('/admin/plataforma', [
            'nombre' => $nombre, 'giro' => $giro, 'dueno_nombre' => 'Luis Losos', 'dueno_email' => $correo, 'enviar_correo' => 0,
        ])->assertRedirect('/admin/plataforma');
        return [Cuenta::where('nombre', $nombre)->firstOrFail(), User::sinCuenta()->where('email', $correo)->firstOrFail()];
    }

    public function test_nuevo_tipo_de_empresa_en_blanco_con_sus_roles_y_modulos(): void
    {
        $g = $this->crearGiro();
        $this->assertSame('distribuidora', $g->clave);
        $this->assertSame(['stock'], $g->modulos);
        $this->assertSame(['resumen', 'ventas', 'cotizaciones', 'productos'], $g->roles[0]['permisos'], 'Redes no aplica: no tiene ese módulo');
        $this->assertSame(['general'], array_keys($g->proyectos));
        $this->assertSame('producto', $g->costeo['componentes'][0]['clave']);

        [$c, $dueno] = $this->negocio('distribuidora');
        Cuentas::activar($c);
        $this->assertTrue($c->tiene('stock'));
        $this->assertSame(['Super admin', 'Gerente', 'Vendedor'], Rol::orderBy('orden')->pluck('nombre')->all());
        $this->assertSame(['general'], array_keys(config('vandu.proyectos')));

        // El dueño ve Productos y un solo tipo de proyecto para armar el suyo
        $dueno->forceFill(['password' => bcrypt('x'), 'invitacion_hash' => null])->save();
        $this->actingAs($dueno->fresh())->get('/admin/productos')->assertOk();
        $this->get('/admin/tipos-de-proyecto')->assertOk()->assertSee('Proyecto general');
    }

    public function test_copiar_los_tipos_de_proyecto_de_otro_giro_y_editar(): void
    {
        $g = $this->crearGiro(['nombre' => 'Constructora', 'origen' => 'copiar', 'origen_copia' => 'arquitectura', 'modulos' => []]);
        $this->assertSame(array_keys(config('vandu.giros.arquitectura.proyectos')), array_keys($g->proyectos));

        $this->put("/admin/plataforma/giros/{$g->id}", ['nombre' => 'Constructora y remodelación', 'icono' => 'bi-buildings', 'origen' => 'conservar', 'modulos' => ['stock'], 'roles' => []])->assertRedirect();
        $g->refresh();
        $this->assertSame('Constructora y remodelación', $g->nombre);
        $this->assertCount(count(config('vandu.giros.arquitectura.proyectos')), $g->proyectos);
        $this->assertTrue(collect($g->costeo['componentes'])->contains('clave', 'producto'));

        $this->get('/admin/plataforma')->assertOk()->assertSee('Constructora y remodelación')->assertSee('Creado en plataforma');
    }

    public function test_no_se_borra_un_tipo_de_empresa_en_uso_y_solo_la_plataforma_lo_maneja(): void
    {
        $g = $this->crearGiro();
        $this->negocio('distribuidora');
        $this->actingAs($this->alvar)->delete("/admin/plataforma/giros/{$g->id}")->assertSessionHasErrors('giro');
        $this->assertModelExists($g);

        $normal = User::factory()->create();
        $this->actingAs($normal)->get('/admin/plataforma/giros/nuevo')->assertForbidden();
    }

    public function test_dominio_propio_con_su_marca_y_solo_su_equipo(): void
    {
        [$c, $dueno] = $this->negocio('comercio');
        $dueno->forceFill(['password' => bcrypt('secreto123'), 'invitacion_hash' => null])->save();
        $this->actingAs($this->alvar)->put("/admin/plataforma/{$c->id}", ['nombre' => $c->nombre, 'giro' => 'comercio', 'activa' => 1, 'dominio' => 'https://App.Losos.mx/admin'])->assertRedirect();
        $this->assertSame('app.losos.mx', $c->fresh()->dominio);
        auth()->logout();

        // El sitio de la agencia no existe en ese dominio
        $this->get('http://app.losos.mx/')->assertRedirect('/admin');
        $this->get('http://app.losos.mx/servicios')->assertRedirect('/admin');
        $this->get('http://app.losos.mx/admin/login')->assertOk()->assertSee('Entra con tu correo de Losos Mobiliario')->assertSee('app.losos.mx');

        // Alguien de otro negocio no entra ahí
        $otro = User::factory()->create(['email' => 'pedro@vandu.mx', 'password' => bcrypt('secreto123'), 'cuenta_id' => 1]);
        $this->post('http://app.losos.mx/admin/login', ['email' => 'pedro@vandu.mx', 'password' => 'secreto123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Su equipo sí, y ve su cuenta
        $this->post('http://app.losos.mx/admin/login', ['email' => 'luis@losos.mx', 'password' => 'secreto123'])->assertRedirect();
        $this->assertAuthenticatedAs($dueno);
        $this->get('http://app.losos.mx/admin/productos')->assertOk();
    }

    public function test_los_enlaces_del_cliente_salen_con_el_dominio_del_negocio(): void
    {
        [$c] = $this->negocio('comercio');
        $c->update(['dominio' => 'app.losos.mx']);
        Cuentas::olvidarDominios();

        Cuentas::activar($c);
        $cli = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Oficinas']);
        $p = Presupuesto::nuevaPara($cli);
        $p->save();
        $this->assertStringStartsWith('https://app.losos.mx/cotizacion/', $p->url_publica);
        $this->assertStringStartsWith('https://app.losos.mx/cliente/', $cli->portal_url);

        // La cuenta principal sigue con la dirección de siempre
        Cuentas::activar(1);
        $cli2 = Cliente::create(['nombre' => 'Hotel']);
        $this->assertStringStartsWith(rtrim(config('app.url'), '/') . '/cliente/', $cli2->portal_url);
    }

    public function test_un_dominio_no_se_repite_ni_puede_ser_el_principal(): void
    {
        [$a] = $this->negocio('comercio', 'Uno', 'uno@x.mx');
        [$b] = $this->negocio('comercio', 'Dos', 'dos@x.mx');
        $this->actingAs($this->alvar)->put("/admin/plataforma/{$a->id}", ['nombre' => 'Uno', 'giro' => 'comercio', 'activa' => 1, 'dominio' => 'app.uno.mx']);
        $this->put("/admin/plataforma/{$b->id}", ['nombre' => 'Dos', 'giro' => 'comercio', 'activa' => 1, 'dominio' => 'app.uno.mx'])->assertSessionHasErrors('dominio');
        $principal = parse_url(config('app.url'), PHP_URL_HOST);
        $this->put("/admin/plataforma/{$b->id}", ['nombre' => 'Dos', 'giro' => 'comercio', 'activa' => 1, 'dominio' => $principal])->assertSessionHasErrors('dominio');
        $this->put("/admin/plataforma/{$b->id}", ['nombre' => 'Dos', 'giro' => 'comercio', 'activa' => 1, 'dominio' => 'no es dominio'])->assertSessionHasErrors('dominio');
    }

    public function test_negocio_con_inventario_arranca_con_productos_de_ejemplo_que_se_quitan(): void
    {
        [$c, $dueno] = $this->negocio('comercio');
        Cuentas::activar($c);
        $this->assertSame(10, \App\Models\Producto::where('ejemplo', true)->count());
        $this->assertEquals(12, \App\Models\Producto::where('clave', 'EJ-001')->value('existencia'));
        $this->assertSame(1, \App\Models\Producto::activos()->bajos()->where('existencia', '>', 0)->count());   // escritorio en su mínimo

        // Otro negocio no ve esos productos; uno sin inventario no los recibe
        [$agencia] = $this->negocio('agencia', 'Estudio', 'e@x.mx');
        Cuentas::activar($agencia);
        $this->assertSame(0, \App\Models\Producto::count());

        $dueno->forceFill(['password' => bcrypt('x'), 'invitacion_hash' => null])->save();
        $this->actingAs($dueno->fresh())->get('/admin/productos')->assertOk()->assertSee('productos de ejemplo');
        $this->delete('/admin/productos/ejemplos')->assertRedirect('/admin/productos');
        Cuentas::activar($c);
        $this->assertSame(0, \App\Models\Producto::count());
        $this->assertSame(0, \App\Models\ProductoCategoria::count());

        // Se pueden volver a cargar desde la página vacía
        $this->post('/admin/productos/ejemplos')->assertRedirect('/admin/productos');
        $this->assertSame(10, \App\Models\Producto::count());
    }
}
