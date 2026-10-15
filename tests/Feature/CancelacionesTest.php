<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\EsteMes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cancelar proyectos y órdenes de compra con motivo: dejan de contar en ventas, por cobrar y gastos */
class CancelacionesTest extends TestCase
{
    use RefreshDatabase;

    private Presupuesto $cot;
    private Proyecto $p;
    private OrdenCompra $o;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $hoy = now(config('vandu.zona_horaria'))->toDateString();
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X']);
        $this->cot = Presupuesto::nuevaPara($c)->fill(['estado' => 'aceptada', 'aceptada_el' => $hoy]);
        $this->cot->save();
        $this->cot->conceptos()->create(['titulo' => 'Video corporativo', 'descripcion' => '', 'cantidad' => 1, 'precio' => 10000, 'orden' => 0]);
        $this->p = Proyecto::create(['cliente_id' => $c->id, 'presupuesto_id' => $this->cot->id, 'nombre' => 'Video corporativo', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 11600]);
        $this->p->pagos()->create(['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'monto' => 5800, 'orden' => 0, 'pagado_el' => $hoy]);
        $this->p->pagos()->create(['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 50, 'monto' => 5800, 'orden' => 1, 'vence_el' => now()->subDays(3)->toDateString()]);
        $prov = Proveedor::create(['empresa' => 'Bordados Mérida', 'nombre' => 'Carlos', 'rubro' => 'Bordados']);
        $this->o = OrdenCompra::create(['proveedor_id' => $prov->id, 'proyecto_id' => $this->p->id, 'titulo' => 'Renta de luces', 'estado' => 'enviada', 'con_iva' => true, 'iva_porcentaje' => 16]);
        $this->o->conceptos()->create(['descripcion' => 'Luces', 'cantidad' => 1, 'precio' => 1500, 'orden' => 0]);
    }

    public function test_cancelar_proyecto_lo_saca_de_ventas_y_por_cobrar(): void
    {
        $this->assertSame(10000.0, EsteMes::datos(false)['vendido']);
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee('$5,800');

        // Sin motivo no se cancela; tampoco desde el selector de estado
        $this->post(route('admin.proyectos.cancelar', $this->p), ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->put(route('admin.proyectos.update', $this->p), ['nombre' => 'Video', 'estado' => 'cancelado', 'monto_total' => 11600])->assertSessionHasErrors('estado');

        $this->get(route('admin.proyectos.show', $this->p))->assertOk()->assertSee('Cancelar proyecto')->assertSee($this->o->folio);
        $this->post(route('admin.proyectos.cancelar', $this->p), ['motivo' => 'El cliente pospuso la campaña', 'ordenes' => 1])
            ->assertRedirect(route('admin.proyectos.show', $this->p))
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'quedó como cancelada') && str_contains($m, '1 orden de compra'));

        $p = $this->p->fresh();
        $this->assertSame(['cancelado', 'El cliente pospuso la campaña'], [$p->estado, $p->motivo_cancelacion]);
        $this->assertSame('cancelada', $this->cot->fresh()->estado);
        $this->assertNotNull($this->cot->fresh()->aceptada_el, 'Conserva la fecha para cuando se reactive');
        $this->assertSame('cancelada', $this->o->fresh()->estado);
        $this->assertStringContainsString('El cliente pospuso la campaña', $this->o->fresh()->motivo_cancelacion);

        // Ventas, por cobrar y vencidos ya no lo cuentan; lo cobrado sigue
        $d = EsteMes::datos(true);
        $this->assertSame([0.0, 5800.0], [$d['vendido'], $d['cobrado']]);
        $this->assertSame(0.0, $p->por_cobrar);
        $this->assertSame('cancelado', $p->salud[0]);
        $this->get(route('admin.clientes.index'))->assertOk()->assertDontSee('$5,800');
        $this->get(route('admin.finanzas', ['iva' => 'con']))->assertOk();
        $this->get(route('admin.finanzas', ['seccion' => 'ventas']))->assertOk()->assertSee('Cancelada')->assertSee('El cliente pospuso la campaña');
        $this->get(route('admin.proyectos.show', $p))->assertOk()->assertSee('Proyecto cancelado')->assertSee('Motivo: El cliente pospuso la campaña')->assertSee('Reactivar');
        $this->get(route('admin.proyectos.index', ['filtro' => 'cancelado']))->assertOk()->assertSee('Video corporativo');
        $this->get(route('admin.presupuestos.edit', $this->cot))->assertOk()->assertSee('Cancelada');

        // El cliente lo ve cancelado, sin datos de pago ni para subir archivos
        $url = $p->url_publica;
        $portal = $p->cliente->portal_url;
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Este proyecto se canceló')->assertDontSee('Datos para tu pago')->assertDontSee('id="enviar-archivos"', false);
        $this->get($this->cot->url_publica)->assertOk()->assertSee('Esta cotización se canceló');
        $this->get($portal)->assertOk()->assertSee('Cancelado');
        $this->postJson(route('proyecto.subir', $p->token), ['accion' => 'iniciar', 'nombre' => 'a.zip'])->assertStatus(422);
    }

    public function test_reactivar_lo_devuelve_a_ventas(): void
    {
        $fecha = $this->cot->fresh()->aceptada_el->toDateString();
        $this->post(route('admin.proyectos.cancelar', $this->p), ['motivo' => 'Se canceló'])->assertSessionHas('ok');
        $this->assertSame('enviada', $this->o->fresh()->estado, 'Sin marcar la casilla, las órdenes no se tocan');

        $this->post(route('admin.proyectos.reactivar', $this->p))->assertSessionHas('ok');
        $p = $this->p->fresh();
        $this->assertSame(['activo', null, null], [$p->estado, $p->motivo_cancelacion, $p->cancelado_at]);
        $this->assertSame('aceptada', $this->cot->fresh()->estado);
        $this->assertSame($fecha, $this->cot->fresh()->aceptada_el->toDateString());
        $this->assertSame(10000.0, EsteMes::datos(false)['vendido']);

        // También desde el selector de estado
        $this->post(route('admin.proyectos.cancelar', $this->p), ['motivo' => 'Otra vez'])->assertSessionHas('ok');
        $this->put(route('admin.proyectos.update', $this->p), ['nombre' => 'Video corporativo', 'estado' => 'pausado', 'monto_total' => 11600])->assertSessionHas('ok');
        $this->assertSame(['pausado', 'aceptada'], [$this->p->fresh()->estado, $this->cot->fresh()->estado]);
    }

    public function test_cancelar_orden_de_compra_con_motivo(): void
    {
        $this->get(route('admin.ordenes.show', $this->o))->assertOk()->assertSee('Cancelar orden');
        $this->patch(route('admin.ordenes.estado', $this->o), ['estado' => 'cancelada'])->assertSessionHasErrors('motivo');
        $this->assertSame('enviada', $this->o->fresh()->estado);

        $this->patch(route('admin.ordenes.estado', $this->o), ['estado' => 'cancelada', 'motivo' => 'Se consiguió prestado'])->assertSessionHas('ok');
        $o = $this->o->fresh();
        $this->assertSame(['cancelada', 'Se consiguió prestado'], [$o->estado, $o->motivo_cancelacion]);
        $this->get(route('admin.ordenes.show', $o))->assertOk()->assertSee('Motivo: Se consiguió prestado');
        $this->get(route('admin.ordenes.index', ['ver' => 'cancelada']))->assertOk()->assertSee($o->folio);
        $this->get(route('admin.finanzas', ['seccion' => 'proveedores']))->assertOk()->assertDontSee($o->folio);

        auth()->logout();
        $this->get($o->url_proveedor)->assertOk()->assertSee('Esta orden se canceló');

        // Al pasarla a otro estado se reactiva y se borra el motivo
        $this->actingAs(User::factory()->create());
        $this->patch(route('admin.ordenes.estado', $o), ['estado' => 'en_proceso'])->assertSessionHas('ok');
        $this->assertSame(['en_proceso', null], [$o->fresh()->estado, $o->fresh()->motivo_cancelacion]);
    }
}
