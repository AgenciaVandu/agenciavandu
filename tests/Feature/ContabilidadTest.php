<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Finanzas\Contabilidad;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Finanzas > Contabilidad: estado de resultados, IVA del mes y gastos con o sin factura */
class ContabilidadTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tienda;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00', 'America/Merida'));
        $alvar = User::factory()->create(['plataforma' => true]);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        $this->actingAs($alvar)->put(route('admin.plataforma.update', $this->tienda), [
            'nombre' => $this->tienda->nombre, 'giro' => 'comercio', '_extras' => 1, 'extras' => ['gastos'], 'gastos_tope' => 150,
        ])->assertSessionHas('ok');
        Cuentas::activar($this->tienda->refresh());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_resultados_iva_y_facturas_faltantes(): void
    {
        $paleta = Producto::create(['nombre' => 'Paleta de mango', 'precio' => 25, 'costo' => 9, 'unidad' => 'pieza']);
        $agua = Producto::create(['nombre' => 'Agua de horchata', 'precio' => 40, 'costo' => 12, 'unidad' => 'pieza']);
        Inventario::mover($paleta, 'entrada', 200, 'Inicial');
        Inventario::mover($agua, 'entrada', 20, 'Inicial');

        $this->actingAs($this->dueno)->post(route('admin.pedidos.store'), [
            'productos' => [['producto_id' => $paleta->id, 'cantidad' => 100, 'precio' => 25], ['producto_id' => $agua->id, 'cantidad' => 2, 'precio' => 40]],
            'pago' => 'contado', 'forma' => 'efectivo', 'entrega' => 'ahora', 'con_iva' => 1,
        ])->assertSessionHasNoErrors();

        $cat = array_key_first(Gasto::CATEGORIAS);
        Gasto::create(['fecha' => '2026-10-10', 'concepto' => 'Fruta del mercado', 'categoria' => $cat, 'subtotal' => 1000, 'iva' => 160, 'total' => 1160, 'origen' => 'xml', 'xml' => 'gastos/a.xml']);
        Gasto::create(['fecha' => '2026-10-12', 'concepto' => 'Gasolina reparto', 'categoria' => $cat, 'subtotal' => 500, 'iva' => 80, 'total' => 580, 'origen' => 'foto', 'foto' => 'gastos/b.jpg']);
        Gasto::create(['fecha' => '2026-09-12', 'concepto' => 'Del mes pasado', 'categoria' => $cat, 'subtotal' => 999, 'iva' => 0, 'total' => 999, 'origen' => 'manual']);

        $c = Contabilidad::datos('2026-10-01', '2026-10-24');
        $r = $c['resultados'];
        $this->assertEquals([2580, 924, 1656, 1500, 156], [$r['ventas'], $r['costo'], $r['bruta'], $r['gastos'], $r['utilidad']]);
        $this->assertSame(0, $r['sinCosto']);
        $this->assertEquals([412.8, 160, 252.8, 80], [$c['iva']['trasladado'], $c['iva']['acreditable'], $c['iva']['saldo'], $c['iva']['perdido']]);
        $this->assertEquals([1160, 580, 1, 1], [$c['comprobantes']['con'], $c['comprobantes']['sin'], $c['comprobantes']['nCon'], $c['comprobantes']['nSin']]);

        // La pantalla, por defecto en el mes en curso
        $this->get(route('admin.finanzas', 'contabilidad'))->assertOk()
            ->assertSee('Estado de resultados')->assertSee('$252.80')->assertSee('IVA a pagar')
            ->assertSee('Gasolina reparto')->assertSee('Subir factura')->assertSee('17 de noviembre')
            ->assertDontSee('Del mes pasado');

        // Desde el aviso: solo los gastos sin factura
        $this->get(route('admin.gastos', ['sin_factura' => 1]))->assertOk()->assertSee('Gasolina reparto')->assertDontSee('Fruta del mercado');
    }

    public function test_venta_sin_costo_se_avisa(): void
    {
        $p = Producto::create(['nombre' => 'Paleta sin costo', 'precio' => 30, 'unidad' => 'pieza']);
        Inventario::mover($p, 'entrada', 10, 'Inicial');
        $this->actingAs($this->dueno)->post(route('admin.pedidos.store'), [
            'productos' => [['producto_id' => $p->id, 'cantidad' => 2, 'precio' => 30]], 'pago' => 'contado', 'forma' => 'efectivo', 'entrega' => 'ahora',
        ])->assertSessionHasNoErrors();

        $r = Contabilidad::datos('2026-10-01', '2026-10-24')['resultados'];
        $this->assertSame(1, $r['sinCosto']);
        $this->get(route('admin.finanzas', ['seccion' => 'contabilidad', 'periodo' => 'mes']))->assertOk()->assertSee('no tiene costo capturado');
    }
}
