<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use App\Models\User;
use App\Support\Costeo;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Costeo con los componentes de cada negocio: precio sugerido, utilidad y margen */
class CosteoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Cliente $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel X']);
    }

    private function datos(array $conceptos, array $extra = []): array
    {
        $p = Presupuesto::nuevaPara($this->c);
        return $extra + [
            'cliente_id' => $this->c->id, 'cliente_nombre' => 'Ana', 'cliente_empresa' => 'Hotel X', 'fecha' => '2026-10-12', 'titulo' => 'Cotización',
            'tipo' => 'web', 'emisor_nombre' => 'Alvar', 'modo_iva' => 'mas_iva', 'iva_porcentaje' => 16,
            'vigente_hasta' => now()->addDays(10)->format('Y-m-d\TH:i'), 'estado' => 'borrador', 'conceptos' => $conceptos,
        ];
    }

    public function test_cualquier_cotizacion_se_puede_costear_con_los_componentes_del_negocio(): void
    {
        // 20 playeras: proveedor $80 c/u (tercero), gasolina $200 total (operativo), utilidad 30 %
        $this->actingAs($this->admin)->post('/admin/presupuestos', $this->datos([[
            'titulo' => 'Playeras bordadas', 'cantidad' => 20, 'precio' => 117, 'utilidad' => 999, 'utilidad_modo' => 'pct',
            'costos' => [
                ['clave' => 'proveedor', 'nombre' => 'Proveedor o maquila', 'modo' => 'unidad', 'clase' => 'tercero', 'monto' => 80],
                ['clave' => 'gasolina', 'nombre' => 'Gasolina y traslados', 'modo' => 'fijo', 'clase' => 'operativo', 'monto' => 200],
                ['clave' => 'horas', 'nombre' => 'Horas del equipo', 'modo' => 'fijo', 'clase' => 'operativo', 'monto' => ''],
            ],
        ], ['titulo' => 'Diseño web', 'cantidad' => 1, 'precio' => 5000]]))->assertRedirect();

        $p = Presupuesto::latest('id')->first();
        [$playeras, $web] = $p->conceptos;
        $this->assertCount(2, $playeras->costos, 'Solo se guardan los costos con monto');
        $this->assertEquals(1800, $playeras->costo);               // 20×80 + 200
        $this->assertEquals(1600, $playeras->costoDe('tercero'));
        $this->assertEquals(200, $playeras->costoDe('operativo'));
        $this->assertEquals(117, $playeras->precio);               // el precio que quedó en el formulario
        $this->assertEquals(30, $playeras->utilidad);              // la utilidad se calcula de ese precio: 540 / 1800
        $this->assertEquals(540, $playeras->ganancia);
        $this->assertFalse($web->tiene_costeo);

        $this->assertTrue($p->usa_costeo);
        $this->assertEquals(2340, $p->venta_costeada);
        $this->assertEquals(23.1, $p->margen);                     // 540 / 2340
        $this->assertEquals(7340, $p->subtotal);

        // Al editarla se ven los montos guardados
        $this->get("/admin/presupuestos/{$p->id}/edit")->assertOk()->assertSee('Calcular precio con costos')->assertSee('\u0022monto\u0022:80', false);

        // Duplicar conserva el costeo
        $this->post("/admin/presupuestos/{$p->id}/duplicar")->assertRedirect();
        $this->assertEquals(1800, Presupuesto::latest('id')->first()->conceptos->first()->costo);
    }

    public function test_el_precio_que_escribes_se_respeta_y_la_utilidad_se_ajusta(): void
    {
        // Cobras $22,000 + IVA y tus costos suman $5,369.96: el precio no se mueve
        $this->actingAs($this->admin)->post('/admin/presupuestos', $this->datos([[
            'titulo' => 'Video corporativo', 'cantidad' => 1, 'precio' => 22000, 'utilidad' => 30, 'utilidad_modo' => 'pct',
            'costos' => [
                ['clave' => 'freelance', 'nombre' => 'Freelance o terceros', 'modo' => 'fijo', 'clase' => 'tercero', 'monto' => 4000],
                ['clave' => 'gasolina', 'nombre' => 'Gasolina y traslados', 'modo' => 'fijo', 'clase' => 'operativo', 'monto' => 1369.96],
            ],
        ]]))->assertRedirect();
        $p = Presupuesto::latest('id')->first();
        $c = $p->conceptos->first();
        $this->assertEquals(22000, $c->precio);
        $this->assertEquals(22000, $p->subtotal);
        $this->assertEquals(3520, $p->iva);
        $this->assertEquals(25520, $p->total);
        $this->assertEquals(16630.04, $c->ganancia);
        $this->assertEquals(309.69, $c->utilidad);                  // % sobre el costo
        $this->assertEquals(75.6, $p->margen);                      // % de lo vendido

        // Con utilidad en $ también se calcula del precio
        $this->put("/admin/presupuestos/{$p->id}", $this->datos([[
            'titulo' => 'Video corporativo', 'cantidad' => 1, 'precio' => 22000, 'utilidad' => 1, 'utilidad_modo' => 'monto',
            'costos' => [['clave' => 'freelance', 'nombre' => 'Freelance o terceros', 'modo' => 'fijo', 'clase' => 'tercero', 'monto' => 4000]],
        ]]))->assertRedirect();
        $c = $p->fresh('conceptos')->conceptos->first();
        $this->assertEquals(22000, $c->precio);
        $this->assertEquals(18000, $c->utilidad);
    }

    public function test_el_costeo_anterior_pasa_a_componentes(): void
    {
        $p = Presupuesto::nuevaPara($this->c);
        $p->save();
        $id = DB::table('presupuesto_conceptos')->insertGetId(['presupuesto_id' => $p->id, 'cuenta_id' => 1, 'descripcion' => 'Lona', 'cantidad' => 2, 'precio' => 500,
            'costo_proveedor' => 150, 'gasolina' => 100, 'utilidad' => 50, 'utilidad_modo' => 'pct', 'orden' => 0, 'created_at' => now(), 'updated_at' => now()]);
        // Lo que hace la migración con lo que ya existía
        (require database_path('migrations/2026_10_19_000001_costeo_por_componentes.php'))->down();
        (require database_path('migrations/2026_10_19_000001_costeo_por_componentes.php'))->up();

        $c = \App\Models\PresupuestoConcepto::find($id);
        $this->assertSame(['proveedor', 'gasolina'], array_column($c->costos, 'clave'));
        $this->assertEquals(400, $c->costo);
        $this->assertEquals(300, $c->costoDe('tercero'));
    }

    public function test_cada_negocio_configura_sus_costos(): void
    {
        $this->actingAs($this->admin)->get('/admin/costeo')->assertOk()->assertSee('Proveedor o maquila');

        $this->put('/admin/costeo', ['utilidad' => 40, 'componentes' => [
            ['nombre' => 'Tinta y material', 'modo' => 'unidad', 'clase' => 'tercero'],
            ['nombre' => 'Diseño', 'modo' => 'fijo', 'clase' => 'operativo'],
            ['nombre' => 'Diseño', 'modo' => 'fijo', 'clase' => 'operativo'],
        ]])->assertRedirect();
        $this->assertSame(['tinta_y_material', 'diseno', 'diseno_2'], array_column(Costeo::componentes(), 'clave'));
        $this->assertEquals(40, Costeo::utilidad());
        $this->get('/admin/presupuestos/create')->assertOk()->assertSee('Tinta y material');

        // Otro giro trae sus propios costos de fábrica
        $arq = Cuenta::create(['nombre' => 'Estudio A', 'giro' => 'arquitectura', 'activa' => true]);
        Cuentas::como($arq, fn () => $this->assertSame('Materiales', Costeo::componentes()[0]['nombre']));
        $this->assertSame('Tinta y material', Costeo::componentes()[0]['nombre'], 'Al volver, la cuenta principal conserva los suyos');

        $this->post('/admin/costeo/restaurar')->assertRedirect();
        $this->assertSame('proveedor', Costeo::componentes()[0]['clave']);
        $this->put('/admin/costeo', ['utilidad' => 10, 'componentes' => []])->assertSessionHasErrors('componentes');
    }
}
