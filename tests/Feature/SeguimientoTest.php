<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Membresia;
use App\Models\Paquete;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Embudo;
use App\Support\Fitness\Membresias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Gimnasio: el seguimiento (interesado → prueba → inscrito → por renovar → vencido) sale de los paquetes */
class SeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $gym;
    private User $dueno;
    private Paquete $prueba;
    private Paquete $ocho;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', 'America/Merida'));
        [$this->gym, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura Gym', 'dueno_email' => 'laura@axion.mx']);
        Cuentas::activar($this->gym);
        $this->prueba = Paquete::create(['nombre' => 'Clase de prueba', 'precio' => 150, 'creditos' => 1, 'vigencia_dias' => 7, 'es_prueba' => true]);
        $this->ocho = Paquete::create(['nombre' => 'Paquete 8 clases', 'precio' => 1000, 'creditos' => 8, 'vigencia_dias' => 30]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function persona(string $nombre): Cliente
    {
        return Cliente::create(['nombre' => $nombre, 'telefono' => '999' . random_int(1000000, 9999999)]);
    }

    private function etapa(Cliente $c): ?string
    {
        $c = $c->fresh();
        return Embudo::etapa($c, $c->membresias);
    }

    public function test_cada_quien_cae_en_su_etapa_segun_sus_paquetes(): void
    {
        $ana = $this->persona('Ana Interesada');
        $this->assertSame('interesado', $this->etapa($ana));

        Membresias::asignar($ana, $this->prueba, []);
        $this->assertSame('prueba', $this->etapa($ana));

        Membresias::asignar($ana, $this->ocho, ['pagado' => true]);
        $this->assertSame('nuevo', $this->etapa($ana));

        // Inscrita hace tiempo y al corriente: ya no necesita seguimiento
        $beto = $this->persona('Beto Constante');
        Membresias::asignar($beto, $this->ocho, ['inicia_el' => '2026-08-01', 'vence_el' => '2026-08-30', 'pagado' => true]);
        Membresias::asignar($beto, $this->ocho, ['inicia_el' => '2026-10-10', 'pagado' => true]);
        $this->assertNull($this->etapa($beto));

        // Le queda 1 clase: por renovar
        Membresia::where('cliente_id', $beto->id)->latest('id')->first()->forceFill(['usados' => 7])->save();
        $this->assertSame('renovar', $this->etapa($beto));

        // Ya pagó el siguiente por adelantado: sale de "por renovar"
        Membresias::asignar($beto, $this->ocho, ['inicia_el' => '2026-11-09', 'pagado' => true]);
        $this->assertNull($this->etapa($beto));

        // Venció hace 20 días sin renovar
        $caro = $this->persona('Caro Vencida');
        Membresias::asignar($caro, $this->ocho, ['inicia_el' => '2026-09-01', 'vence_el' => '2026-09-30', 'pagado' => true]);
        $this->assertSame('vencido', $this->etapa($caro));

        // Venció hace mucho: ya no se sigue
        $dani = $this->persona('Dani Antiguo');
        Membresias::asignar($dani, $this->ocho, ['inicia_el' => '2026-03-01', 'vence_el' => '2026-03-30', 'pagado' => true]);
        $this->assertNull($this->etapa($dani));
    }

    public function test_tablero_registrar_descartar_y_recuperar(): void
    {
        $this->actingAs($this->dueno);
        $this->post(route('admin.seguimiento.store'), ['nombre' => 'Paola Chan', 'telefono' => '999 233 8104', 'origen' => 'whatsapp', 'interes' => 'HYROX'])
            ->assertSessionHas('ok');
        $paola = Cliente::where('nombre', 'Paola Chan')->firstOrFail();
        $this->assertSame('whatsapp', $paola->origen);

        // Mismo teléfono: no se duplica
        $this->post(route('admin.seguimiento.store'), ['nombre' => 'Paola', 'telefono' => '9992338104', 'origen' => 'redes'])
            ->assertRedirect(route('admin.clientes.show', [$paola, 'ver' => 'atleta']));
        $this->assertSame(1, Cliente::where('telefono', 'like', '%8104')->count());

        $this->get(route('admin.seguimiento'))->assertOk()->assertSee('Paola Chan')->assertSee('Agendar prueba')
            ->assertSee('paquete=' . $this->prueba->id, false);

        $this->post(route('admin.seguimiento.tarea', $paola), ['titulo' => 'Escribirle', 'fecha_limite' => '2026-10-21'])->assertSessionHas('ok');
        $this->assertSame(1, Tarea::where('cliente_id', $paola->id)->abiertas()->count());

        $this->post(route('admin.seguimiento.descartar', $paola), ['motivo' => 'Horarios'])->assertSessionHas('ok');
        $this->assertSame('descartado', $this->etapa($paola));
        $this->assertSame(0, Tarea::where('cliente_id', $paola->id)->abiertas()->count());
        $this->get(route('admin.seguimiento', ['ver' => 'descartados']))->assertSee('Paola Chan')->assertSee('Horarios');

        $this->post(route('admin.seguimiento.recuperar', $paola))->assertSessionHas('ok');
        $this->assertSame('interesado', $this->etapa($paola));

        // Asignarle un paquete también la saca de descartados
        $paola->forceFill(['descartado_at' => now(), 'motivo_descarte' => 'Precio'])->save();
        Membresias::asignar($paola->fresh(), $this->prueba, []);
        $this->assertSame('prueba', $this->etapa($paola));
    }

    public function test_ventas_del_gimnasio_lleva_al_seguimiento_y_respeta_permisos(): void
    {
        $this->actingAs($this->dueno);
        $this->get(route('admin.ventas.index'))->assertRedirect(route('admin.seguimiento'));

        $coach = User::create(['name' => 'Coach', 'email' => 'coach@axion.mx', 'password' => 'x', 'activo' => true,
            'rol_id' => \App\Models\Rol::where('nombre', 'Entrenador')->value('id')]);
        $this->actingAs($coach)->get(route('admin.seguimiento'))->assertForbidden();

        // Otros giros no tienen esta pantalla
        [$agencia, $otro] = PlataformaController::crearCuenta(['nombre' => 'Estudio', 'giro' => 'agencia', 'dueno_nombre' => 'Otro', 'dueno_email' => 'otro@estudio.mx']);
        Cuentas::activar($agencia);
        $this->actingAs($otro)->get(route('admin.seguimiento'))->assertNotFound();
    }

    public function test_resultados_conversion_y_renovacion(): void
    {
        // Dos probaron este mes, una se inscribió
        $a = $this->persona('A');
        Membresias::asignar($a, $this->prueba, ['inicia_el' => '2026-10-02']);
        Membresias::asignar($a, $this->ocho, ['inicia_el' => '2026-10-05', 'pagado' => true]);
        $b = $this->persona('B');
        Membresias::asignar($b, $this->prueba, ['inicia_el' => '2026-10-03']);

        // De dos paquetes que vencieron este mes, uno renovó
        $c = $this->persona('C');
        Membresias::asignar($c, $this->ocho, ['inicia_el' => '2026-09-05', 'vence_el' => '2026-10-04', 'pagado' => true]);
        Membresias::asignar($c, $this->ocho, ['inicia_el' => '2026-10-05', 'precio' => 900, 'pagado' => true]);
        $d = $this->persona('D');
        Membresias::asignar($d, $this->ocho, ['inicia_el' => '2026-09-08', 'vence_el' => '2026-10-07', 'pagado' => true]);

        $r = Embudo::resultados('mes');
        $this->assertSame(2, $r['pruebas']);
        $this->assertSame(50, $r['conversion']);
        $this->assertSame(1, $r['nuevos']);
        $this->assertSame(1000.0, $r['ingresoNuevos']);
        $this->assertSame(1, $r['renovaciones']);
        $this->assertSame(900.0, $r['ingresoRenov']);
        $this->assertSame(2, $r['tocaba']);
        $this->assertSame(50, $r['renovacion']);
    }
}
