<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\EsteMes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Borrar una cotización que ya era proyecto también quita sus pagos de los ingresos */
class EliminarCotizacionTest extends TestCase
{
    use RefreshDatabase;

    private function cotizacionConProyecto(Cliente $c, string $nombre, float $cobrado, float $pendiente): Presupuesto
    {
        $p = Presupuesto::nuevaPara($c);
        $p->estado = 'aceptada';
        $p->aceptada_el = now();
        $p->save();
        $p->conceptos()->create(['descripcion' => $nombre, 'cantidad' => 1, 'precio' => 10000, 'orden' => 0]);
        $pr = Proyecto::create(['cliente_id' => $c->id, 'presupuesto_id' => $p->id, 'nombre' => $nombre, 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => $cobrado + $pendiente]);
        $pr->pagos()->create(['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'monto' => $cobrado, 'orden' => 0, 'pagado_el' => now()]);
        $pr->pagos()->create(['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 50, 'monto' => $pendiente, 'orden' => 1]);
        return $p->fresh('proyecto');
    }

    public function test_al_eliminar_la_cotizacion_sus_pagos_dejan_de_contar(): void
    {
        $this->actingAs(User::factory()->create());
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel X']);
        $prueba = $this->cotizacionConProyecto($c, 'Cotización de prueba', 5800, 5800);
        $real = $this->cotizacionConProyecto($c, 'Video real', 3000, 3000);

        $this->assertEquals(8800, EsteMes::datos(true)['cobrado']);
        $this->get("/admin/presupuestos/{$prueba->id}/edit")->assertOk()->assertSee('se elimina su proyecto', false);

        $this->delete("/admin/presupuestos/{$prueba->id}")->assertRedirect()->assertSessionHas('ok');

        $this->assertModelMissing($prueba);
        $this->assertDatabaseMissing('proyectos', ['nombre' => 'Cotización de prueba']);
        $this->assertDatabaseCount('proyecto_pagos', 2);              // solo los del proyecto real
        $this->assertEquals(3000, EsteMes::datos(true)['cobrado']);
        $this->get('/admin/finanzas')->assertOk();
        $this->get('/admin')->assertOk();

        // Una cotización sin proyecto se borra igual que siempre
        $sola = Presupuesto::nuevaPara($c);
        $sola->save();
        $this->delete("/admin/presupuestos/{$sola->id}")->assertRedirect();
        $this->assertModelMissing($sola);
        $this->assertModelExists($real->proyecto);
    }
}
