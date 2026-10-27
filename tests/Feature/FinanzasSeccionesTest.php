<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Finanzas en secciones: resumen, ventas, cobranza, rentabilidad y proveedores */
class FinanzasSeccionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_seccion_muestra_lo_suyo(): void
    {
        $this->actingAs(User::factory()->create());
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel X']);

        // Venta con costeo: 20 × $117 = $2,340; costo $1,800 (terceros 1,600, operativo 200) → utilidad $540, margen 23.1 %
        $p = Presupuesto::nuevaPara($c);
        $p->fill(['estado' => 'aceptada', 'aceptada_el' => now(), 'tipo' => 'produccion'])->save();
        $p->conceptos()->create(['titulo' => 'Playeras', 'descripcion' => '', 'cantidad' => 20, 'precio' => 117, 'orden' => 0, 'utilidad' => 30, 'costos' => [
            ['clave' => 'proveedor', 'nombre' => 'Proveedor o maquila', 'modo' => 'unidad', 'clase' => 'tercero', 'monto' => 80],
            ['clave' => 'gasolina', 'nombre' => 'Gasolina y traslados', 'modo' => 'fijo', 'clase' => 'operativo', 'monto' => 200],
        ]]);
        // Venta sin costeo
        $s = Presupuesto::nuevaPara($c);
        $s->fill(['estado' => 'aceptada', 'aceptada_el' => now()])->save();
        $s->conceptos()->create(['titulo' => 'Video', 'descripcion' => '', 'cantidad' => 1, 'precio' => 9000, 'orden' => 0]);

        $pr = Proyecto::create(['cliente_id' => $c->id, 'presupuesto_id' => $p->id, 'nombre' => 'Uniformes', 'tipo' => 'produccion', 'estado' => 'activo', 'monto_total' => 2714.4]);
        $prov = Proveedor::create(['empresa' => 'Bordados Mérida', 'rubro' => 'Bordados']);
        $o = OrdenCompra::create(['proveedor_id' => $prov->id, 'proyecto_id' => $pr->id, 'titulo' => 'Playeras', 'con_iva' => true, 'iva_porcentaje' => 16, 'estado' => 'entregada']);
        $o->conceptos()->create(['descripcion' => 'Playera bordada', 'cantidad' => 20, 'precio' => 85, 'orden' => 0]); // $1,700 real vs 1,600 costeado

        // 5 pestañas, cada una con lo suyo
        $this->get('/admin/finanzas?periodo=todo')->assertOk()->assertSee('Lo que pide tu atención')->assertSee('Vendiste')->assertSee('Te quedó')
            ->assertSee('Dinero')->assertSee('Utilidad e impuestos')->assertDontSee('Embudo de ventas');
        $this->get('/admin/finanzas/ventas?iva=sin&periodo=todo')->assertOk()->assertSee('Embudo de ventas')->assertSee('Cotizaciones perdidas')
            ->assertSee('Tendencia de 12 meses')->assertSee('Quién te debe');
        $this->get('/admin/finanzas/utilidad?periodo=todo')->assertOk()->assertSee('Estado de resultados')
            ->assertSee('$540')->assertSee('23.1%')->assertSee('Proveedor o maquila')->assertSee('$1,700')->assertSee('$100 más de lo costeado')
            ->assertSee('1 venta ganada no tiene costeo');
        $this->get('/admin/finanzas/gastos?periodo=todo')->assertOk()->assertSee('Bordados Mérida')->assertSee('OC-0001')->assertSee('$1,972');
        $this->get('/admin/finanzas/dinero')->assertOk()->assertSee('Próximas 8 semanas');
        // Fechas a elegir
        $this->get('/admin/finanzas/ventas?periodo=rango&desde=2020-01-01&hasta=2030-12-31')->assertOk()->assertSee('1 ene. 2020')->assertSee('31 dic. 2030');
        // Los enlaces de antes llevan a su nueva pestaña
        $this->get('/admin/finanzas/cobranza?periodo=todo')->assertRedirect('/admin/finanzas/ventas?periodo=todo#por-cobrar');
        $this->get('/admin/finanzas/rentabilidad')->assertRedirect('/admin/finanzas/utilidad#rentabilidad');
        $this->get('/admin/finanzas/proveedores')->assertRedirect('/admin/finanzas/gastos#proveedores');
        $this->get('/admin/finanzas/flujo')->assertRedirect('/admin/finanzas/dinero#lo-que-viene');
        $this->get('/admin/finanzas/otra')->assertNotFound();
        $this->get('/admin/finanzas/exportar')->assertOk();
    }
}
