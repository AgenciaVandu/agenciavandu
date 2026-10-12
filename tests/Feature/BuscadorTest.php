<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Buscador de todo el panel: clientes, proyectos, proveedores, cotizaciones y órdenes */
class BuscadorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $ana = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel Xcanatún', 'telefono' => '(999) 123 4567', 'email' => 'ana@xcanatun.mx']);
        Cliente::create(['nombre' => 'Luis Pérez', 'empresa' => 'Café Lima']);
        $p = Presupuesto::nuevaPara($ana);
        $p->estado = 'aceptada';
        $p->save();
        $p->conceptos()->create(['descripcion' => 'Lona impresa 3x2', 'cantidad' => 1, 'precio' => 1500, 'orden' => 0]);
        $pr = Proyecto::create(['cliente_id' => $ana->id, 'presupuesto_id' => $p->id, 'nombre' => 'Uniformes del hotel', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 1740]);
        $prov = Proveedor::create(['empresa' => 'Bordados Mérida', 'nombre' => 'Carlos Pech', 'rubro' => 'Bordados', 'telefono' => '999-555-0101']);
        $o = OrdenCompra::create(['proveedor_id' => $prov->id, 'proyecto_id' => $pr->id, 'titulo' => 'Camisas bordadas', 'con_iva' => true, 'iva_porcentaje' => 16, 'estado' => 'enviada']);
        $o->conceptos()->create(['descripcion' => 'Camisa Oxford', 'cantidad' => 20, 'precio' => 250, 'orden' => 0]);
    }

    private function grupos(string $q, ?User $u = null): array
    {
        return collect($this->actingAs($u ?? $this->admin)->getJson('/admin/buscar?q=' . urlencode($q))->assertOk()->json('grupos'))
            ->mapWithKeys(fn ($g) => [$g['clave'] => collect($g['items'])->pluck('titulo')->all()])->all();
    }

    public function test_encuentra_en_todos_los_modulos(): void
    {
        $g = $this->grupos('hotel');
        $this->assertSame(['Hotel Xcanatún'], $g['clientes']);
        $this->assertSame(['Uniformes del hotel'], $g['proyectos']);
        $this->assertStringContainsString('Hotel Xcanatún', $g['cotizaciones'][0]);

        $this->assertSame(['Bordados Mérida'], $this->grupos('bordados')['proveedores']);
        $this->assertSame(['OC-0001 · Camisas bordadas'], $this->grupos('OC-0001')['ordenes']);
        $this->assertArrayHasKey('cotizaciones', $this->grupos('CT-0001'));
        // Por lo que se pidió, por el cliente del proyecto y por el proveedor
        $this->assertArrayHasKey('cotizaciones', $this->grupos('lona'));
        $this->assertSame(['Uniformes del hotel'], $this->grupos('Ana López')['proyectos']);
        $this->assertArrayHasKey('ordenes', $this->grupos('oxford'));
    }

    public function test_varias_palabras_y_telefonos_con_cualquier_formato(): void
    {
        $this->assertSame(['Hotel Xcanatún'], $this->grupos('ana hotel')['clientes']);
        $this->assertArrayNotHasKey('clientes', $this->grupos('ana lima'));
        $this->assertSame(['Hotel Xcanatún'], $this->grupos('9991234567')['clientes']);
        $this->assertSame(['Hotel Xcanatún'], $this->grupos('4567')['clientes']);
        $this->assertSame(['Bordados Mérida'], $this->grupos('5550101')['proveedores']);
        $this->assertSame([], $this->grupos('x'));            // muy corto
        $this->assertSame([], $this->grupos('%%'));           // sin comodines
        $this->assertSame([], $this->grupos('zzzz'));
    }

    public function test_solo_muestra_lo_que_cada_quien_puede_ver(): void
    {
        $rol = Rol::create(['nombre' => 'Compras', 'permisos' => ['proveedores']]);
        $compras = User::factory()->create(['rol_id' => $rol->id]);
        $g = $this->grupos('bordados', $compras);
        $this->assertSame(['proveedores', 'ordenes'], array_keys($g));
        $this->assertEmpty(array_diff(array_keys($this->grupos('hotel', $compras)), ['proveedores', 'ordenes']), 'No ve clientes, proyectos ni cotizaciones');

        $foto = User::factory()->create(['rol_id' => Rol::where('nombre', 'Fotógrafo')->value('id')]);
        $this->assertSame([], $this->grupos('hotel', $foto));
    }

    public function test_pagina_completa_y_boton_en_el_panel(): void
    {
        $this->actingAs($this->admin)->get('/admin/buscar?q=hotel')->assertOk()->assertSee('Uniformes del hotel')->assertSee('Hotel Xcanatún');
        $this->get('/admin/buscar?q=zzzz')->assertOk()->assertSee('Nada coincide');
        $this->get('/admin')->assertOk()->assertSee('data-buscar', false)->assertSee('buscador-q', false);
    }
}
