<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Acceso;
use App\Models\AccesoDispositivo;
use App\Models\Clase;
use App\Models\ClaseTipo;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Paquete;
use App\Models\Reservacion;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Credencial;
use App\Support\Fitness\Membresias;
use App\Support\Fitness\Reservas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Torniquete Control iD (iDFace) en modo online: el equipo pregunta a Nerby en cada lectura.
 * Pasa quien trae su credencial del celular (cambia cada minuto) y tiene una clase reservada en la ventana de entrada.
 */
class TorniqueteTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $gym;
    private User $dueno;
    private AccesoDispositivo $tq;
    private Cliente $sofia;
    private Clase $clase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 06:50:00', 'America/Merida'));
        [$this->gym, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura', 'dueno_email' => 'laura@axion.mx']);
        Cuentas::activar($this->gym);
        $tipo = ClaseTipo::create(['nombre' => 'HYROX Engine', 'duracion' => 60, 'cupo' => 12, 'color' => '#E8590C']);
        $i = Carbon::parse('2026-10-21 07:00', 'America/Merida');
        $this->clase = Clase::create(['clase_tipo_id' => $tipo->id, 'inicia_at' => $i->copy()->utc(), 'termina_at' => $i->copy()->addHour()->utc(), 'cupo' => 10]);
        $this->sofia = Cliente::create(['nombre' => 'Sofía Medina', 'email' => 'sofia@mail.mx', 'telefono' => '9991112233']);
        Membresias::asignar($this->sofia, Paquete::create(['nombre' => 'Paquete 8 clases', 'precio' => 1050, 'creditos' => 8, 'vigencia_dias' => 30]), ['pagado' => true]);
        $this->tq = AccesoDispositivo::create(['nombre' => 'Entrada']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function leer(string $codigo, ?AccesoDispositivo $d = null)
    {
        return $this->post('/api/controlid/' . ($d ?? $this->tq)->token . '/new_qrcode.fcgi', ['qrcode_value' => $codigo, 'device_id' => 99, 'portal_id' => 1]);
    }

    public function test_la_credencial_del_celular_cambia_cada_minuto(): void
    {
        $t = time();
        $qr = Credencial::qr($this->sofia, $t);
        $this->assertStringStartsWith('FIT2:' . $this->sofia->id . ':', $qr);
        $this->assertSame([$this->sofia->id, 'dinamica'], [Credencial::leer($qr, $t)[0]?->id, Credencial::leer($qr, $t)[1]]);
        $this->assertSame('dinamica', Credencial::leer($qr, $t + 120)[1]);
        $this->assertSame('vencido', Credencial::leer($qr, $t + 60 * 6)[1]);
        $falso = substr($qr, 0, -1) . (str_ends_with($qr, '0') ? '1' : '0');
        $this->assertSame([null, 'desconocido'], Credencial::leer($falso, $t));
        $this->assertSame('fija', Credencial::leer($this->sofia->qr)[1]);

        // Si se le genera un QR nuevo, el secreto cambia y los códigos anteriores dejan de servir
        $this->sofia->nuevoQr();
        $this->assertNull(Credencial::leer($qr, $t)[0]);
    }

    public function test_sin_reserva_no_pasa_y_con_reserva_pasa_una_vez(): void
    {
        $this->post('/api/controlid/' . $this->tq->token . '/device_is_alive.fcgi')->assertOk();
        $this->assertNotNull($this->tq->fresh()->ultimo_contacto_at);

        $this->leer(Credencial::qr($this->sofia))->assertOk()
            ->assertJsonPath('result.event', 6)->assertJsonPath('result.actions', [])
            ->assertJsonPath('result.message', 'Sofía, no tienes clase reservada a esta hora.');

        $r = Reservas::reservar($this->clase, $this->sofia, 'portal');
        $this->leer(Credencial::qr($this->sofia))->assertOk()
            ->assertJsonPath('result.event', 7)->assertJsonPath('result.user_id', $this->sofia->id)
            ->assertJsonPath('result.actions.0', ['action' => 'sec_box', 'parameters' => 'id=65793,reason=3'])
            ->assertJsonPath('result.message', '¡Adelante, Sofía! HYROX Engine · te quedan 7 clases');
        $this->assertSame('asistio', $r->fresh()->estado);

        // Otra vez: ya entró
        $this->leer(Credencial::qr($this->sofia))->assertJsonPath('result.event', 6)->assertJsonPath('result.message', 'Sofía, ya entraste a las 06:50.');
        $this->tq->update(['reingreso' => true]);
        $this->leer(Credencial::qr($this->sofia))->assertJsonPath('result.event', 7);

        $this->assertSame(2, Acceso::where('permitido', true)->count());
        $this->assertSame(2, Acceso::where('permitido', false)->count());
    }

    public function test_fuera_de_la_ventana_le_dice_a_que_hora(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 06:00:00', 'America/Merida'));
        Reservas::reservar($this->clase, $this->sofia, 'portal');
        $this->leer(Credencial::qr($this->sofia))->assertJsonPath('result.event', 6)
            ->assertJsonPath('result.message', 'Sofía, tu clase es a las 07:00. Puedes entrar 30 min antes.');
    }

    public function test_credencial_impresa_vencida_ajena_y_desconocida(): void
    {
        Reservas::reservar($this->clase, $this->sofia, 'portal');
        $this->leer($this->sofia->qr)->assertJsonPath('result.event', 6)->assertJsonPath('result.message', 'Usa la credencial de tu celular.');
        $this->leer(Credencial::qr($this->sofia, time() - 600))->assertJsonPath('result.event', 6)->assertJsonPath('result.message', 'Ese QR ya cambió. Abre de nuevo tu credencial.');
        $this->leer('hola')->assertJsonPath('result.event', 6);

        // El código de un atleta de otro gimnasio no abre aquí
        [$otro] = PlataformaController::crearCuenta(['nombre' => 'Otro Box', 'giro' => 'fitness', 'dueno_nombre' => 'X', 'dueno_email' => 'x@otro.mx']);
        Cuentas::activar($otro);
        $ajeno = Cliente::create(['nombre' => 'Ajeno', 'telefono' => '9990000000']);
        $codigo = Credencial::qr($ajeno);
        $this->leer($codigo)->assertJsonPath('result.event', 6)->assertJsonPath('result.user_id', 0);

        Cuentas::activar($this->gym);
        $this->tq->update(['credencial_fija' => true]);
        $this->leer($this->sofia->qr)->assertJsonPath('result.event', 7);
    }

    public function test_rostro_identificado_boton_y_equipos_desconocidos(): void
    {
        Reservas::reservar($this->clase, $this->sofia, 'portal');
        $base = '/api/controlid/' . $this->tq->token;
        $this->post($base . '/new_user_identified.fcgi', ['user_id' => $this->sofia->id, 'event' => 7, 'portal_id' => 1])->assertJsonPath('result.event', 7);
        $this->assertSame('rostro', Acceso::latest('id')->first()->metodo);
        $this->post($base . '/new_rex_log.fcgi')->assertOk();
        $this->assertSame('boton', Acceso::latest('id')->first()->motivo);
        $this->post($base . '/new_card.fcgi', ['card_value' => 123456])->assertJsonPath('result.event', 6);

        $this->post('/api/controlid/' . str_repeat('a', 32) . '/new_qrcode.fcgi', ['qrcode_value' => 'x'])->assertNotFound();
        $this->tq->update(['activo' => false]);
        $this->leer(Credencial::qr($this->sofia))->assertNotFound();
    }

    public function test_pantalla_de_torniquetes_y_credencial_del_atleta(): void
    {
        $this->actingAs($this->dueno)->get(route('admin.torniquetes'))->assertOk()->assertSee('Entrada')->assertSee($this->tq->url);
        $this->post(route('admin.torniquetes.store'), ['nombre' => 'Puerta trasera'])->assertSessionHas('ok');
        $this->assertSame(2, AccesoDispositivo::count());

        Reservas::reservar($this->clase, $this->sofia, 'portal');
        $this->post(route('admin.torniquetes.probar', $this->tq), ['codigo' => Credencial::qr($this->sofia)])->assertSessionHas('ok', 'Abriría: ¡Adelante, Sofía! HYROX Engine · te quedan 7 clases');
        $this->get(route('admin.torniquetes'))->assertSee('Sofía Medina')->assertSee('Pasó');

        $viejo = $this->tq->token;
        $this->post(route('admin.torniquetes.token', $this->tq))->assertSessionHas('ok');
        $this->post('/api/controlid/' . $viejo . '/device_is_alive.fcgi')->assertNotFound();

        $coach = User::create(['name' => 'Coach', 'email' => 'coach@axion.mx', 'password' => 'x', 'activo' => true, 'rol_id' => \App\Models\Rol::where('nombre', 'Entrenador')->value('id')]);
        $this->actingAs($coach)->get(route('admin.torniquetes'))->assertForbidden();

        $this->actingAs($this->sofia, 'atleta')->get(route('atleta.credencial'))->assertOk()->assertSee('FIT2:' . $this->sofia->id . ':', false)->assertSee('Se renueva cada minuto');
        $this->getJson(route('atleta.credencial.codigo'))->assertOk()->assertJsonStructure(['qr']);
    }
}
