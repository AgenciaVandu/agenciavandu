<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Mail\CorreoVandu;
use App\Models\Cuenta;
use App\Models\CuentaDinero;
use App\Models\Licencia;
use App\Models\LicenciaComprobante;
use App\Models\LicenciaPago;
use App\Models\Plan;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Dinero\Movimientos;
use App\Support\Licencias\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Mi plan (dueño), comprobantes "Ya pagué", recibos, números de la plataforma y licencias en las Finanzas de la principal */
class MiPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $alvar;
    private Cuenta $tienda;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        $this->hoy('2026-11-01 10:00');
        $this->alvar = User::factory()->create(['name' => 'Alvar', 'plataforma' => true, 'email' => 'alvar@vandu.test']);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar(Cuentas::principalId());
        $this->actingAs($this->alvar)->put(route('admin.plataforma.licencia.update', $this->tienda), [
            'tipo' => 'suscripcion', 'plan_id' => Plan::where('clave', 'fundador')->value('id'), 'periodo' => 'mensual',
            'inicio' => '2026-10-01', 'vence_el' => '2026-11-10', 'dias_gracia' => 5, 'dias_lectura' => 10, 'implementacion' => 3500,
        ])->assertSessionHasNoErrors();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function hoy(string $f): void
    {
        Carbon::setTestNow(Carbon::parse($f, 'America/Merida'));
        Estado::olvidar();
    }

    private function comoDueno(): void
    {
        $this->actingAs($this->dueno);
        Cuentas::activar($this->tienda);
        Estado::olvidar();
    }

    public function test_mi_plan_comprobante_confirmado_y_recibo(): void
    {
        $this->comoDueno();
        $this->get(route('admin.clientes.index'))->assertSee('Mi plan');
        $this->get(route('admin.mi-plan'))->assertOk()->assertSee('Fundador')->assertSee('Tu plan está pagado hasta')->assertSee('10 de noviembre de 2026')->assertSee('Ya pagué');

        $this->post(route('admin.mi-plan.comprobante'), ['monto' => 1000, 'fecha' => '2026-11-01', 'referencia' => 'SPEI 123',
            'archivo' => UploadedFile::fake()->image('comprobante.jpg')])->assertSessionHas('ok');
        $comp = LicenciaComprobante::firstOrFail();
        Storage::assertExists($comp->archivo);
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('alvar@vandu.test') && str_contains($m->asunto, 'Comprobante de pago'));
        $this->get(route('admin.mi-plan'))->assertSee('Por confirmar');

        // La plataforma lo ve y lo confirma
        $this->actingAs($this->alvar);
        Cuentas::activar(Cuentas::principalId());
        $this->get(route('admin.plataforma'))->assertSee('1 comprobante de pago por revisar');
        $this->get(route('admin.plataforma.licencia', $this->tienda))->assertSee('Comprobante por revisar')->assertSee('SPEI 123');
        $this->get(route('admin.plataforma.licencia.comprobante', [$this->tienda, $comp]))->assertOk();
        $this->post(route('admin.plataforma.licencia.confirmar', [$this->tienda, $comp]), ['concepto' => 'periodo', 'periodos' => 1, 'monto' => 1000, 'forma' => 'transferencia'])->assertSessionHas('ok');
        $this->assertSame('aprobado', $comp->fresh()->estado);
        $this->assertSame('2026-12-10', Licencia::first()->vence_el->toDateString());
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('lupe@paletas.mx') && str_contains($m->cuerpo, 'pagado hasta el 10 de diciembre de 2026'));

        // Recibo en PDF: el dueño ve el suyo, no el de otro negocio
        $pago = LicenciaPago::firstOrFail();
        $this->comoDueno();
        $this->get(route('admin.mi-plan'))->assertSee('Recibo');
        $this->get(route('admin.mi-plan.recibo', $pago))->assertOk()->assertHeader('content-type', 'application/pdf');
        [$otra] = PlataformaController::crearCuenta(['nombre' => 'Otra', 'giro' => 'comercio', 'dueno_nombre' => 'X', 'dueno_email' => 'x@otra.mx']);
        $ajeno = LicenciaPago::create(['cuenta_id' => $otra->id, 'licencia_id' => Licencia::first()->id, 'fecha' => '2026-11-01', 'concepto' => 'otro', 'monto' => 5]);
        Cuentas::activar($this->tienda);
        $this->get(route('admin.mi-plan.recibo', $ajeno))->assertNotFound();
    }

    public function test_suspendido_puede_subir_comprobante_y_rechazo_con_motivo(): void
    {
        $this->hoy('2026-11-27 10:00');                                                // venció el 10: suspendido desde el 26
        $this->comoDueno();
        $this->get(route('admin.clientes.index'))->assertStatus(402)->assertSee('Ya pagué: subir comprobante');
        $this->get(route('admin.mi-plan'))->assertOk();
        $this->post(route('admin.mi-plan.comprobante'), ['monto' => 1000, 'fecha' => '2026-11-27', 'archivo' => UploadedFile::fake()->create('pago.pdf', 50, 'application/pdf')])
            ->assertSessionHas('ok');
        $comp = LicenciaComprobante::firstOrFail();

        $this->actingAs($this->alvar);
        Cuentas::activar(Cuentas::principalId());
        $this->post(route('admin.plataforma.licencia.rechazar', [$this->tienda, $comp]), ['motivo' => 'No vemos el depósito'])->assertSessionHas('ok');
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('lupe@paletas.mx') && str_contains($m->cuerpo, 'No vemos el depósito'));
        $this->comoDueno();
        $this->get(route('admin.mi-plan'))->assertSee('No vemos el depósito');
    }

    public function test_numeros_de_la_plataforma_y_licencias_en_mis_finanzas(): void
    {
        // Otra licencia anual (12,000 al año = 1,000 al mes) y una vencida
        [$gym] = PlataformaController::crearCuenta(['nombre' => 'Axion', 'giro' => 'comercio', 'dueno_nombre' => 'L', 'dueno_email' => 'l@axion.mx']);
        $this->put(route('admin.plataforma.licencia.update', $gym), ['tipo' => 'suscripcion', 'plan_id' => Plan::where('clave', 'pro')->value('id'), 'periodo' => 'anual', 'precio' => 12000,
            'inicio' => '2026-01-01', 'vence_el' => '2026-10-20', 'dias_gracia' => 5, 'dias_lectura' => 10])->assertSessionHasNoErrors();

        // Mis Finanzas: banco para transferencias y precios con IVA incluido
        Cuentas::activar(Cuentas::principalId());
        $banco = CuentaDinero::create(['nombre' => 'BBVA', 'tipo' => 'banco', 'formas' => ['transferencia'], 'saldo_inicial' => 0, 'saldo_desde' => '2026-10-01']);
        $this->put(route('admin.plataforma.planes.pago'), ['banco' => 'BBVA', 'clabe' => '012345678901234567', 'iva' => 'incluido'])->assertSessionHas('ok');
        $this->post(route('admin.plataforma.licencia.pagar', $this->tienda), ['fecha' => '2026-11-01', 'concepto' => 'implementacion', 'monto' => 3480, 'forma' => 'transferencia'])->assertSessionHas('ok');
        $p = LicenciaPago::firstOrFail();
        $this->assertEquals([480, $banco->id], [$p->iva, $p->cuenta_dinero_id]);

        $this->get(route('admin.plataforma.numeros'))->assertOk()->assertSee('Ingreso recurrente al mes')->assertSee('$1,000')->assertSee('Axion')->assertSee('Paletas Lupe');
        $n = \App\Support\Licencias\Numeros::datos();
        $this->assertEquals([2000, 1, 3480], [$n['mrr'], $n['vencidos']->count(), $n['cobradoMes']], 'Axion en solo lectura sigue contando; solo los suspendidos salen');

        Movimientos::olvidar();
        $this->assertEquals(3480, Movimientos::saldos()[$banco->id]['saldo']);
        $this->get(route('admin.finanzas', ['seccion' => 'flujo', 'periodo' => 'mes']))->assertOk()->assertSee('Licencias de la plataforma');
        $c = \App\Support\Finanzas\Contabilidad::datos('2026-11-01', '2026-11-30');
        $this->assertEquals([3000, 1, 480], [$c['resultados']['ventasLic'], $c['resultados']['nLic'], $c['iva']['trasladado']]);
    }
}
