<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClienteRazon;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\Correos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Un cliente con varias empresas: se dan de alta en su ficha, cada cotización va a nombre de una y en su espacio elige cuál ver */
class RazonesSocialesTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $c;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X', 'email' => 'ana@hotel.mx', 'email_factura' => 'conta@hotel.mx', 'rfc' => 'HXX010101AB1']);
    }

    private function razon(array $extra = []): ClienteRazon
    {
        $this->post(route('admin.clientes.razones.store', $this->c), $extra + [
            'nombre' => 'Restaurante Y', 'razon_social' => 'Restaurante Y SA de CV', 'rfc' => 'ryy010101ab1', 'cp_fiscal' => '97000',
            'regimen_fiscal' => '601', 'uso_cfdi' => 'G03', 'email_factura' => 'facturas@resty.mx',
        ])->assertRedirect(route('admin.clientes.show', [$this->c, 'ver' => 'facturacion']))->assertSessionHas('ok');
        return $this->c->razones()->latest('id')->first();
    }

    private function cotizacion(?ClienteRazon $r, string $titulo): Presupuesto
    {
        $p = Presupuesto::nuevaPara($this->c)->fill(['estado' => 'enviada', 'razon_id' => $r?->id, 'cliente_empresa' => $r?->nombre ?? 'Hotel X']);
        $p->save();
        $p->conceptos()->create(['titulo' => $titulo, 'descripcion' => '', 'cantidad' => 1, 'precio' => 1000, 'orden' => 0]);
        return $p;
    }

    public function test_alta_edicion_y_baja_de_razones_sociales(): void
    {
        $r = $this->razon();
        $this->assertSame('RYY010101AB1', $r->rfc);
        $this->assertTrue($r->fiscales_completos);

        $this->get(route('admin.clientes.show', [$this->c, 'ver' => 'facturacion']))->assertOk()
            ->assertSee('Empresas y razones sociales')->assertSee('Restaurante Y SA de CV')->assertSee('Agregar otra razón social')
            ->assertSee(e(route('admin.presupuestos.create', ['cliente' => $this->c->id, 'razon' => $r->id])), false);

        $this->put(route('admin.clientes.razones.update', [$this->c, $r]), ['nombre' => 'Resto Y', 'rfc' => 'RYY010101AB1'])->assertSessionHas('ok');
        $this->assertSame('Resto Y', $r->fresh()->nombre);

        // Sin nombre no se guarda
        $this->post(route('admin.clientes.razones.store', $this->c), ['nombre' => ''])->assertSessionHasErrors('nombre');

        // Otra cliente no puede tocarla
        $otro = Cliente::create(['nombre' => 'Otro']);
        $this->put(route('admin.clientes.razones.update', [$otro, $r]), ['nombre' => 'X'])->assertNotFound();

        // Al quitarla, sus cotizaciones pasan a la empresa principal
        $p = $this->cotizacion($r, 'Menú');
        $this->delete(route('admin.clientes.razones.destroy', [$this->c, $r]))->assertSessionHas('ok', fn ($m) => str_contains($m, '1'));
        $this->assertNull($p->fresh()->razon_id);
        $this->assertSame(0, $this->c->razones()->count());
    }

    public function test_la_cotizacion_va_a_nombre_de_una_razon_y_usa_sus_datos(): void
    {
        $r = $this->razon();
        $this->get(route('admin.presupuestos.create', ['cliente' => $this->c->id, 'razon' => $r->id]))->assertOk()
            ->assertSee('¿A nombre de qué empresa?');

        $p = $this->cotizacion($r, 'Menú');
        $this->assertSame('Restaurante Y SA de CV', $p->datos_fiscales->razon_social);
        $this->get(route('admin.presupuestos.edit', $p))->assertOk()->assertSee('Restaurante Y SA de CV');

        // La factura y los recordatorios van al correo de facturación de esa empresa
        $this->assertSame('facturas@resty.mx', Correos::destinatario(['cliente' => $this->c, 'presupuesto' => $p], 'factura'));
        $this->assertSame('ana@hotel.mx', Correos::destinatario(['cliente' => $this->c, 'presupuesto' => $p], 'enviar'));
        $q = $this->cotizacion(null, 'Video');
        $this->assertSame('conta@hotel.mx', Correos::destinatario(['cliente' => $this->c, 'presupuesto' => $q], 'factura'));

        // No se puede usar la razón social de otro cliente
        $otro = Cliente::create(['nombre' => 'Otro']);
        $this->put(route('admin.presupuestos.update', $q), [
            'cliente_id' => $otro->id, 'razon_id' => $r->id, 'cliente_nombre' => 'Otro', 'fecha' => '2026-10-12', 'titulo' => 'Cotización',
            'emisor_nombre' => 'Alvar', 'modo_iva' => 'mas_iva', 'iva_porcentaje' => 16, 'vigente_hasta' => now()->addDays(10)->format('Y-m-d\\TH:i'),
            'estado' => 'enviada', 'conceptos' => [['titulo' => 'Video', 'cantidad' => 1, 'precio' => 1000]],
        ])->assertSessionHasErrors('razon_id');
    }

    public function test_en_su_espacio_el_cliente_elige_la_empresa(): void
    {
        $r = $this->razon();
        $hotel = $this->cotizacion(null, 'Video del hotel');
        $resto = $this->cotizacion($r, 'Fotos del menú');
        Proyecto::create(['cliente_id' => $this->c->id, 'presupuesto_id' => $resto->id, 'nombre' => 'Sesión menú', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 1160]);
        Proyecto::create(['cliente_id' => $this->c->id, 'presupuesto_id' => $hotel->id, 'nombre' => 'Video lobby', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 1160]);
        $url = $this->c->portal_url;
        auth()->logout();

        $this->get($url)->assertOk()->assertSee('¿De qué empresa quieres ver la información?')
            ->assertSee('Video del hotel')->assertSee('Fotos del menú')->assertSee('Sesión menú')->assertSee('Video lobby')
            ->assertSee('<span class="emp-tag">Restaurante Y</span>', false);

        $this->get($url . '?empresa=' . $r->id)->assertOk()->assertSee('Fotos del menú')->assertSee('Sesión menú')
            ->assertDontSee('Video del hotel')->assertDontSee('Video lobby')->assertSee('RFC RYY010101AB1');

        $this->get($url . '?empresa=principal')->assertOk()->assertSee('Video del hotel')->assertSee('Video lobby')
            ->assertDontSee('Fotos del menú')->assertDontSee('Sesión menú');

        // Una empresa que no es suya se ignora
        $this->get($url . '?empresa=999')->assertOk()->assertSee('Fotos del menú')->assertSee('Video del hotel');
    }

    public function test_sin_razones_extra_no_hay_selector(): void
    {
        $this->cotizacion(null, 'Video del hotel');
        $url = $this->c->portal_url;
        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Video del hotel')->assertDontSee('¿De qué empresa quieres ver');
    }
}
