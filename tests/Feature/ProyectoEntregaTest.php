<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Productos y servicios: dirección de entrega o instalación del proyecto, la manda el cliente o la captura el equipo */
class ProyectoEntregaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_cliente_manda_su_direccion_y_el_equipo_la_ve(): void
    {
        [$tienda, $dueno] = PlataformaController::crearCuenta(['nombre' => 'Muebles Losos', 'giro' => 'comercio', 'dueno_nombre' => 'Luis', 'dueno_email' => 'luis@losos.mx']);
        Cuentas::activar($tienda);
        $cli = Cliente::create(['nombre' => 'Mariana Uc', 'telefono' => '9991234567']);
        $p = Proyecto::create(['cliente_id' => $cli->id, 'nombre' => 'Cocina integral', 'tipo' => 'venta', 'estado' => 'activo']);

        // El cliente ve el aviso y el formulario en el enlace de su proyecto
        $this->get(route('proyecto.publico', $p->token))->assertOk()->assertSee('Necesitamos la dirección de entrega')->assertSee('Enviar mis datos')->assertSee('Mariana Uc');
        $this->post(route('proyecto.entrega.guardar', $p->token), ['nombre' => 'Mariana Uc', 'telefono' => '999 123 4567'])->assertSessionHasErrors('direccion');
        $this->post(route('proyecto.entrega.guardar', $p->token), ['nombre' => 'Mariana Uc', 'telefono' => '999 123 4567', 'direccion' => 'Calle 60 #500, Centro',
            'referencias' => 'Casa azul', 'notas' => 'Recibe de 9 a 2', 'lat' => '20.9674', 'lng' => '-89.6237'])
            ->assertRedirect(route('proyecto.publico', $p->token) . '#entrega');
        $p->refresh();
        $this->assertSame(['Calle 60 #500, Centro', 'cliente', 20.9674], [$p->entrega_direccion, $p->entrega_datos_por, $p->entrega_lat]);
        $this->get(route('proyecto.publico', $p->token))->assertSee('Calle 60 #500, Centro')->assertSee('Corregir mis datos')->assertDontSee('Necesitamos la dirección de entrega');

        // El equipo la ve con mapa y la puede corregir
        $this->actingAs($dueno);
        Cuentas::activar($tienda);
        $this->get(route('admin.proyectos.show', $p))->assertOk()->assertSee('Entrega o instalación')->assertSee('La mandó el cliente')
            ->assertSee('Recibe de 9 a 2')->assertSee('waze.com/ul?ll=20.9674', false);
        $this->put(route('admin.proyectos.entrega', $p), ['nombre' => 'Mariana Uc', 'telefono' => '9991234567', 'direccion' => 'Calle 62 #10, Centro'])->assertSessionHas('ok');
        $this->assertSame(['Calle 62 #10, Centro', 'equipo', null], [$p->fresh()->entrega_direccion, $p->fresh()->entrega_datos_por, $p->fresh()->entrega_lat]);

        // Terminado: ya no recibe cambios del cliente, pero se sigue viendo
        $p->update(['estado' => 'terminado']);
        $this->post(route('proyecto.entrega.guardar', $p->token), ['nombre' => 'X', 'telefono' => '1', 'direccion' => 'Otra'])->assertStatus(410);
        $this->get(route('proyecto.publico', $p->token))->assertSee('Calle 62 #10, Centro')->assertDontSee('Corregir mis datos');
    }

    public function test_solo_aplica_a_productos_y_servicios(): void
    {
        $agencia = User::factory()->create();
        Cuentas::activar(Cuentas::principalId());
        $cli = Cliente::create(['nombre' => 'Cliente agencia']);
        $p = Proyecto::create(['cliente_id' => $cli->id, 'nombre' => 'Sitio web', 'tipo' => 'web', 'estado' => 'activo']);
        $this->get(route('proyecto.publico', $p->token))->assertOk()->assertDontSee('Entrega o instalación');
        $this->post(route('proyecto.entrega.guardar', $p->token), ['nombre' => 'X', 'telefono' => '1', 'direccion' => 'Y'])->assertStatus(410);
        $this->actingAs($agencia)->get(route('admin.proyectos.show', $p))->assertOk()->assertDontSee('Entrega o instalación');
        $this->put(route('admin.proyectos.entrega', $p), ['nombre' => 'X', 'telefono' => '1', 'direccion' => 'Y'])->assertNotFound();
    }
}
