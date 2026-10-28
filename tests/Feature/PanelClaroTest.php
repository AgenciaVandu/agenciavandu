<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proyecto;
use App\Support\Cuentas;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Inicio según el giro, barra del celular, Configuración en un solo lugar y nombre Inicio */
class PanelClaroTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicio_de_productos_y_servicios(): void
    {
        [$tienda, $dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar($tienda);
        $p = Producto::create(['nombre' => 'Paleta', 'precio' => 25, 'costo' => 9, 'unidad' => 'pieza']);
        Inventario::mover($p, 'entrada', 100, 'Inicial');
        $this->actingAs($dueno);
        // Venta a crédito (232 con IVA) y un pago pendiente de proyecto (500): un solo "Te deben"
        $this->post(route('admin.pedidos.store'), ['productos' => [['producto_id' => $p->id, 'cantidad' => 8, 'precio' => 25]], 'pago' => 'credito', 'entrega' => 'ahora',
            'con_iva' => 1, 'cliente_nombre' => 'Hotel', 'cliente_telefono' => '9991112233'])->assertSessionHasNoErrors();
        $pr = Proyecto::create(['cliente_id' => Cliente::first()->id, 'nombre' => 'Congelador', 'tipo' => 'venta', 'estado' => 'activo']);
        $pr->pagos()->create(['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 100, 'monto' => 500, 'orden' => 0]);

        $html = $this->get(route('admin.resumen'))->assertOk()->getContent();
        $this->assertStringContainsString('Te deben', $html);
        $this->assertStringContainsString('$732', $html);
        $this->assertStringNotContainsString('Tasa de aceptación', $html, 'Sin cotizaciones no se muestran sus números');
        $this->assertStringContainsString('de 5', $html, 'Primeros pasos con avance');
        $this->assertStringNotContainsString('Carga tus productos', $html, 'Los pasos hechos ya no salen');
        // Barra del celular: Inicio, Pedidos, Productos, Clientes
        preg_match('/<nav class="tabbar".*?<\/nav>/s', $html, $m);
        foreach (['Inicio', 'Ventas', 'Productos', 'Clientes'] as $t) $this->assertStringContainsString($t, $m[0]);
        $this->assertStringNotContainsString('Proyectos', $m[0]);
        // Menú: Inicio (no Resumen) y Configuración en un solo lugar
        $this->assertStringContainsString(route('admin.configuracion'), $html);
        $this->assertStringNotContainsString('>Costeo<', $html);
        $this->get(route('admin.configuracion'))->assertOk()->assertSee('Mi negocio')->assertSee('Ventas y envíos')->assertSee('Diseño de cotización')->assertSee('Costeo');
    }
}
