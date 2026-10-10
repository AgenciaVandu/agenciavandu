<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un solo enlace por cliente con todo lo suyo, y galerías que cargan de 10 en 10 */
class PortalClienteTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel Xcanatún', 'telefono' => '9991234567']);
    }

    private function cotizacion(Cliente $c, string $concepto, string $estado = 'enviada'): Presupuesto
    {
        $p = Presupuesto::nuevaPara($c);
        $p->estado = $estado;
        $p->save();
        $p->conceptos()->create(['descripcion' => $concepto, 'cantidad' => 1, 'precio' => 10000, 'orden' => 0]);
        return $p;
    }

    private function proyecto(Presupuesto $p, string $nombre, int $fotos = 0): Proyecto
    {
        $pr = Proyecto::create(['cliente_id' => $p->cliente_id, 'presupuesto_id' => $p->id, 'nombre' => $nombre, 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 11600]);
        $pr->etapas()->create(['clave' => 'levantamiento', 'nombre' => 'Grabación', 'orden' => 0, 'estado' => 'completada', 'es_fecha' => true]);
        $pr->etapas()->create(['clave' => 'entrega', 'nombre' => 'Entrega', 'orden' => 1, 'estado' => 'en_curso', 'es_fecha' => true]);
        for ($i = 1; $i <= $fotos; $i++) {
            $pr->archivos()->create(['grupo' => 'galeria', 'nombre' => "foto-$i.jpg", 'ruta' => "x/foto-$i.jpg", 'mime' => 'image/jpeg', 'miniatura' => "x/m-$i.jpg", 'visible' => true, 'orden' => $i]);
        }
        return $pr;
    }

    public function test_el_portal_muestra_todo_lo_del_cliente_y_nada_mas(): void
    {
        $video = $this->proyecto($this->cotizacion($this->c, 'Video corporativo', 'aceptada'), 'Video corporativo', 6);
        $this->proyecto($this->cotizacion($this->c, 'Sesión de fotos', 'aceptada'), 'Sesión de habitaciones');
        $pendiente = $this->cotizacion($this->c, 'Sitio web nuevo');
        $this->cotizacion($this->c, 'Borrador secreto', 'borrador');
        $otro = Cliente::create(['nombre' => 'Luis', 'empresa' => 'Otro hotel']);
        $this->proyecto($this->cotizacion($otro, 'Algo de otro', 'aceptada'), 'Proyecto de otro');

        $html = $this->get($this->c->portal_url)->assertOk()->getContent();
        foreach (['Hola, Ana', 'Video corporativo', 'Sesión de habitaciones', 'Sitio web nuevo', 'Tienes una cotización por responder', $pendiente->url_publica, $video->url_publica, $video->url_entrega, 'Ver entrega · 6'] as $t) {
            $this->assertStringContainsString($t, $html, "Falta: $t");
        }
        $this->assertStringNotContainsString('Borrador secreto', $html);
        $this->assertStringNotContainsString('Proyecto de otro', $html);
        $this->assertNotNull($this->c->fresh()->portal_visto_at);
        $this->get('/cliente/' . str_repeat('a', 32))->assertNotFound();

        // Desde el proyecto y la cotización se regresa al espacio
        $this->get($video->url_publica)->assertOk()->assertSee('Todos mis proyectos')->assertSee($this->c->portal_url);
        $this->get($pendiente->url_publica)->assertOk()->assertSee('Ver todos mis proyectos y cotizaciones');
    }

    public function test_la_galeria_carga_10_y_luego_ver_mas(): void
    {
        $pr = $this->proyecto($this->cotizacion($this->c, 'Video', 'aceptada'), 'Sesión grande', 23);
        foreach ([$pr->url_entrega, $pr->url_publica] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertSame(10, preg_match_all('#<img src="[^"]*v=miniatura#', $html), $url); // solo se piden 10 miniaturas
            $this->assertSame(13, preg_match_all('#<img data-src="[^"]*v=miniatura#', $html), $url);
            $this->assertStringContainsString('Ver más <span class="num">(13)</span>', $html);
        }
    }

    public function test_en_el_panel_esta_el_enlace_del_espacio(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get("/admin/clientes/{$this->c->id}")->assertOk()->assertSee('Espacio del cliente')->assertSee($this->c->fresh()->portal_url);
    }
}
