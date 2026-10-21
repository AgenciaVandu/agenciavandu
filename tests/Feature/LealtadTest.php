<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Comensal;
use App\Models\Cuenta;
use App\Models\LealtadCampana;
use App\Models\LealtadInscripcion;
use App\Models\LealtadVisita;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Lealtad\Codigo;
use App\Support\Lealtad\Programa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Lealtad para restaurantes: el comensal es de la plataforma (una cuenta y un QR para todos),
 * cada restaurante ve solo a quien se unió a su programa, campañas con premios por visitas, QR que cambia cada minuto.
 */
class LealtadTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $r1;
    private Cuenta $r2;
    private User $dueno1;
    private User $dueno2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 14:00:00', 'America/Merida'));
        [$this->r1, $this->dueno1] = PlataformaController::crearCuenta(['nombre' => 'La Chaya Maya', 'giro' => 'restaurante', 'dueno_nombre' => 'Rosa', 'dueno_email' => 'rosa@chaya.mx']);
        [$this->r2, $this->dueno2] = PlataformaController::crearCuenta(['nombre' => 'Manjar Blanco', 'giro' => 'restaurante', 'dueno_nombre' => 'Pepe', 'dueno_email' => 'pepe@manjar.mx']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function campana(Cuenta $c, array $extra = []): LealtadCampana
    {
        Cuentas::activar($c);
        $k = LealtadCampana::create($extra + ['nombre' => 'Temporada navideña', 'inicia_el' => '2026-11-01', 'termina_el' => '2027-01-01', 'meta_visitas' => 4, 'dias_canje' => 10]);
        foreach ([[2, 'Postre'], [4, 'Cena para dos']] as [$v, $n]) $k->premios()->create(['visitas' => $v, 'nombre' => $n, 'cuenta_id' => $c->id]);
        return $k->load('premios');
    }

    private function comensal(string $nombre = 'Ana López', string $email = 'ana@mail.mx'): Comensal
    {
        return Comensal::create(['nombre' => $nombre, 'email' => $email, 'telefono' => '9991234567', 'password' => 'secreta123']);
    }

    public function test_el_giro_restaurante_trae_su_panel_y_oculta_lo_demas(): void
    {
        Cuentas::activar($this->r1);
        $this->assertTrue($this->r1->tiene('lealtad'));
        foreach (['tareas', 'clientes', 'ventas', 'finanzas', 'cotizaciones', 'proyectos'] as $m) $this->assertFalse($this->r1->tiene($m), $m);
        $this->actingAs($this->dueno1)->get(route('admin.resumen'))->assertOk()->assertSee('Tu programa de lealtad')->assertSee('Campañas')->assertSee('Comensales')
            ->assertDontSee(route('admin.clientes.index'))->assertDontSee(route('admin.finanzas'));
        $this->get(route('admin.clientes.index'))->assertForbidden();
        $this->get(route('admin.lealtad.qr'))->assertOk()->assertSee(route('lealtad.restaurante', $this->r1->slug));

        // Caja / Mesero solo escanea
        $caja = User::create(['name' => 'Toño', 'email' => 'tono@chaya.mx', 'password' => 'x', 'activo' => true, 'rol_id' => \App\Models\Rol::where('nombre', 'Caja / Mesero')->value('id')]);
        $this->actingAs($caja)->get(route('admin.lealtad.escanear'))->assertOk();
        $this->get(route('admin.comensales'))->assertForbidden();
        $this->get(route('admin.lealtad.campanas'))->assertForbidden();

        // Otros giros no tienen lealtad
        [$gym, $g] = PlataformaController::crearCuenta(['nombre' => 'Axion', 'giro' => 'fitness', 'dueno_nombre' => 'L', 'dueno_email' => 'l@axion.mx']);
        Cuentas::activar($gym);
        $this->actingAs($g)->get(route('admin.lealtad.campanas'))->assertForbidden();
    }

    public function test_campanas_con_premios(): void
    {
        $this->actingAs($this->dueno1)->post(route('admin.lealtad.campanas.store'), [
            'nombre' => 'Ruta del sabor', 'inicia_el' => '2026-11-01', 'termina_el' => '2026-12-31', 'meta_visitas' => 10, 'dias_canje' => 15,
            'premios' => [['visitas' => 10, 'nombre' => 'Cena para dos'], ['visitas' => 3, 'nombre' => 'Postre']],
        ])->assertRedirect(route('admin.lealtad.campanas'));
        $k = LealtadCampana::firstOrFail();
        $this->assertSame([3, 10], $k->premios->pluck('visitas')->all());
        $this->assertSame('vigente', $k->estado);

        $this->post(route('admin.lealtad.campanas.store'), ['nombre' => 'Mal', 'inicia_el' => '2026-11-01', 'termina_el' => '2026-12-31', 'meta_visitas' => 5, 'dias_canje' => 0,
            'premios' => [['visitas' => 8, 'nombre' => 'Imposible']]])->assertSessionHasErrors('premios');
        $this->get(route('admin.lealtad.campanas'))->assertOk()->assertSee('Ruta del sabor');
    }

    public function test_registro_desde_la_puerta_con_consentimiento_y_cada_restaurante_ve_solo_a_los_suyos(): void
    {
        $this->campana($this->r1);
        $this->get(route('lealtad.restaurante', $this->r1->slug))->assertOk()->assertSee('Temporada navideña')->assertSee('Crear mi cuenta y unirme');

        // Sin aceptar compartir sus datos no se une
        $datos = ['r' => $this->r1->slug, 'nombre' => 'Ana López', 'telefono' => '999 123 4567', 'email' => 'Ana@Mail.mx', 'password' => 'secreta123', 'password_confirmation' => 'secreta123', 'privacidad' => 1];
        $this->post(route('lealtad.registrar'), $datos)->assertSessionHasErrors('compartir');
        $this->post(route('lealtad.registrar'), $datos + ['compartir' => 1])->assertRedirect(route('lealtad.restaurante', $this->r1->slug));
        $ana = Comensal::where('email', 'ana@mail.mx')->firstOrFail();
        $this->assertTrue(Auth::guard('comensal')->check());
        $this->assertTrue(Programa::inscrito($this->r1, $ana));
        $this->assertFalse(Programa::inscrito($this->r2, $ana));

        // Se une al Restaurante 2 con la misma cuenta
        $this->post(route('lealtad.unirme', $this->r2->slug), [])->assertSessionHasErrors('compartir');
        $this->post(route('lealtad.unirme', $this->r2->slug), ['compartir' => 1])->assertRedirect();
        $this->assertTrue(Programa::inscrito($this->r2, $ana));
        $this->get(route('lealtad.inicio'))->assertOk()->assertSee('La Chaya Maya')->assertSee('Manjar Blanco');

        // Un tercer restaurante donde no está no la ve
        [$r3, $d3] = PlataformaController::crearCuenta(['nombre' => 'El Tercero', 'giro' => 'restaurante', 'dueno_nombre' => 'X', 'dueno_email' => 'x@tercero.mx']);
        Cuentas::activar($r3);
        $this->actingAs($d3)->get(route('admin.comensales'))->assertOk()->assertDontSee('ana@mail.mx');
        Cuentas::activar($this->r1);
        $this->actingAs($this->dueno1)->get(route('admin.comensales'))->assertOk()->assertSee('Ana López')->assertSee('ana@mail.mx');

        // Si se sale del programa, el restaurante deja de ver su contacto
        Programa::baja($this->r1, $ana);
        $this->get(route('admin.comensales'))->assertDontSee('ana@mail.mx');
        $this->get(route('admin.comensales', ['ver' => 'baja']))->assertSee('Sin acceso a sus datos')->assertDontSee('ana@mail.mx');
    }

    public function test_el_qr_cambia_cada_minuto_y_vence(): void
    {
        $ana = $this->comensal();
        $t = time();
        $qr = Codigo::qr($ana, $t);
        $this->assertTrue(Codigo::leer($qr, $t)?->is($ana));
        $this->assertTrue(Codigo::leer($qr, $t + 120)?->is($ana), 'Se acepta un par de minutos de retraso');
        $this->assertNull(Codigo::leer($qr, $t + 60 * 5), 'Una captura vieja ya no sirve');
        $this->assertNotSame($qr, Codigo::qr($ana, $t + 60));
        $this->assertTrue(Codigo::leer(Codigo::corto($ana, $t), $t)?->is($ana));
        $this->assertNull(Codigo::leer('NRB:' . $ana->id . ':' . intdiv($t, 60) . ':000000000000', $t), 'Firma falsa');
        $otro = $this->comensal('Beto', 'beto@mail.mx');
        $this->assertNull(Codigo::leer(str_replace('NRB:' . $ana->id . ':', 'NRB:' . $otro->id . ':', $qr), $t), 'No sirve con otro id');
    }

    public function test_caja_escanea_une_suma_visitas_y_entrega_premios(): void
    {
        $k = $this->campana($this->r1);
        $ana = $this->comensal();
        Cuentas::activar($this->r1);
        $this->actingAs($this->dueno1);

        $r = $this->postJson(route('admin.lealtad.escanear.leer'), ['codigo' => Codigo::qr($ana)])->assertOk()->json();
        $this->assertTrue($r['ok']);
        $this->assertFalse($r['inscrito']);
        $this->assertNull($r['comensal']['telefono'], 'Antes de unirse no ve su teléfono');
        $this->postJson(route('admin.lealtad.escanear.visita'), ['pase' => $r['pase']])->assertJson(['ok' => false]);

        $r = $this->postJson(route('admin.lealtad.escanear.unir'), ['pase' => $r['pase']])->assertJson(['ok' => true, 'inscrito' => true])->json();
        $this->assertSame('caja', Programa::inscripcion($this->r1, $ana)->origen);
        $this->postJson(route('admin.lealtad.escanear.visita'), ['pase' => $r['pase'], 'monto' => 450])->assertJson(['ok' => true]);
        $this->postJson(route('admin.lealtad.escanear.visita'), ['pase' => $r['pase']])->assertJson(['ok' => false])->assertJsonFragment(['mensaje' => 'La visita de hoy de Ana ya está registrada. Solo cuenta una por día.']);

        // Al día siguiente, segunda visita: desbloquea el postre
        Carbon::setTestNow(Carbon::parse('2026-11-11 14:00:00', 'America/Merida'));
        $r = $this->postJson(route('admin.lealtad.escanear.leer'), ['codigo' => Codigo::qr($ana)])->json();
        $v = $this->postJson(route('admin.lealtad.escanear.visita'), ['pase' => $r['pase']])->assertJson(['ok' => true, 'nuevos' => ['Postre']])->json();
        $postre = $k->premios->firstWhere('nombre', 'Postre');
        $cena = $k->premios->firstWhere('nombre', 'Cena para dos');
        $this->assertSame([$postre->id], collect($v['campanas'][0]['disponibles'])->pluck('id')->all());
        $this->postJson(route('admin.lealtad.escanear.canjear'), ['pase' => $v['pase'], 'premio_id' => $cena->id])->assertJson(['ok' => false]);
        $this->postJson(route('admin.lealtad.escanear.canjear'), ['pase' => $v['pase'], 'premio_id' => $postre->id])->assertJson(['ok' => true]);
        $this->postJson(route('admin.lealtad.escanear.canjear'), ['pase' => $v['pase'], 'premio_id' => $postre->id])->assertJson(['ok' => false]);

        // Las visitas cuentan por restaurante: en el 2 empieza de cero
        Programa::unir($this->r2, $ana);
        $this->assertSame(0, LealtadVisita::sinCuenta()->where('cuenta_id', $this->r2->id)->count());
        $this->assertSame(2, Programa::avance($this->r1, $ana)->first()['visitas']);

        // Un pase de otro restaurante o alterado no sirve
        Cuentas::activar($this->r2);
        $this->actingAs($this->dueno2)->postJson(route('admin.lealtad.escanear.visita'), ['pase' => $v['pase']])->assertStatus(422);
        $this->postJson(route('admin.lealtad.escanear.leer'), ['codigo' => 'hola'])->assertJson(['ok' => false, 'mensaje' => 'Ese no es un QR de lealtad.']);
    }

    public function test_app_del_comensal_y_programa_de_ejemplo(): void
    {
        $this->campana($this->r1);
        $ana = $this->comensal();
        Programa::unir($this->r1, $ana);
        $this->actingAs($ana, 'comensal')->get(route('lealtad.inicio'))->assertOk()->assertSee('Hola, Ana')->assertSee('Temporada navideña')->assertSee('Te faltan 2 visitas');
        $this->get(route('lealtad.qr'))->assertOk()->assertSee($ana->id . '-', false);
        $this->getJson(route('lealtad.qr.codigo'))->assertOk()->assertJsonStructure(['qr', 'corto', 'restante']);
        $this->post(route('lealtad.perfil.guardar'), ['nombre' => 'Ana María López', 'telefono' => '9990001111', 'email' => 'ana@mail.mx'])->assertSessionHas('ok');
        $this->assertSame('Ana María López', $ana->fresh()->nombre);
        $this->post(route('lealtad.baja', $this->r1->slug))->assertRedirect(route('lealtad.perfil'));
        $this->assertFalse(Programa::inscrito($this->r1, $ana));
        Auth::guard('comensal')->logout();
        $this->get(route('lealtad.qr'))->assertRedirect(route('lealtad.entrar'));

        // Ejemplo
        Cuentas::activar($this->r2);
        $this->actingAs($this->dueno2)->post(route('admin.lealtad.ejemplos'))->assertRedirect();
        $this->assertSame(2, LealtadCampana::where('ejemplo', true)->count());
        $this->assertGreaterThan(15, LealtadInscripcion::count());
        $this->assertGreaterThan(20, LealtadVisita::count());
        $this->get(route('admin.resumen'))->assertOk()->assertSee('programa de ejemplo');
        $this->delete(route('admin.lealtad.ejemplos.quitar'))->assertRedirect();
        $this->assertSame(0, LealtadInscripcion::count());
        $this->assertSame(0, Comensal::where('ejemplo', true)->count());
        $this->assertTrue($ana->fresh() !== null, 'Los comensales reales no se tocan');
    }
}
