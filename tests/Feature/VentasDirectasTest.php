<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Support\Cuentas;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Venta directa de productos: mostrador en una pantalla, pedidos con envío (datos que llena el cliente o el vendedor),
 * crédito con abonos, cotización de productos que termina en venta y todo sumado en Finanzas.
 */
class VentasDirectasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendedor;
    private Producto $paleta;
    private Producto $agua;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-23 12:00:00', 'America/Merida'));
        Cuenta::whereKey(1)->update(['giro' => 'comercio']);
        Cuentas::activar(1);
        $this->admin = User::factory()->create(['name' => 'Laura Dueña']);
        $rol = Rol::create(['nombre' => 'Vendedor', 'permisos' => ['vender', 'cotizaciones', 'clientes']]);
        $this->vendedor = User::factory()->create(['name' => 'Pedro Canul', 'rol_id' => $rol->id]);
        $this->paleta = Producto::create(['nombre' => 'Paleta de mango', 'clave' => 'PAL-01', 'precio' => 25, 'costo' => 9, 'unidad' => 'pieza']);
        $this->agua = Producto::create(['nombre' => 'Agua de horchata 1 L', 'clave' => 'AGU-01', 'precio' => 40, 'costo' => 12, 'unidad' => 'pieza']);
        Inventario::mover($this->paleta, 'entrada', 200, 'Inicial');
        Inventario::mover($this->agua, 'entrada', 30, 'Inicial');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venta(array $extra = []): Venta
    {
        $this->post(route('admin.pedidos.store'), $extra + [
            'productos' => [['producto_id' => $this->paleta->id, 'cantidad' => 100, 'precio' => 25], ['producto_id' => $this->agua->id, 'cantidad' => 2, 'precio' => 40]],
            'pago' => 'contado', 'forma' => 'efectivo', 'entrega' => 'ahora',
        ])->assertSessionHasNoErrors()->assertRedirect();
        return Venta::latest('id')->firstOrFail();
    }

    public function test_venta_de_mostrador_en_un_paso(): void
    {
        $this->actingAs($this->vendedor)->get(route('admin.pedidos.create'))->assertOk()->assertSee('Nueva venta');
        $v = $this->venta(['con_iva' => 1]);
        $this->assertSame(['V-0001', 2580.0, 412.8, 2992.8, 2992.8, 'entregada', true], [$v->folio, $v->subtotal, $v->iva, $v->total, $v->pagado, $v->estado, $v->pagada]);
        $this->assertNull($v->cliente_id, 'Mostrador: sin cliente');
        $this->assertEquals(100, $this->paleta->fresh()->existencia);
        $this->assertEquals(28, $this->agua->fresh()->existencia);
        $this->get(route('admin.pedidos.show', $v))->assertOk()->assertSee('Mostrador')->assertSee('Pagada');
        $this->get(route('admin.pedidos.nota', $v))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('admin.pedidos'))->assertOk()->assertSee('V-0001')->assertSee('$2,993');

        // Cancelar regresa la mercancía y deja de contar
        $this->post(route('admin.pedidos.cancelar', $v))->assertSessionHas('ok');
        $this->assertEquals(200, $this->paleta->fresh()->existencia);
        $this->assertSame('cancelada', $v->fresh()->estado);

        // Quien no tiene "Vender" no entra
        $otro = User::factory()->create(['rol_id' => Rol::create(['nombre' => 'Diseño', 'permisos' => ['clientes']])->id]);
        $this->actingAs($otro)->get(route('admin.pedidos.create'))->assertForbidden();
    }

    public function test_credito_con_abonos(): void
    {
        $this->actingAs($this->vendedor);
        $this->post(route('admin.pedidos.store'), ['productos' => [['producto_id' => $this->paleta->id, 'cantidad' => 10, 'precio' => 25]], 'pago' => 'credito', 'entrega' => 'ahora'])
            ->assertSessionHasErrors('cliente_nombre');

        $v = $this->venta(['pago' => 'parcial', 'monto' => 1000, 'dias' => 30, 'cliente_nombre' => 'Tienda Doña Mary', 'cliente_telefono' => '999 111 2233']);
        $this->assertSame([2580.0, 1000.0, 1580.0, true, '2026-11-22'], [$v->total, $v->pagado, $v->saldo, $v->credito, $v->vence_el->toDateString()]);
        $this->assertSame('Tienda Doña Mary', $v->cliente->nombre, 'A crédito se guarda el cliente');

        // Otra venta al mismo teléfono usa el mismo cliente
        $v2 = $this->venta(['pago' => 'credito', 'cliente_nombre' => 'Mary', 'cliente_telefono' => '9991112233']);
        $this->assertSame($v->cliente_id, $v2->cliente_id);
        $this->assertSame(1, Cliente::count());

        $this->post(route('admin.pedidos.abonar', $v), ['monto' => 2000, 'forma' => 'transferencia'])->assertSessionHasErrors('monto');
        $this->post(route('admin.pedidos.abonar', $v), ['monto' => 1580, 'forma' => 'transferencia'])->assertSessionHas('ok', 'Pago registrado: la venta quedó pagada.');
        $this->assertTrue($v->fresh()->pagada);
        $this->get(route('admin.pedidos', ['ver' => 'cobrar']))->assertSee($v2->folio)->assertDontSee($v->folio . ' ·');
    }

    public function test_envio_con_datos_que_llena_el_cliente_y_tarifa_por_km(): void
    {
        $c = Cuenta::find(1);
        $c->update(['ajustes' => array_merge($c->ajustes ?? [], ['envios' => ['modo' => 'km', 'base' => 30, 'por_km' => 8, 'minimo' => 40, 'factor' => 1.3, 'lat' => 20.9674, 'lng' => -89.5926]])]);
        Cuentas::activar(1);

        $this->actingAs($this->vendedor);
        $this->post(route('admin.pedidos.store'), ['productos' => [['producto_id' => $this->paleta->id, 'cantidad' => 20, 'precio' => 25]], 'pago' => 'contado', 'forma' => 'efectivo',
            'entrega' => 'envio', 'datos' => 'cliente'])->assertSessionHasErrors('cliente_telefono');
        $v = $this->venta(['productos' => [['producto_id' => $this->paleta->id, 'cantidad' => 20, 'precio' => 25]], 'entrega' => 'envio', 'datos' => 'cliente',
            'cliente_nombre' => 'Carlos', 'cliente_telefono' => '9997776655']);
        $this->assertSame(['por_surtir', true, null, 500.0, true], [$v->estado, $v->lleva_envio, $v->datos_at, $v->total, $v->pagada]);
        $this->get(route('admin.pedidos.show', $v))->assertSee('Esperando la dirección')->assertSee('wa.me/529997776655', false);

        // El cliente abre su enlace, pone su dirección y su ubicación (unos 5 km al norte)
        auth()->logout();
        $this->get($v->url_cliente)->assertOk()->assertSee('¿A dónde te lo llevamos?')->assertSee('Usar mi ubicación');
        $this->post(route('pedido.publico.guardar', $v->token), ['nombre' => 'Carlos Pech', 'telefono' => '9997776655', 'direccion' => 'Calle 60 491, Centro',
            'referencias' => 'Portón negro', 'lat' => 21.0124, 'lng' => -89.5926])->assertRedirect($v->url_cliente);
        $v->refresh();
        $this->assertSame(['cliente', 6.5, 82.0, 582.0, 82.0], [$v->datos_por, $v->envio_km, $v->envio_costo, $v->total, $v->saldo]);
        $this->get($v->url_cliente)->assertSee('Recibimos tus datos')->assertSee('6.5 km');
        $this->get(route('pedido.publico.nota', $v->token))->assertOk();

        // Ya en camino no se puede cambiar desde el enlace
        $this->actingAs($this->vendedor)->patch(route('admin.pedidos.estado', $v), ['estado' => 'en_camino'])->assertSessionHas('ok');
        auth()->logout();
        $this->post(route('pedido.publico.guardar', $v->token), ['nombre' => 'X', 'telefono' => '1', 'direccion' => 'Otra'])->assertStatus(410);

        // El vendedor puede corregir y poner el costo a mano
        $this->actingAs($this->vendedor)->put(route('admin.pedidos.envio', $v), ['envio_direccion' => 'Calle 60 491, Centro', 'envio_costo' => 50, 'repartidor_id' => $this->vendedor->id])->assertSessionHas('ok');
        $this->assertSame([50.0, true, 'vendedor', $this->vendedor->id], [$v->fresh()->envio_costo, $v->fresh()->envio_costo_manual, $v->fresh()->datos_por, $v->fresh()->repartidor_id]);
        $this->get(route('admin.pedidos.show', $v))->assertSee('Abrir en Google Maps');
    }

    public function test_el_vendedor_llena_los_datos_de_envio(): void
    {
        $c = Cuenta::find(1);
        $c->update(['ajustes' => array_merge($c->ajustes ?? [], ['envios' => ['modo' => 'fijo', 'fijo' => 60]])]);
        Cuentas::activar(1);
        $this->actingAs($this->vendedor);
        $v = $this->venta(['productos' => [['producto_id' => $this->agua->id, 'cantidad' => 3, 'precio' => 40]], 'entrega' => 'envio', 'datos' => 'vendedor',
            'envio_direccion' => 'Calle 21 #100, Itzimná', 'envio_lat' => 21.0, 'envio_lng' => -89.6, 'pago' => 'contado']);
        $this->assertSame(['vendedor', 60.0, 180.0, 'por_surtir'], [$v->datos_por, $v->envio_costo, $v->total, $v->estado]);
        $this->assertTrue($v->pagada, 'De contado se cobra con el envío incluido');
    }

    public function test_cotizacion_de_productos_termina_en_venta_y_suma_en_finanzas(): void
    {
        $cliente = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Eventos Ana']);
        $this->actingAs($this->admin)->post('/admin/presupuestos', [
            'cliente_id' => $cliente->id, 'cliente_nombre' => 'Ana', 'cliente_empresa' => 'Eventos Ana', 'fecha' => '2026-10-20', 'titulo' => 'Paletas para boda',
            'tipo' => array_key_first(config('vandu.proyectos')), 'emisor_nombre' => 'Laura', 'modo_iva' => 'mas_iva', 'iva_porcentaje' => 16,
            'vigente_hasta' => now()->addDays(10)->format('Y-m-d\TH:i'), 'estado' => 'borrador',
            'conceptos' => [['producto_id' => $this->paleta->id, 'titulo' => 'Paleta de mango', 'cantidad' => 150, 'precio' => 22]],
        ])->assertRedirect();
        $cot = Presupuesto::latest('id')->firstOrFail();
        $cot->update(['estado' => 'aceptada', 'aceptada_el' => '2026-10-21']);
        $this->assertEquals(50, $this->paleta->fresh()->existencia);
        $this->assertTrue($cot->fresh()->solo_productos);
        $this->get(route('admin.presupuestos.edit', $cot))->assertSee('Registrar la venta')->assertSee('Mejor un proyecto');

        $this->post(route('admin.pedidos.desde-cotizacion', $cot))->assertRedirect();
        $v = Venta::firstOrFail();
        $this->assertSame([$cot->id, $cliente->id, 3300.0, 528.0, 3828.0, 'por_surtir'], [$v->presupuesto_id, $v->cliente_id, $v->subtotal, $v->iva, $v->total, $v->estado]);
        $this->assertEquals(50, $this->paleta->fresh()->existencia, 'El inventario ya lo había descontado la cotización');
        $this->post(route('admin.pedidos.desde-cotizacion', $cot))->assertRedirect(route('admin.pedidos.show', $v));
        $this->assertSame(1, Venta::count(), 'No se duplica');
        $this->get(route('admin.presupuestos.edit', $cot))->assertSee('Venta ' . $v->folio);

        // Finanzas: la venta de mostrador suma a vendido; los cobros de ambas a cobrado
        $this->post(route('admin.pedidos.abonar', $v), ['monto' => 3828, 'forma' => 'transferencia']);
        $this->actingAs($this->vendedor);
        $this->venta();   // 2,580 sin IVA, de contado
        $this->actingAs($this->admin);
        $d = \App\Support\EsteMes::datos(true);
        $this->assertSame([3828.0 + 2580.0, 3828.0 + 2580.0], [$d['cobrado'], $d['vendido']]);
        $this->get(route('admin.finanzas', ['periodo' => 'mes', 'iva' => 'con']))->assertOk()->assertSee('$6,408');
    }
}
