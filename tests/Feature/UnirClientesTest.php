<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Correo;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** El mismo representante registrado dos veces (una por empresa) se une en un solo cliente */
class UnirClientesTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $a;
    private Cliente $b;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $this->a = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X', 'email' => 'ana@hotel.mx', 'rfc' => 'HXX010101AB1']);
        $this->b = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Restaurante Y', 'telefono' => '9991234567', 'rfc' => 'RYY010101AB1',
            'razon_social' => 'Restaurante Y SA de CV', 'email_factura' => 'facturas@resty.mx', 'notas' => 'Paga a 15 días']);
    }

    private function cotizacion(Cliente $c, string $titulo): Presupuesto
    {
        $p = Presupuesto::nuevaPara($c)->fill(['estado' => 'aceptada']);
        $p->save();
        $p->conceptos()->create(['titulo' => $titulo, 'descripcion' => '', 'cantidad' => 1, 'precio' => 1000, 'orden' => 0]);
        return $p;
    }

    public function test_une_al_duplicado_y_su_empresa_queda_como_razon_social(): void
    {
        $pa = $this->cotizacion($this->a, 'Video del hotel');
        $pb = $this->cotizacion($this->b, 'Fotos del menú');
        $pry = Proyecto::create(['cliente_id' => $this->b->id, 'presupuesto_id' => $pb->id, 'nombre' => 'Sesión menú', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 1160]);
        Correo::create(['cliente_id' => $this->b->id, 'plantilla' => 'libre', 'para' => 'x@y.mx', 'asunto' => 'Hola', 'cuerpo' => 'Hola', 'estado' => 'enviado']);
        $this->post(route('admin.clientes.constancias.store', $this->b), ['constancia' => UploadedFile::fake()->createWithContent('csf.pdf', '%PDF-1.4')]);
        $tokenB = $this->b->portal_url;

        // El duplicado aparece marcado como posible duplicado
        $this->get(route('admin.clientes.show', [$this->a, 'ver' => 'facturacion']))->assertOk()
            ->assertSee('Unir con otro cliente')->assertSee('★ Restaurante Y — Ana López');

        $this->post(route('admin.clientes.unir', $this->a), ['otro' => $this->b->id, 'como_razon' => 1])
            ->assertRedirect(route('admin.clientes.show', [$this->a, 'ver' => 'facturacion']))
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '1 cotización y 1 proyecto') && str_contains($m, 'Restaurante Y'));

        $this->assertModelMissing($this->b);
        $a = $this->a->fresh();
        $r = $a->razones()->sole();
        $this->assertSame(['Restaurante Y', 'Restaurante Y SA de CV', 'RYY010101AB1', 'facturas@resty.mx'], [$r->nombre, $r->razon_social, $r->rfc, $r->email_factura]);
        $this->assertSame([$a->id, $r->id], [$pb->fresh()->cliente_id, $pb->fresh()->razon_id]);
        $this->assertNull($pa->fresh()->razon_id);
        $this->assertSame($a->id, $pry->fresh()->cliente_id);
        $this->assertSame(1, Correo::where('cliente_id', $a->id)->count());

        // Sus datos fiscales no se mezclan con los de la empresa principal; el teléfono sí se completa
        $this->assertSame(['Hotel X', 'HXX010101AB1', '9991234567'], [$a->empresa, $a->rfc, $a->telefono]);
        $this->assertStringContainsString('Paga a 15 días', $a->notas);

        // La constancia se conserva con su archivo
        $c = $a->constancias()->sole();
        Storage::disk('local')->assertExists($c->ruta);
        $this->assertStringStartsWith("clientes/{$a->id}/", $c->ruta);

        // El enlace del duplicado se borra; el del cliente que queda muestra todo con selector
        auth()->logout();
        $this->get($a->portal_url)->assertOk()->assertSee('Video del hotel')->assertSee('Fotos del menú')->assertSee('Restaurante Y');
    }

    public function test_si_no_tenia_enlace_hereda_el_del_duplicado(): void
    {
        $url = $this->b->portal_url;
        $this->assertNull($this->a->fresh()->portal_token);
        $this->post(route('admin.clientes.unir', $this->a), ['otro' => $this->b->id])->assertSessionHas('ok');
        $this->assertSame(0, $this->a->razones()->count());
        // Sin razón social: los datos que le faltaban se completan
        $this->assertSame('facturas@resty.mx', $this->a->fresh()->email_factura);
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Hola, Ana');
    }

    public function test_no_se_une_consigo_mismo(): void
    {
        $this->post(route('admin.clientes.unir', $this->a), ['otro' => $this->a->id])->assertStatus(422);
        $this->post(route('admin.clientes.unir', $this->a), [])->assertSessionHasErrors('otro');
        $this->assertModelExists($this->a);
    }
}
