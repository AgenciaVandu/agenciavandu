<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Mail\CorreoVandu;
use App\Models\Cuenta;
use App\Models\Licencia;
use App\Models\Plan;
use App\Models\Rol;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Licencias\Estado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Licenciamiento: planes con límites, pagos que recorren el vencimiento, avisos, solo lectura y suspensión */
class LicenciasTest extends TestCase
{
    use RefreshDatabase;

    private User $alvar;
    private Cuenta $tienda;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hoy('2026-11-01 10:00');
        $this->alvar = User::factory()->create(['name' => 'Alvar', 'plataforma' => true]);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar(Cuentas::principalId());
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

    private function asignar(string $plan, array $extra = []): Licencia
    {
        $this->actingAs($this->alvar)->put(route('admin.plataforma.licencia.update', $this->tienda), $extra + [
            'tipo' => 'suscripcion', 'plan_id' => Plan::where('clave', $plan)->value('id'), 'periodo' => 'mensual',
            'inicio' => '2026-11-01', 'dias_gracia' => 5, 'dias_lectura' => 10, 'implementacion' => 3500,
        ])->assertSessionHasNoErrors()->assertRedirect();
        return Licencia::where('cuenta_id', $this->tienda->id)->firstOrFail();
    }

    private function pagar(array $d = []): void
    {
        $this->actingAs($this->alvar)->post(route('admin.plataforma.licencia.pagar', $this->tienda), $d + [
            'fecha' => now('America/Merida')->toDateString(), 'concepto' => 'periodo', 'monto' => 1000, 'periodos' => 1, 'forma' => 'transferencia',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');
    }

    public function test_asignar_plan_aplica_limites_y_el_pago_recorre_el_vencimiento(): void
    {
        $this->actingAs($this->alvar)->get(route('admin.plataforma.planes'))->assertOk()->assertSee('Fundador')->assertSee('Lectura con IA');
        $this->get(route('admin.plataforma.licencia', $this->tienda))->assertOk()->assertSee('Sin licencia');

        $ia = Plan::where('clave', 'ia')->first();
        $l = $this->asignar('esencial', ['extras' => [$ia->id => ['cantidad' => 1, 'precio' => 299], Plan::where('clave', 'sucursal')->value('id') => ['cantidad' => 1, 'precio' => 500]]]);
        $this->assertEquals([1399.0, '2026-11-01', 2], [$l->precio, $l->vence_el->toDateString(), $l->sucursalesMax()]);
        $c = $this->tienda->fresh();
        $this->assertSame(2, $c->ajuste('sucursales_max'));
        $this->assertTrue($c->tiene('gastos') && $c->tiene('ia'), 'El extra de IA prende Gastos e IA');

        // Implementación y primer mes
        $this->pagar(['concepto' => 'implementacion', 'monto' => 3500, 'periodos' => 0]);
        $this->pagar(['monto' => 1399]);
        $l->refresh();
        $this->assertEquals(['2026-12-01', '2026-11-01'], [$l->vence_el->toDateString(), $l->implementacion_pagada_el->toDateString()]);
        $this->assertSame('corriente', Estado::de($c)->clave);
        $this->get(route('admin.plataforma'))->assertOk()->assertSee('Al corriente')->assertSee('Esencial');
        $this->get(route('admin.plataforma.licencia', $this->tienda))->assertOk()->assertSee('Pagado hasta el')->assertSee('$4,899.00 en total');

        // Pagar 2 meses adelantados y luego borrar ese pago regresa el vencimiento
        $this->pagar(['monto' => 2798, 'periodos' => 2]);
        $this->assertSame('2027-02-01', $l->fresh()->vence_el->toDateString());
        $ultimo = $l->pagos()->first();
        $this->delete(route('admin.plataforma.licencia.pagos.destroy', [$this->tienda, $ultimo]))->assertSessionHas('ok');
        $this->assertSame('2026-12-01', $l->fresh()->vence_el->toDateString());

        // Quitar el extra de IA apaga Gastos e IA
        $this->asignar('esencial', ['vence_el' => '2026-12-01']);
        $this->assertFalse($this->tienda->fresh()->tiene('ia'));
    }

    public function test_limite_de_usuarios_del_plan(): void
    {
        $this->asignar('esencial');                                                   // 3 usuarios
        $rol = Rol::sinCuenta()->where('cuenta_id', $this->tienda->id)->where('todo', false)->firstOrFail();
        $this->actingAs($this->dueno);
        Cuentas::activar($this->tienda);
        foreach (['ana@paletas.mx', 'beto@paletas.mx'] as $correo) {
            $this->post(route('admin.usuarios.store'), ['name' => 'Persona', 'email' => $correo, 'rol_id' => $rol->id])->assertSessionHasNoErrors();
        }
        $this->get(route('admin.usuarios'))->assertSee('3 de 3 usuarios activos de tu plan Esencial');
        $this->post(route('admin.usuarios.store'), ['name' => 'Carla', 'email' => 'carla@paletas.mx', 'rol_id' => $rol->id])
            ->assertSessionHasErrors(['email' => 'Tu plan Esencial incluye 3 usuarios activos. Desactiva a alguien o pide usuarios adicionales.']);
        $this->assertDatabaseMissing('users', ['email' => 'carla@paletas.mx']);
    }

    public function test_vencimiento_gracia_solo_lectura_y_suspension(): void
    {
        Mail::fake();
        $l = $this->asignar('esencial', ['vence_el' => '2026-11-10']);
        $this->actingAs($this->dueno);

        // 3 días antes: aviso al dueño, todo funciona
        $this->hoy('2026-11-07 10:00');
        Cuentas::activar($this->tienda);
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee('Tu plan vence en 3 días');
        $this->artisan('vandu:licencias')->assertSuccessful();
        $this->artisan('vandu:licencias')->assertSuccessful();
        Mail::assertSent(CorreoVandu::class, 1);
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('lupe@paletas.mx') && str_contains($m->asunto, 'vence pronto'));

        // En gracia (del 11 al 15): sigue funcionando
        $this->hoy('2026-11-13 10:00');
        $this->post(route('admin.clientes.store'), ['nombre' => 'Cliente en gracia'])->assertSessionDoesntHaveErrors();
        $this->get(route('admin.clientes.index'))->assertSee('Tu plan venció el 10 de noviembre');

        // Solo lectura (del 16 al 25): ve todo, no guarda nada
        $this->hoy('2026-11-17 10:00');
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee('Cliente en gracia')->assertSee('solo lectura');
        $this->from(route('admin.clientes.index'))->post(route('admin.clientes.store'), ['nombre' => 'No pasa'])
            ->assertRedirect(route('admin.clientes.index'))->assertSessionHas('error');
        $this->assertDatabaseMissing('clientes', ['nombre' => 'No pasa']);

        // Suspendida (26 en adelante): no entra al panel, pero puede cerrar sesión; la plataforma sí entra
        $this->hoy('2026-11-26 10:00');
        $this->get(route('admin.clientes.index'))->assertStatus(402)->assertSee('El acceso al panel está suspendido');
        $this->artisan('vandu:licencias')->assertSuccessful();
        Mail::assertSent(CorreoVandu::class, fn ($m) => str_contains($m->asunto, 'acceso al panel suspendido'));
        $this->post(route('logout'))->assertRedirect();
        $this->actingAs($this->alvar)->get(route('admin.plataforma'))->assertOk()->assertSee('Suspendida por pago');

        // Paga tarde: el nuevo mes cuenta desde el día que paga y vuelve a entrar
        $this->pagar(['fecha' => '2026-11-26']);
        $this->assertSame('2026-12-26', $l->fresh()->vence_el->toDateString());
        $this->actingAs($this->dueno);
        Cuentas::activar($this->tienda);
        $this->get(route('admin.clientes.index'))->assertOk()->assertDontSee('suspendido');
    }

    public function test_licencia_de_por_vida_nunca_se_bloquea(): void
    {
        $this->actingAs($this->alvar)->put(route('admin.plataforma.licencia.update', $this->tienda), [
            'tipo' => 'perpetua', 'plan_id' => Plan::where('clave', 'pro')->value('id'), 'periodo' => 'anual', 'mantenimiento' => 6000,
            'inicio' => '2025-10-01', 'vence_el' => '2026-10-01', 'dias_gracia' => 5, 'dias_lectura' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertSame('mantenimiento', Estado::de($this->tienda)->clave);
        $this->actingAs($this->dueno);
        Cuentas::activar($this->tienda);
        $this->post(route('admin.clientes.store'), ['nombre' => 'Sigue trabajando'])->assertSessionDoesntHaveErrors();
        $this->get(route('admin.clientes.index'))->assertOk()->assertSee('mantenimiento anual de tu licencia venció');
        // Pagar el mantenimiento recorre un año
        $this->pagar(['concepto' => 'mantenimiento', 'monto' => 6000, 'periodos' => 1, 'fecha' => '2026-11-01']);
        $this->assertSame('2027-11-01', Licencia::first()->vence_el->toDateString());
        $this->assertSame('perpetua', Estado::de($this->tienda)->clave);
    }
}
