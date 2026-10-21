<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Clase;
use App\Models\ClaseTipo;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Reservacion;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Horarios\Datos;
use App\Support\Fitness\Horarios\Horarios;
use App\Support\Fitness\Horarios\Reglas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Horarios del gimnasio: mapa día × hora y hallazgos por reglas (llenas, vacías, faltas) con acciones */
class HorariosGimnasioTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $gym;
    private User $dueno;
    private ClaseTipo $engine;
    private ClaseTipo $fuerza;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'America/Merida'));   // miércoles
        [$this->gym, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura Gym', 'dueno_email' => 'laura@axion.mx']);
        Cuentas::activar($this->gym);
        $this->engine = ClaseTipo::create(['nombre' => 'HYROX Engine', 'duracion' => 60, 'cupo' => 10, 'color' => '#E8590C']);
        $this->fuerza = ClaseTipo::create(['nombre' => 'Fuerza', 'duracion' => 60, 'cupo' => 10, 'color' => '#2557D6']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Historia de 6 semanas: lunes 19:00 siempre lleno, viernes 06:00 casi vacío, los martes muchas faltas */
    private function historia(): void
    {
        $gente = collect(range(1, 10))->map(fn ($i) => Cliente::create(['nombre' => "Atleta $i"]));
        $tz = 'America/Merida';
        foreach (range(1, 6) as $w) {
            $lunes = now($tz)->startOfWeek()->subWeeks($w);
            foreach ([[$this->engine, 0, '19:00', 10, 0], [$this->fuerza, 4, '06:00', 1, 0], [$this->engine, 1, '07:00', 6, 3]] as [$tipo, $mas, $hora, $n, $faltan]) {
                $ini = Carbon::parse($lunes->copy()->addDays($mas)->toDateString() . ' ' . $hora, $tz);
                $c = Clase::create(['clase_tipo_id' => $tipo->id, 'inicia_at' => $ini->copy()->utc(), 'termina_at' => $ini->copy()->addHour()->utc(), 'cupo' => 10]);
                foreach ($gente->take($n) as $k => $a) {
                    Reservacion::create(['clase_id' => $c->id, 'cliente_id' => $a->id, 'estado' => $k < $faltan ? 'falta' : 'asistio', 'origen' => 'panel']);
                }
            }
        }
        // Una clase próxima del viernes 06:00 para la acción "Revisar ese horario"
        $vie = Carbon::parse('2026-10-23 06:00', $tz);
        Clase::create(['clase_tipo_id' => $this->fuerza->id, 'inicia_at' => $vie->copy()->utc(), 'termina_at' => $vie->copy()->addHour()->utc(), 'cupo' => 10]);
    }

    public function test_reglas_encuentran_llenas_vacias_y_faltas(): void
    {
        $this->historia();
        $d = Datos::cargar(8);
        $this->assertTrue($d->suficiente());
        $this->assertSame(100, $d->celdas[1][19]['ocupacion']);
        $this->assertSame(10, $d->celdas[5][6]['ocupacion']);

        $h = collect((new Reglas())->hallazgos($d))->keyBy('clave');
        $llena = $h["llena-{$this->engine->id}-1-19:00"];
        $this->assertSame('oportunidad', $llena->tono);
        $this->assertStringContainsString('6 de 6', $llena->titulo);
        $this->assertSame('alta', $llena->confianza);
        $this->assertStringContainsString('programar=20%3A00', $llena->accion['url']);
        $this->assertStringContainsString('fecha=2026-10-26', $llena->accion['url']);   // el próximo lunes

        $vacia = $h["vacia-{$this->fuerza->id}-5-06:00"];
        $this->assertSame('alerta', $vacia->tono);
        $this->assertStringContainsString('10%', $vacia->titulo);
        $this->assertStringContainsString('fecha=2026-10-23', $vacia->accion['url']);

        $this->assertTrue($h->has('faltas-2'));   // martes: 50% de faltas contra 0% el resto
        $this->assertLessThanOrEqual(Reglas::MAX, $h->count());
    }

    public function test_pantalla_y_programar_desde_un_hallazgo(): void
    {
        $this->actingAs($this->dueno)->get(route('admin.fitness-reportes.horarios'))->assertOk()->assertSee('Aún hay pocas clases para analizar');

        $this->historia();
        $this->get(route('admin.fitness-reportes.horarios'))->assertOk()
            ->assertSee('Ocupación por día y hora')->assertSee('Programar otra a las 20:00')->assertSee('Análisis automático')->assertDontSee('inteligencia artificial')->assertDontSee('con IA');

        // El enlace abre la agenda con la ventana de programar lista
        $this->get(route('admin.clases.index', ['fecha' => '2026-10-26', 'programar' => '20:00', 'tipo' => $this->engine->id]))->assertOk()
            ->assertViewHas('programar', ['fecha' => '2026-10-26', 'hora' => '20:00', 'tipo' => (string) $this->engine->id]);
        $this->assertSame('análisis automático', Horarios::analizador()->nombre());
    }

    public function test_el_entrenador_no_ve_horarios(): void
    {
        $coach = User::create(['name' => 'Coach', 'email' => 'coach@axion.mx', 'password' => 'x', 'activo' => true,
            'rol_id' => \App\Models\Rol::where('nombre', 'Entrenador')->value('id')]);
        $this->actingAs($coach)->get(route('admin.fitness-reportes.horarios'))->assertForbidden();
    }
}
