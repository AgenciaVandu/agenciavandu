<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vista general de proyectos: semáforo, lo urgente primero y números de arriba */
class ProyectosVistaTest extends TestCase
{
    use RefreshDatabase;

    private function proyecto(string $nombre): Proyecto
    {
        $c = Cliente::create(['nombre' => "Cliente $nombre", 'empresa' => "Empresa $nombre"]);
        $p = Presupuesto::nuevaPara($c)->fill(['estado' => 'aceptada']); $p->save();
        $p->conceptos()->create(['titulo' => $nombre, 'descripcion' => '', 'cantidad' => 1, 'precio' => 10000, 'orden' => 0]);
        $tipo = collect(config('vandu.proyectos'))->filter(fn ($m) => count($m['etapas']) >= 3)->keys()->first();
        $pr = Proyecto::desdePresupuesto($p->fresh('conceptos'), $tipo);
        $pr->update(['nombre' => $nombre]);
        return $pr->fresh(['etapas', 'pagos']);
    }

    public function test_semaforo_y_orden_por_urgencia(): void
    {
        $this->actingAs(User::factory()->create());
        $hoy = now(config('vandu.zona_horaria'))->startOfDay();

        $atrasado = $this->proyecto('Atrasado');
        $atrasado->pagos->each->update(['pagado_el' => $hoy]);
        $e = $atrasado->etapas->first(fn ($e) => ! $e->es_fecha);
        $e->update(['estado' => 'en_curso', 'fecha_inicio' => $hoy->copy()->subDays(10), 'fecha_fin' => $hoy->copy()->subDays(2)]);
        $atrasado->etapas->where('orden', '<', $e->orden)->each->update(['estado' => 'completada']);

        $tranquilo = $this->proyecto('Tranquilo');
        $tranquilo->pagos->each->update(['pagado_el' => $hoy]);
        $tranquilo->etapas->each(fn ($x, $i) => $x->update(['fecha_inicio' => $hoy->copy()->addDays(20 + $i), 'fecha_fin' => $hoy->copy()->addDays(21 + $i), 'es_fecha' => false]));

        $pago = $this->proyecto('Esperando pago');   // el anticipo sin pagar frena la primera etapa

        $this->assertSame('atrasado', $atrasado->fresh(['etapas', 'pagos'])->salud[0]);
        $this->assertSame('ok', $tranquilo->fresh(['etapas', 'pagos'])->salud[0]);
        $this->assertSame('hace 2 días', $atrasado->fresh('etapas')->etapa_actual->falta_texto);

        $html = $this->get(route('admin.proyectos.index'))->assertOk()
            ->assertSee('En marcha')->assertSee('Atrasado')->assertSee('En tiempo')->assertSee('Termina hace 2 días')
            ->assertSee('Recordar pago')->getContent();
        // Lo urgente primero
        $this->assertLessThan(strpos($html, '>Tranquilo<'), strpos($html, '>Atrasado<'));
        if ($pago->fresh(['etapas', 'pagos'])->salud[0] === 'pago') $this->assertLessThan(strpos($html, '>Tranquilo<'), strpos($html, '>Esperando pago<'));

        // La semana: lo atrasado aparece hoy y se puede navegar entre semanas
        $this->get(route('admin.proyectos.index'))->assertSee('La semana')->assertSee('Atrasado · hace 2 días');
        $this->get(route('admin.proyectos.index', ['semana' => 1]))->assertOk()->assertSee('La semana')->assertDontSee('Atrasado · hace 2 días');
        $this->get(route('admin.proyectos.index', ['vista' => 'lista']))->assertOk()->assertSee('Siguiente paso');
        $this->get(route('admin.proyectos.index', ['filtro' => 'todos']))->assertOk()->assertSee('Tranquilo');
        $this->get(route('admin.proyectos.index', ['filtro' => 'terminado']))->assertOk()->assertSee('No hay proyectos');
    }
}
