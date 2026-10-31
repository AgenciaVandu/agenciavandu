<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Cuentas;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Venta dictada: la IA entiende el texto, Nerby busca cliente y productos y la deja lista para cobrar */
class VentaDictadaTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tienda;
    private User $dueno;
    private Producto $mango;
    private Producto $coco;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        config(['services.anthropic.key' => 'sk-ant-prueba']);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar($this->tienda->refresh());
        $this->mango = Producto::create(['nombre' => 'Paleta de mango', 'precio' => 25, 'unidad' => 'pieza']);
        $this->coco = Producto::create(['nombre' => 'Paleta de coco', 'precio' => 28, 'unidad' => 'pieza']);
        Inventario::mover($this->mango, 'entrada', 12, 'Inicial');
        Inventario::mover($this->coco, 'entrada', 50, 'Inicial');
        $this->actingAs($this->dueno);
    }

    private array $entrada = [];
    private bool $falso = false;

    private function ia(array $entrada): void
    {
        $this->entrada = $entrada;
        if ($this->falso) return;
        $this->falso = true;
        Http::fake(['api.anthropic.com/*' => fn () => Http::response(['content' => [['type' => 'tool_use', 'name' => 'armar_venta', 'input' => $this->entrada]], 'usage' => ['input_tokens' => 900, 'output_tokens' => 150]])]);
    }

    public function test_arma_la_venta_con_cliente_existente_productos_y_concepto_libre(): void
    {
        Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => '9991112233']);
        $this->ia([
            'es_venta' => true, 'cliente' => ['nombre' => 'Juan Pérez', 'empresa' => null, 'telefono' => null],
            'productos' => [
                ['dicho' => 'paletas de mango', 'producto_id' => $this->mango->id, 'cantidad' => 20, 'precio_unitario' => null, 'importe' => null],
                ['dicho' => 'paletas de coco', 'producto_id' => $this->coco->id, 'cantidad' => 5, 'precio_unitario' => 25, 'importe' => null],
                ['dicho' => 'hielera de unicel', 'producto_id' => null, 'cantidad' => 1, 'precio_unitario' => null, 'importe' => null],
                ['dicho' => 'producto inventado', 'producto_id' => 999999, 'cantidad' => 2, 'precio_unitario' => null, 'importe' => 60],
            ],
            'pago' => 'contado', 'forma' => 'transferencia', 'entrega' => 'ahora', 'notas' => 'sin chile',
        ]);
        $r = $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'le vendí a Juan Pérez 20 de mango y 5 de coco a 25, una hielera, pagó con transferencia'])->assertOk()->json('venta');

        $this->assertSame(['tipo' => 'existe'], ['tipo' => $r['cliente']['tipo']]);
        $this->assertSame('Juan Pérez', $r['cliente']['cliente']['nombre']);
        [$m, $c, $h, $x] = $r['lineas'];
        $this->assertSame([$this->mango->id, 20, 25, false, 12], [$m['id'], (int) $m['cantidad'], (int) $m['precio'], $m['precio_dicho'], (int) $m['existencia']]);
        $this->assertSame([25, 28, true], [(int) $c['precio'], (int) $c['precio_catalogo'], $c['precio_dicho']]);
        // No está en el catálogo: concepto libre, sin precio inventado
        $this->assertSame([null, true, 'Hielera de unicel', null, true], [$h['id'], $h['libre'], $h['nombre'], $h['precio'], $h['sin_precio']]);
        // Un id que no es del catálogo no se acepta; el importe del renglón da el precio
        $this->assertSame([null, true, 30], [$x['id'], $x['libre'], (int) $x['precio']]);
        $this->assertSame(['contado', 'transferencia', 'ahora', 'sin chile'], [$r['pago'], $r['forma'], $r['entrega'], $r['notas']]);
        Http::assertSent(fn ($q) => str_contains($q['messages'][0]['content'][0]['text'], $this->mango->id . ' | Paleta de mango') && $q['tool_choice']['name'] === 'armar_venta');

        // Se cobra con el formulario: el concepto libre se guarda y no mueve inventario
        $this->post(route('admin.pedidos.store'), [
            'cliente_id' => $r['cliente']['cliente']['id'],
            'productos' => [
                ['producto_id' => $this->mango->id, 'cantidad' => 20, 'precio' => 25],
                ['descripcion' => 'Hielera de unicel', 'cantidad' => 1, 'precio' => 80],
            ], 'pago' => 'contado', 'forma' => 'transferencia', 'entrega' => 'ahora',
        ])->assertSessionHasNoErrors();
        $v = Venta::latest('id')->first();
        $this->assertEquals(580, $v->total);
        $this->assertSame([null, 'Hielera de unicel'], [$v->conceptos[1]->producto_id, $v->conceptos[1]->descripcion]);
        $this->assertEquals(-8, $this->mango->fresh()->existencia);
        $this->get(route('admin.pedidos.show', $v))->assertOk()->assertSee('Hielera de unicel');

        // Concepto libre sin descripción no pasa
        $this->post(route('admin.pedidos.store'), ['productos' => [['cantidad' => 1, 'precio' => 10]], 'pago' => 'contado', 'entrega' => 'ahora'])->assertSessionHasErrors('productos.0.descripcion');
    }

    public function test_cliente_nuevo_varios_y_por_telefono(): void
    {
        Cliente::create(['nombre' => 'Juan Pérez', 'telefono' => '9991112233']);
        Cliente::create(['nombre' => 'Juan Pech']);
        Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel Sol', 'telefono' => '999 444 5566']);
        $base = ['es_venta' => true, 'productos' => [['dicho' => 'mango', 'producto_id' => $this->mango->id, 'cantidad' => 1]], 'pago' => 'credito', 'entrega' => 'ahora'];

        $this->ia($base + ['cliente' => ['nombre' => 'Juan']]);
        $r = $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'una de mango a Juan, me la paga después'])->json('venta');
        $this->assertSame(['varios', 2, 'credito'], [$r['cliente']['tipo'], count($r['cliente']['candidatos']), $r['pago']]);

        $this->ia($base + ['cliente' => ['nombre' => 'María López', 'telefono' => '999 777 8899']]);
        $r = $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'una de mango a María López 9997778899'])->json('venta');
        $this->assertSame(['nuevo', 'María López', '9997778899'], [$r['cliente']['tipo'], $r['cliente']['nombre'], $r['cliente']['telefono']]);

        $this->ia($base + ['cliente' => ['nombre' => 'la del hotel', 'telefono' => '9994445566']]);
        $r = $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'una de mango al 9994445566'])->json('venta');
        $this->assertSame(['existe', 'Hotel Sol'], [$r['cliente']['tipo'], $r['cliente']['cliente']['nombre']]);

        $this->ia($base + ['cliente' => null]);
        $this->assertSame('ninguno', $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'una de mango'])->json('venta.cliente.tipo'));
    }

    public function test_sin_ia_o_sin_venta_avisa(): void
    {
        $this->get(route('admin.pedidos.create'))->assertOk()->assertSee('Armar venta');
        $this->ia(['es_venta' => false, 'productos' => [], 'pago' => 'contado', 'entrega' => 'ahora']);
        $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'hola qué tal'])->assertStatus(422)->assertJson(['ok' => false]);

        config(['services.anthropic.key' => null]);
        $this->get(route('admin.pedidos.create'))->assertOk()->assertDontSee('Armar venta');
        $this->postJson(route('admin.pedidos.dictar'), ['texto' => 'dos de mango'])->assertStatus(422)->assertJson(['mensaje' => 'Dictar ventas todavía no está activo en este panel.']);
    }
}
