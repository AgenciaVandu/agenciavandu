<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menú en áreas fijas, funciones que se prenden y apagan, regresar automático y "Para hoy" */
class PanelSencilloTest extends TestCase
{
    use RefreshDatabase;

    private function tienda(): array
    {
        [$tienda, $dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar($tienda);
        $this->actingAs($dueno);
        return [$tienda, $dueno];
    }

    public function test_negocio_nuevo_arranca_sin_lo_que_no_usa_y_lo_puede_prender(): void
    {
        [$tienda] = $this->tienda();
        $this->assertFalse($tienda->fresh()->tiene('tareas'));
        $this->assertTrue($tienda->fresh()->incluye('tareas'));
        $html = $this->get(route('admin.resumen'))->assertOk()->getContent();
        preg_match('/<aside.*?<\/aside>/s', $html, $m);
        $this->assertStringNotContainsString(route('admin.tareas.index'), $m[0]);
        $this->assertStringNotContainsString('>Panel<', $m[0]);
        $this->assertStringNotContainsString('Compras e inventario', $m[0]);
        $this->assertStringContainsString('Operación', $m[0]);

        // Apagada: la página explica cómo prenderla
        $this->get(route('admin.tareas.index'))->assertStatus(403)->assertSee('Tareas está apagada')->assertSee('Ir a Funciones del panel');

        // Configuración → Funciones
        $this->get(route('admin.configuracion'))->assertSee('Funciones del panel')->assertSee('2 apagadas');
        $this->get(route('admin.funciones'))->assertOk()->assertSee('Proyectos')->assertSee('Tareas')->assertDontSee('Redes sociales');
        $this->put(route('admin.funciones.guardar'), ['funciones' => ['tareas' => 1, 'archivos' => 0, 'proyectos' => 0, 'cotizaciones' => 1, 'ventas' => 1, 'proveedores' => 1]])->assertSessionHas('ok');
        $t = $tienda->fresh();
        $this->assertTrue($t->tiene('tareas'));
        $this->assertFalse($t->tiene('proyectos'));
        Cuentas::activar($t);
        $this->get(route('admin.tareas.index'))->assertOk();
        $this->get(route('admin.proyectos.index'))->assertStatus(403)->assertSee('Proyectos está apagada');
    }

    public function test_regresar_y_confirmaciones_propias(): void
    {
        $this->tienda();
        $c = Cliente::create(['nombre' => 'Hotel Casa Azul']);
        $html = $this->get(route('admin.clientes.show', $c))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#class="regresar" data-regresar><i class="bi bi-arrow-left"></i> Clientes</a>#', $html);
        $this->assertStringContainsString('id="cfm"', $html);
        $this->assertStringNotContainsString('class="regresar"', $this->get(route('admin.clientes.index'))->getContent());
        $this->assertMatchesRegularExpression('#data-regresar><i class="bi bi-arrow-left"></i> Configuración</a>#', $this->get(route('admin.negocio'))->getContent());
    }

    public function test_para_hoy(): void
    {
        [, $dueno] = $this->tienda();
        $this->get(route('admin.resumen'))->assertSee('Para hoy')->assertSee('Todo al día');
        $cli = Cliente::create(['nombre' => 'Hotel']);
        $p = Proyecto::create(['cliente_id' => $cli->id, 'nombre' => 'Congelador', 'tipo' => 'venta', 'estado' => 'activo']);
        $p->pagos()->create(['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 100, 'monto' => 1500, 'orden' => 0, 'vence_el' => now()->subDay()->toDateString()]);
        \App\Models\Prospecto::create(['nombre' => 'Ana', 'nuevo' => true]);
        $this->get(route('admin.resumen'))->assertSee('Cobrar $1,500')->assertSee('1 prospecto nuevo')->assertDontSee('Todo al día');
    }

    public function test_registrar_pago_en_un_solo_lugar_y_historial(): void
    {
        $this->tienda();
        $cli = Cliente::create(['nombre' => 'Hotel Casa Azul']);
        $p = Proyecto::create(['cliente_id' => $cli->id, 'nombre' => 'Congelador', 'tipo' => 'venta', 'estado' => 'activo']);
        $pago = $p->pagos()->create(['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'monto' => 800, 'orden' => 0, 'vence_el' => now()->subDays(2)->toDateString()]);
        $v = \App\Models\Venta::create(['folio' => 'V-1', 'cliente_id' => $cli->id, 'cliente_nombre' => 'Hotel Casa Azul', 'fecha' => now()->toDateString(), 'subtotal' => 300, 'total' => 300, 'pagado' => 0, 'credito' => true, 'estado' => 'entregada']);

        // Lista de quién debe, y lo que debe este cliente (proyecto + venta)
        $this->get(route('admin.cobrar'))->assertOk()->assertSee('Hotel Casa Azul')->assertSee('$1,100.00')->assertSee('algo ya venció');
        $this->get(route('admin.cobrar', ['cliente' => $cli->id]))->assertOk()->assertSee('Cobrar a Hotel Casa Azul')->assertSee('Anticipo')->assertSee('Venta V-1');

        // Se registra con la misma acción de siempre y regresa a cobrar
        $this->from(route('admin.cobrar', ['cliente' => $cli->id]))
            ->patch(route('admin.proyectos.pago', [$p, $pago]), ['accion' => 'pagar', 'monto' => 800, 'metodo' => 'transferencia'])
            ->assertRedirect(route('admin.cobrar', ['cliente' => $cli->id]));
        $this->from(route('admin.cobrar', ['cliente' => $cli->id]))->post(route('admin.pedidos.abonar', $v), ['monto' => 300, 'forma' => 'efectivo'])->assertSessionHasNoErrors();
        $this->get(route('admin.cobrar', ['cliente' => $cli->id]))->assertSee('No te debe nada');

        // La ficha cuenta su historia
        $this->get(route('admin.clientes.show', $cli))->assertOk()->assertSee('Historial')->assertSee('Pagó $800.00')->assertSee('Arrancó el proyecto Congelador')->assertSee('Venta V-1 · Efectivo');
        // Y el + Nuevo trae "Registrar pago"
        $this->get(route('admin.resumen'))->assertSee(route('admin.cobrar'), false);
    }

    public function test_venta_unificada_arranque_e_inicio_por_rol(): void
    {
        [$tienda] = $this->tienda();
        $cli = Cliente::create(['nombre' => 'Hotel']);
        Proyecto::create(['cliente_id' => $cli->id, 'nombre' => 'Congelador grande', 'tipo' => 'venta', 'estado' => 'activo']);
        // Ventas: mostrador, pedidos y proyectos en un solo lugar
        $this->get(route('admin.pedidos', ['ver' => 'proyectos']))->assertOk()->assertSee('Ventas')->assertSee('Congelador grande')->assertSee('Cotizaciones abiertas');
        $this->get(route('admin.pedidos.create'))->assertSee('Pedido especial o con etapas');

        // Arranque: pasos y ejemplos que se cargan y se borran
        $this->get(route('admin.resumen'))->assertSee('Primeros pasos')->assertSee('Cargar ejemplos');
        $this->post(route('admin.ejemplos'))->assertRedirect(route('admin.resumen'));
        $this->assertTrue(\App\Models\Producto::where('ejemplo', true)->exists());
        $this->get(route('admin.resumen'))->assertSee('Borrar ejemplos');
        $this->delete(route('admin.ejemplos.quitar'))->assertSessionHas('ok');
        $this->assertFalse(\App\Models\Producto::where('ejemplo', true)->exists());

        // Inicio por rol: un vendedor sin cifras ve su día y sus herramientas
        $rol = \App\Models\Rol::create(['nombre' => 'Vendedor', 'permisos' => ['vender', 'clientes', 'cotizaciones'], 'orden' => 5]);
        $vend = \App\Models\User::create(['name' => 'Pepe Vendedor', 'email' => 'pepe@paletas.mx', 'password' => 'x', 'rol_id' => $rol->id, 'activo' => true]);
        $this->actingAs($vend);
        Cuentas::activar($tienda);
        $this->get(route('admin.resumen'))->assertOk()->assertSee('Tus herramientas')->assertSee('Nueva venta')->assertSee('Para hoy')->assertDontSee('Cobrado en');
        // Con una sola herramienta va directo a ella
        $rol->update(['permisos' => ['clientes']]);
        $vend->load('rol');
        $this->get(route('admin.resumen'))->assertRedirect();
    }
}
