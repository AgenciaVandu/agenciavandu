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
}
