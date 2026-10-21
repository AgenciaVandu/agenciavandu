<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Clase;
use App\Models\ClaseSerie;
use App\Models\ClaseTipo;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Paquete;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Calendario;
use App\Support\Fitness\Membresias;
use App\Support\Fitness\Reservas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Agenda del gimnasio como calendario: acomodo por horas, programar desde la agenda, panel de la clase y horario fijo */
class AgendaCalendarioTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $gym;
    private User $dueno;
    private ClaseTipo $engine;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', 'America/Merida'));   // martes
        [$this->gym, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura Gym', 'dueno_email' => 'laura@axion.mx']);
        Cuentas::activar($this->gym);
        $this->engine = ClaseTipo::create(['nombre' => 'HYROX Engine', 'duracion' => 60, 'cupo' => 12, 'color' => '#E8590C']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function clase(string $cuando, int $min = 60): Clase
    {
        $i = Carbon::parse($cuando, 'America/Merida');
        return Clase::create(['clase_tipo_id' => $this->engine->id, 'inicia_at' => $i->copy()->utc(), 'termina_at' => $i->copy()->addMinutes($min)->utc(), 'cupo' => 10]);
    }

    public function test_acomoda_por_hora_y_pone_lado_a_lado_las_que_se_enciman(): void
    {
        $a = $this->clase('2026-10-21 07:00');
        $b = $this->clase('2026-10-21 07:30');
        $c = $this->clase('2026-10-21 09:00', 45);
        $bloques = Calendario::acomodar(collect([$a, $b, $c]), 6)->keyBy(fn ($x) => $x['clase']->id);

        $this->assertSame(Calendario::PX_HORA, $bloques[$a->id]['top']);               // 7:00 = una hora abajo de las 6
        $this->assertSame([0, 2], [$bloques[$a->id]['carril'], $bloques[$a->id]['carriles']]);
        $this->assertSame([1, 2], [$bloques[$b->id]['carril'], $bloques[$b->id]['carriles']]);
        $this->assertSame([0, 1], [$bloques[$c->id]['carril'], $bloques[$c->id]['carriles']]);
        $this->assertSame([6, 21], Calendario::horas(collect([$a])));
        $this->assertSame([5, 22], Calendario::horas(collect([$this->clase('2026-10-22 05:30'), $this->clase('2026-10-22 21:00')])));
    }

    public function test_programar_desde_la_agenda_y_abrir_la_clase_en_el_panel(): void
    {
        $this->actingAs($this->dueno);
        $r = $this->post(route('admin.clases.store'), ['volver' => 'agenda', 'clase_tipo_id' => $this->engine->id, 'fecha' => '2026-10-29', 'hora' => '18:30', 'duracion' => 60, 'cupo' => 14, 'repetir' => 0]);
        $clase = Clase::latest('id')->first();
        $r->assertRedirect(route('admin.clases.index', ['fecha' => '2026-10-29', 'clase' => $clase->id]));

        $paquete = Paquete::create(['nombre' => '8 clases', 'precio' => 1000, 'creditos' => 8, 'vigencia_dias' => 30]);
        $sofia = Cliente::create(['nombre' => 'Sofía Medina']);
        Membresias::asignar($sofia, $paquete, ['pagado' => true]);
        $sinClases = Cliente::create(['nombre' => 'Diego Sin Clases']);
        Membresias::asignar($sinClases, $paquete, ['pagado' => true])->forceFill(['usados' => 8])->save();

        $this->get(route('admin.clases.index', ['fecha' => '2026-10-29', 'clase' => $clase->id]))->assertOk()
            ->assertSee('id="ag-clase-t"', false)->assertSee('29 de octubre')
            ->assertViewHas('abierta', fn ($a) => $a['atletas']->pluck('nombre')->all() === ['Sofía Medina']);   // sin clases no aparece

        // Horario fijo desde la agenda: regresa a la agenda de esa semana
        $this->post(route('admin.clases.store'), ['volver' => 'agenda', 'clase_tipo_id' => $this->engine->id, 'fecha' => '2026-10-21', 'hora' => '06:00', 'duracion' => 60, 'cupo' => 16, 'repetir' => 1, 'dias' => [1, 3]])
            ->assertRedirect(route('admin.clases.index', ['fecha' => '2026-10-21']));
        $this->assertSame(1, ClaseSerie::count());
    }

    public function test_el_entrenador_no_abre_clases_de_otro(): void
    {
        $clase = $this->clase('2026-10-21 07:00');
        $coach = User::create(['name' => 'Coach Beto', 'email' => 'beto@axion.mx', 'password' => 'x', 'activo' => true,
            'rol_id' => \App\Models\Rol::where('nombre', 'Entrenador')->value('id')]);
        $this->actingAs($coach)->get(route('admin.clases.index', ['clase' => $clase->id]))->assertOk()->assertDontSee('id="ag-clase-t"', false);
        $clase->update(['coach_id' => $coach->id]);
        $this->actingAs($coach)->get(route('admin.clases.index', ['clase' => $clase->id]))->assertOk()->assertSee('id="ag-clase-t"', false);
    }

    public function test_cambiar_horario_fijo_mueve_las_clases_con_inscritos_y_regenera_las_vacias(): void
    {
        $this->actingAs($this->dueno);
        $this->post(route('admin.clases.store'), ['clase_tipo_id' => $this->engine->id, 'fecha' => '2026-10-20', 'hora' => '19:00', 'duracion' => 60, 'cupo' => 10, 'repetir' => 1, 'dias' => [1, 3, 5]]);
        $serie = ClaseSerie::firstOrFail();

        $paquete = Paquete::create(['nombre' => 'Ilimitada', 'precio' => 1500, 'creditos' => null, 'vigencia_dias' => 30]);
        $ana = Cliente::create(['nombre' => 'Ana']);
        Membresias::asignar($ana, $paquete, ['pagado' => true]);
        $miercoles = $serie->clases()->orderBy('inicia_at')->first();   // mié 21 a las 19:00
        Reservas::reservar($miercoles, $ana, 'panel');
        $viernes = $serie->clases()->orderBy('inicia_at')->skip(1)->first();
        $antes = $serie->clases()->count();

        // Pasa a las 20:00, solo lunes y miércoles
        $this->from(route('admin.clases.index', ['clase' => $miercoles->id]))
            ->put(route('admin.clases.series.update', $serie), ['dias' => [1, 3], 'hora' => '20:00', 'duracion' => 60, 'cupo' => 10])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '1 clase con inscritos se movió'));

        $miercoles->refresh();
        $this->assertSame('20:00', $miercoles->inicio->format('H:i'));
        $this->assertTrue($miercoles->reservaciones()->where('cliente_id', $ana->id)->exists());
        $this->assertNull(Clase::find($viernes->id));
        $this->assertTrue($serie->clases()->get()->every(fn ($c) => $c->inicio->format('H:i') === '20:00' && in_array($c->inicio->dayOfWeekIso, [1, 3], true)));
        $this->assertLessThan($antes, $serie->clases()->count());
    }
}
