<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** "Cobrado en <mes>" debe dar lo mismo en el Resumen y en Finanzas */
class CobradoMesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel X']);
        $p = Presupuesto::nuevaPara($c);
        $p->estado = 'aceptada';
        $p->save();
        $p->conceptos()->create(['descripcion' => 'Video', 'cantidad' => 1, 'precio' => 10000, 'orden' => 0]); // 11,600 con IVA
        $pr = Proyecto::create(['presupuesto_id' => $p->id, 'cliente_id' => $c->id, 'nombre' => 'Video', 'tipo' => 'audiovisual', 'estado' => 'activo']);
        $pr->pagos()->create(['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'monto' => 5800, 'orden' => 0, 'pagado_el' => now(config('vandu.zona_horaria'))->toDateString()]);
    }

    private function cobrado(string $html): string
    {
        preg_match('/Cobrado en [^<]+<\/div>\s*<div class="em-v num">([^<]+)</', $html, $m);
        return $m[1] ?? 'no encontrado';
    }

    public function test_resumen_y_finanzas_coinciden(): void
    {
        $resumen = $this->cobrado($this->get('/admin')->getContent());
        $finanzas = $this->cobrado($this->get('/admin/finanzas')->getContent());
        $this->assertSame($resumen, $finanzas, "Resumen dice $resumen y Finanzas dice $finanzas");
    }

    public function test_la_eleccion_de_iva_en_finanzas_se_aplica_al_resumen(): void
    {
        $this->assertSame('$5,000', $this->cobrado($this->get('/admin')->getContent())); // sin elegir: antes de IVA, igual que Finanzas

        $r = $this->get('/admin/finanzas?iva=con');
        $this->assertSame('$5,800', $this->cobrado($r->getContent()));
        $cookie = collect($r->headers->getCookies())->first(fn ($c) => $c->getName() === 'vandu_iva');
        $this->assertNotNull($cookie);

        $this->withCookie('vandu_iva', 'con');
        $html = $this->get('/admin')->getContent();
        $this->assertSame('$5,800', $this->cobrado($html));
        $this->assertStringContainsString('($5,000 sin IVA)', $html);
        $this->assertSame('$5,800', $this->cobrado($this->get('/admin/finanzas')->getContent()));
        $this->assertSame('$5,000', $this->cobrado($this->get('/admin/finanzas?iva=sin')->getContent()));
    }
}
