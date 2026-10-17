<?php

namespace Tests\Feature;

use App\Models\Credito;
use App\Models\Cuenta;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Credito as Calculo;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Lote de autos: unidades, venta a crédito con letras, pagos adelantados, moratorios y termómetro */
class AutosTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-16 10:00:00');
        Cuenta::whereKey(1)->update(['giro' => 'autos']);
        Cuentas::activar(1);
        $this->admin = User::factory()->create(['name' => 'Jorge Lote', 'telefono' => '9991112233']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function unidad(array $extra = []): Unidad
    {
        return Unidad::create($extra + ['marca' => 'Nissan', 'modelo' => 'Versa', 'version' => 'Advance', 'anio' => 2021, 'precio' => 200000, 'kilometraje' => 45000, 'transmision' => 'Automática']);
    }

    /** Vende a crédito: $200,000, enganche $50,000, 24 meses al 2 %, primera letra el 16 de noviembre */
    private function vender(Unidad $u, array $extra = []): Credito
    {
        $this->actingAs($this->admin)->post("/admin/unidades/{$u->id}/vender", $extra + [
            'cliente_nombre' => 'Ana Pérez', 'cliente_telefono' => '9997776655', 'tipo' => 'credito', 'fecha' => '2026-10-16',
            'precio' => 200000, 'enganche' => 50000, 'tasa' => 2, 'plazo' => 24, 'primer_vencimiento' => '2026-11-16',
            'moratorio_modo' => 'porcentaje', 'moratorio_valor' => 5, 'dias_gracia' => 0, 'descuento_adelanto' => 500,
        ])->assertRedirect();
        return Credito::latest('id')->firstOrFail();
    }

    public function test_mensualidad_fija_sobre_saldo(): void
    {
        $this->assertSame(7930.66, Calculo::mensualidad(150000, 2, 24));
        $t = Calculo::tabla(150000, 2, 24, '2026-11-16');
        $this->assertCount(24, $t);
        $this->assertSame(3000.0, $t[0]['interes']);
        $this->assertSame(4930.66, $t[0]['capital']);
        $this->assertEqualsWithDelta(150000, array_sum(array_column($t, 'capital')), 0.001);
        $this->assertSame(0.0, $t[23]['saldo']);
        $this->assertSame('2028-10-16', $t[23]['vence_el']);
        $this->assertSame(1250.0, Calculo::mensualidad(15000, 0, 12));
    }

    public function test_alta_de_unidad_con_fotos_y_pagina_publica(): void
    {
        $this->actingAs($this->admin)->post('/admin/unidades', [
            'marca' => 'Mazda', 'modelo' => '3', 'version' => 'i Sport', 'anio' => 2020, 'precio' => 285000, 'kilometraje' => 38000, 'placas' => 'yzx-123-a',
            'ficha' => "Motor 2.5 L\nPantalla táctil", 'publico' => 1, 'vendedor_id' => $this->admin->id,
            'fotos' => [UploadedFile::fake()->image('frente.jpg', 2000, 1500), UploadedFile::fake()->image('interior.jpg', 1200, 900)],
        ])->assertRedirect();
        $u = Unidad::firstOrFail();
        $this->assertSame('mazda-3-2020', $u->slug);
        $this->assertSame('YZX-123-A', $u->placas);
        $this->assertCount(2, $u->fotos);
        $this->get($u->fotos->first()->miniatura_url)->assertOk();

        auth()->logout();
        $this->get('/autos/mazda-3-2020')->assertOk()->assertSee('Mazda 3 2020')->assertSee('$285,000')
            ->assertSee('al mes')->assertSee('wa.me/529991112233', false)->assertSee('Pantalla táctil')->assertSee('"@type":"Car"', false);
    }

    public function test_venta_a_credito_genera_letras_y_la_unidad_queda_vendida(): void
    {
        $u = $this->unidad();
        $c = $this->vender($u);
        $this->assertSame('vendido', $u->fresh()->estado);
        $this->assertSame(150000.0, $c->financiado);
        $this->assertSame(7930.66, $c->mensualidad);
        $this->assertCount(24, $c->letras);
        $this->assertSame('Ana Pérez', $c->cliente->nombre);

        // Vendida ya no se ve en público
        auth()->logout();
        $this->get('/autos/' . $u->slug)->assertNotFound();
    }

    public function test_pago_puntual_con_adelanto_de_la_ultima_letra(): void
    {
        $c = $this->vender($this->unidad());
        $q = Calculo::cotizarPago($c, Carbon::parse('2026-11-16'), 1, true);
        $ultima = $c->letras->last();
        $this->assertTrue($q['puede_adelantar']);
        $this->assertSame(24, $q['adelanto']['numero']);
        // Letra 1 completa + capital de la 24 (sin intereses) − $500 de descuento
        $this->assertEqualsWithDelta(7930.66 + $ultima->capital - 500, $q['total'], 0.001);

        $this->post("/admin/cobranza/{$c->id}/pago", ['fecha' => '2026-10-16', 'cuantas' => 1, 'adelantar' => 1, 'metodo' => 'Efectivo'])->assertRedirect();
        $c->refresh()->load('letras');
        $this->assertNotNull($c->letras->firstWhere('numero', 1)->pagada_el);
        $l24 = $c->letras->firstWhere('numero', 24);
        $this->assertTrue($l24->adelantada);
        $this->assertEqualsWithDelta($l24->interes + 500, $l24->descuento, 0.001);
        // Lo siguiente es la 2; la nueva "última" es la 23
        $this->assertSame(2, $c->proxima->numero);
        $this->assertSame(23, Calculo::cotizarPago($c, Carbon::parse('2026-12-16'), 1, true)['adelanto']['numero']);
    }

    public function test_no_se_adelanta_despues_del_vencimiento_y_se_cobra_moratorio(): void
    {
        $c = $this->vender($this->unidad());
        $q = Calculo::cotizarPago($c, Carbon::parse('2026-11-20'), 1, true);
        $this->assertFalse($q['puede_adelantar']);
        $this->assertNull($q['adelanto']);
        $this->assertSame(396.53, $q['letras'][0]['moratorio']);   // 5 % de 7,930.66
        $this->assertEqualsWithDelta(7930.66 + 396.53, $q['total'], 0.001);

        // Por día: $50 por cada día después de 3 de gracia
        $c->update(['moratorio_modo' => 'dia', 'moratorio_valor' => 50, 'dias_gracia' => 3]);
        $this->assertSame(0.0, Calculo::cotizarPago($c->fresh(), Carbon::parse('2026-11-19'))['letras'][0]['moratorio']);
        $this->assertSame(200.0, Calculo::cotizarPago($c->fresh(), Carbon::parse('2026-11-20'))['letras'][0]['moratorio']);
    }

    public function test_termometro_configurable_por_el_lote(): void
    {
        $c = $this->vender($this->unidad());
        Carbon::setTestNow('2027-01-10 10:00:00');   // vencidas: 16 nov y 16 dic
        $c = $c->fresh('letras');
        $this->assertCount(2, $c->atrasadas());
        $this->assertSame('Cuidado', $c->termometro['nombre']);

        $this->post('/admin/credito', ['tasa' => 2, 'plazo' => 24, 'enganche_pct' => 30, 'moratorio_modo' => 'porcentaje', 'moratorio_valor' => 5, 'dias_gracia' => 0, 'descuento_adelanto' => 0,
            'termometro' => [['nombre' => 'Al corriente', 'hasta' => 0, 'color' => '#0a7d4a'], ['nombre' => 'Vigilar', 'hasta' => 3, 'color' => '#b7791f'], ['nombre' => 'Jurídico', 'hasta' => null, 'color' => '#c2372f']],
        ])->assertRedirect();
        Cuentas::activar(1);
        $this->assertSame('Vigilar', $c->fresh('letras')->termometro['nombre']);

        $this->get('/admin/cobranza')->assertOk()->assertSee('Vigilar')->assertSee('Ana Pérez');
        $this->get('/admin')->assertOk()->assertSee('Termómetro de clientes')->assertSee('Clientes con adeudo');
    }

    public function test_liquidar_y_deshacer_pago(): void
    {
        $c = $this->vender($this->unidad(), ['plazo' => 2]);
        $this->post("/admin/cobranza/{$c->id}/pago", ['fecha' => '2026-10-16', 'cuantas' => 2]);
        $this->assertSame('liquidado', $c->fresh()->estado);

        $pago = $c->pagos()->first();
        $this->delete("/admin/cobranza/{$c->id}/pago/{$pago->id}")->assertRedirect();
        $this->assertSame('activo', $c->fresh()->estado);
        $this->assertSame(2, $c->letras()->whereNull('pagada_el')->count());
    }

    public function test_venta_de_contado_y_cancelar_venta(): void
    {
        $u = $this->unidad();
        $c = $this->vender($u, ['tipo' => 'contado', 'enganche' => null, 'plazo' => null]);
        $this->assertSame('liquidado', $c->estado);
        $this->assertCount(0, $c->letras);
        $this->post("/admin/cobranza/{$c->id}/cancelar")->assertRedirect();
        $this->assertSame('disponible', $u->fresh()->estado);
    }

    public function test_sin_proyectos_y_permisos_por_rol(): void
    {
        $this->actingAs($this->admin)->get('/admin/proyectos')->assertForbidden();
        $this->get('/admin/unidades')->assertOk()->assertDontSee('admin/proyectos"', false);

        $rol = Rol::create(['nombre' => 'Vendedor', 'permisos' => ['unidades']]);
        $v = User::factory()->create(['rol_id' => $rol->id]);
        $u = $this->unidad();
        $this->actingAs($v)->get('/admin/unidades')->assertOk();
        $this->get("/admin/unidades/{$u->id}/vender")->assertForbidden();
        $this->get('/admin/cobranza')->assertForbidden();
    }

    public function test_sucursales_segun_el_plan(): void
    {
        $this->actingAs($this->admin)->post('/admin/sucursales', ['nombre' => 'Matriz'])->assertRedirect();
        $this->post('/admin/sucursales', ['nombre' => 'Norte'])->assertSessionHas('error');
        $this->assertSame(1, Sucursal::count());

        $c = Cuenta::find(1);
        $c->update(['ajustes' => array_merge($c->ajustes ?? [], ['sucursales_max' => 2])]);
        Cuentas::activar(1);
        $this->post('/admin/sucursales', ['nombre' => 'Norte'])->assertSessionHas('ok');
        $this->assertSame(2, Sucursal::count());
    }

    public function test_lote_de_ejemplo_con_clientes_al_corriente_y_morosos(): void
    {
        $this->actingAs($this->admin)->post('/admin/unidades/ejemplos')->assertRedirect('/admin/unidades')->assertSessionHas('ok');
        $this->assertSame(10, Unidad::where('ejemplo', true)->count());
        $this->assertSame(5, Unidad::where('estado', 'vendido')->count());
        $this->assertSame(1, Unidad::where('estado', 'apartado')->count());
        $this->assertSame(10, \App\Models\UnidadFoto::count());
        $this->assertSame(5, \App\Models\Cliente::where('ejemplo', true)->count());

        $niveles = Credito::with('letras')->get()->map(fn ($c) => $c->termometro['nombre'])->countBy()->all();
        $this->assertSame(['Saludable' => 2, 'Atención' => 1, 'Riesgo' => 2], collect($niveles)->sortKeys()->sortBy(fn ($n, $k) => array_search($k, ['Saludable', 'Atención', 'Riesgo']))->all());
        $this->assertGreaterThan(0, \App\Models\CreditoPago::count());

        // Se ve en las pantallas y no se duplica
        $this->get('/admin/unidades')->assertOk()->assertSee('Quitar ejemplos');
        $this->get('/admin/cobranza')->assertOk()->assertSee('Roberto Ek Balam');
        $this->get('/admin')->assertOk()->assertSee('Gabriela Tun Poot');
        $this->post('/admin/unidades/ejemplos');
        $this->assertSame(10, Unidad::count());

        $this->delete('/admin/unidades/ejemplos')->assertSessionHas('ok');
        $this->assertSame(0, Unidad::count());
        $this->assertSame(0, Credito::count());
        $this->assertSame(0, \App\Models\CreditoLetra::count());
        $this->assertSame(0, \App\Models\Cliente::where('ejemplo', true)->count());
        $this->assertSame([], Storage::disk('local')->allFiles('unidades'));
    }
}
