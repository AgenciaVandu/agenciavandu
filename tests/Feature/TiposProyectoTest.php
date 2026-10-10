<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\ProyectoTipo;
use App\Models\User;
use App\Support\TiposProyecto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** La agencia arma sus tipos de proyecto y ajusta las etapas de cada proyecto */
class TiposProyectoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        TiposProyecto::aplicar();
    }

    protected function tearDown(): void
    {
        config(['vandu.proyectos' => TiposProyecto::fabrica()]);
        parent::tearDown();
    }

    private function datos(array $extra = []): string
    {
        return json_encode($extra + [
            'nombre' => 'Redes mensual', 'icono' => 'bi-megaphone',
            'etapas' => [
                ['ref' => 'estrategia', 'clave' => 'estrategia', 'nombre' => 'Junta de arranque', 'dias' => 1, 'descripcion' => 'Llamada', 'fecha' => true],
                ['ref' => 'nueva-1', 'clave' => '', 'nombre' => 'Sesión de fotos', 'dias' => 2, 'descripcion' => ''],
                ['ref' => 'publicacion', 'clave' => 'publicacion', 'nombre' => 'Publicación', 'dias' => 25, 'descripcion' => ''],
            ],
            'pagos' => [
                ['concepto' => 'Mensualidad', 'porcentaje' => 100, 'antes_de' => 'nueva-1'],
            ],
        ]);
    }

    private function cotizacion(): Presupuesto
    {
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Café Sol']);
        $p = Presupuesto::nuevaPara($c);
        $p->save();
        $p->conceptos()->create(['descripcion' => 'Gestión de redes sociales', 'cantidad' => 1, 'precio' => 8000, 'orden' => 0]);
        return $p->fresh();
    }

    public function test_redes_es_un_tipo_de_fabrica_y_se_sugiere(): void
    {
        $this->assertArrayHasKey('redes', config('vandu.proyectos'));
        $this->assertSame('redes', Proyecto::tipoSugerido($this->cotizacion()));
        $this->get('/admin/tipos-de-proyecto?t=redes')->assertOk()->assertSee('Gestión de redes sociales')->assertSee('Estrategia del mes');
    }

    public function test_editar_un_tipo_de_fabrica_y_restaurarlo(): void
    {
        $this->put('/admin/tipos-de-proyecto/redes', ['datos' => $this->datos()])->assertRedirect('/admin/tipos-de-proyecto?t=redes');

        $t = config('vandu.proyectos.redes');
        $this->assertSame('Redes mensual', $t['nombre']);
        $this->assertTrue($t['editado']);
        $this->assertTrue($t['redes']); // se conserva la liga con el calendario
        $this->assertSame(['estrategia', 'sesion-de-fotos', 'publicacion'], array_column($t['etapas'], 'clave'));
        $this->assertTrue($t['etapas'][0]['fecha']);
        $this->assertSame('sesion-de-fotos', $t['pagos'][0]['antes_de']); // la etapa nueva se liga con el pago

        $this->post('/admin/tipos-de-proyecto/redes/restaurar')->assertRedirect();
        $this->assertSame('Gestión de redes sociales', config('vandu.proyectos.redes.nombre'));
        $this->assertCount(6, config('vandu.proyectos.redes.etapas'));
    }

    public function test_los_pagos_deben_sumar_100(): void
    {
        $d = json_decode($this->datos(), true);
        $d['pagos'] = [['concepto' => 'Anticipo', 'porcentaje' => 50, 'antes_de' => '']];
        $this->put('/admin/tipos-de-proyecto/redes', ['datos' => json_encode($d)])->assertSessionHasErrors('pagos');
        $this->assertDatabaseCount('proyecto_tipos', 0);
    }

    public function test_crear_tipo_propio_usarlo_y_no_poder_borrarlo_en_uso(): void
    {
        $this->post('/admin/tipos-de-proyecto')->assertRedirect();
        $clave = ProyectoTipo::firstOrFail()->clave;
        $this->put("/admin/tipos-de-proyecto/$clave", ['datos' => $this->datos(['nombre' => 'Branding', 'galeria' => true])])->assertRedirect();
        $this->assertSame('Branding', config("vandu.proyectos.$clave.nombre"));
        $this->assertTrue(config("vandu.proyectos.$clave.galeria"));

        $p = $this->cotizacion();
        $this->get("/admin/presupuestos/{$p->id}/proyecto?tipo=$clave")->assertOk()->assertSee('Branding')->assertSee('sesion-de-fotos');

        Proyecto::create(['cliente_id' => $p->cliente_id, 'nombre' => 'X', 'tipo' => $clave, 'estado' => 'activo']);
        $this->delete("/admin/tipos-de-proyecto/$clave")->assertSessionHasErrors('tipo');
        $this->assertDatabaseHas('proyecto_tipos', ['clave' => $clave]);
    }

    public function test_crear_proyecto_con_etapas_propias_y_ligarlo_a_redes(): void
    {
        $p = $this->cotizacion();
        $this->post("/admin/presupuestos/{$p->id}/proyecto", [
            'tipo' => 'redes', 'nombre' => 'Redes Café Sol', 'monto_total' => 9280, 'forma_pago' => 'contado',
            'etapas' => [
                ['clave' => 'estrategia', 'nombre' => 'Estrategia', 'estado' => 'pendiente', 'dias' => 2],
                ['clave' => '', 'nombre' => 'Sesión de fotos en sucursal', 'descripcion' => 'Vamos a tu local', 'es_fecha' => '1', 'estado' => 'pendiente', 'fecha_inicio' => '2026-11-03'],
                ['clave' => 'publicacion', 'nombre' => 'Publicación', 'estado' => 'pendiente', 'dias' => 20],
            ],
            'pagos' => [['monto' => 9280]],
        ])->assertRedirect();

        $pr = Proyecto::with('etapas', 'pagos')->firstOrFail();
        $this->assertSame(['estrategia', 'sesion-de-fotos-en-sucursal', 'publicacion'], $pr->etapas->pluck('clave')->all());
        $this->assertTrue($pr->etapas[1]->es_fecha);
        $this->assertSame('Vamos a tu local', $pr->etapas[1]->descripcion);
        $this->assertNull($pr->pagos[0]->antes_de); // la etapa "produccion" se quitó: el pago queda sin condición
        $this->assertNotNull($pr->cliente->fresh()->redes_token);
        $this->get("/admin/proyectos/{$pr->id}")->assertSee('Calendario de contenido');
    }

    public function test_editar_etapas_de_un_proyecto_existente(): void
    {
        $p = $this->cotizacion();
        $pr = Proyecto::create(['cliente_id' => $p->cliente_id, 'presupuesto_id' => $p->id, 'nombre' => 'Video', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 1000]);
        $lev = $pr->etapas()->create(['clave' => 'levantamiento', 'nombre' => 'Grabación', 'orden' => 0, 'es_fecha' => true]);
        $ent = $pr->etapas()->create(['clave' => 'entrega', 'nombre' => 'Entrega', 'orden' => 1, 'es_fecha' => true]);
        $pr->pagos()->create(['clave' => 'saldo', 'concepto' => 'Saldo', 'porcentaje' => 100, 'monto' => 1000, 'antes_de' => 'levantamiento', 'orden' => 0]);
        $archivo = $pr->archivos()->create(['etapa_id' => $lev->id, 'nombre' => 'a.jpg', 'ruta' => 'x/a.jpg', 'tipo' => 'foto', 'orden' => 0]);

        // Se quita la grabación, se agrega la edición antes de la entrega y se renombra la entrega
        $this->put("/admin/proyectos/{$pr->id}/fechas", [
            'etapas' => [
                ['id' => '', 'clave' => '', 'nombre' => 'Edición', 'estado' => 'en_curso', 'dias' => '4', 'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-15'],
                ['id' => $ent->id, 'clave' => 'otra-cosa', 'nombre' => 'Entrega final', 'es_fecha' => '1', 'estado' => 'pendiente'],
            ],
            'pagos' => [['monto' => 1000]],
        ])->assertRedirect("/admin/proyectos/{$pr->id}");

        $etapas = $pr->etapas()->get();
        $this->assertSame(['edicion', 'entrega'], $etapas->pluck('clave')->all()); // la existente conserva su clave
        $this->assertSame('Entrega final', $etapas[1]->nombre);
        $this->assertSame($ent->id, $etapas[1]->id);
        $this->assertSame(4, $etapas[0]->dias);
        $this->assertDatabaseMissing('proyecto_etapas', ['id' => $lev->id]);
        $this->assertNull($archivo->fresh()->etapa_id); // el archivo se queda en el proyecto
        $this->assertNull($pr->pagos()->first()->antes_de);
        $this->get("/admin/proyectos/{$pr->id}/fechas")->assertOk()->assertSee('Agregar etapa');
    }
}
