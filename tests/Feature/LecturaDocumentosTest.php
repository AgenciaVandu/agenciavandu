<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cuenta;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Documentos\Constancia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Leer documentos: la Constancia de Situación Fiscal llena los datos fiscales; la lista de precios del proveedor actualiza costos */
class LecturaDocumentosTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tienda;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => null]);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Losos', 'giro' => 'comercio', 'dueno_nombre' => 'Luis', 'dueno_email' => 'luis@losos.mx']);
        Cuentas::activar($this->tienda);
    }

    private function conIa(): void
    {
        $a = $this->tienda->ajustes ?? [];
        $a['modulos'] = ['ia'];
        $this->tienda->update(['ajustes' => $a]);
        Cuentas::activar($this->tienda->fresh());
        config(['services.anthropic.key' => 'sk-prueba']);
    }

    private function constanciaPdf(string $cuerpo): UploadedFile
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<html><body style="font-family: DejaVu Sans; font-size: 11px">' . $cuerpo . '</body></html>')->output();
        return UploadedFile::fake()->createWithContent('constancia.pdf', $pdf);
    }

    public function test_el_pdf_del_sat_se_lee_sin_ia(): void
    {
        if (! Constancia::texto(\Barryvdh\DomPDF\Facade\Pdf::loadHTML('<p>x</p>')->output())) $this->markTestSkipped('Sin pdftotext en este equipo');
        $moral = $this->constanciaPdf('<h3>CONSTANCIA DE SITUACIÓN FISCAL</h3><p>MÉRIDA , YUCATÁN A 03 DE OCTUBRE DE 2026</p>
            <table><tr><td>RFC:</td><td>LOS200115AB3</td></tr><tr><td>Denominación/Razón Social:</td><td>LOSOS</td></tr>
            <tr><td>Régimen Capital:</td><td>SOCIEDAD ANONIMA DE CAPITAL VARIABLE</td></tr><tr><td>Nombre Comercial:</td><td>LOSOS ACABADOS</td></tr></table>
            <table><tr><td>Código Postal:97130</td><td>Tipo de Vialidad: CALLE</td></tr></table>
            <h4>Regímenes:</h4><table><tr><td>Régimen</td><td>Fecha Inicio</td><td>Fecha Fin</td></tr>
            <tr><td>Régimen General de Ley Personas Morales</td><td>15/01/2020</td><td></td></tr></table><h4>Obligaciones:</h4>');
        $this->actingAs($this->dueno)->post(route('admin.documentos.constancia'), ['archivo' => $moral], ['Accept' => 'application/json'])->assertOk()
            ->assertJson(['ok' => true, 'origen' => 'texto', 'datos' => ['rfc' => 'LOS200115AB3', 'razon_social' => 'LOSOS', 'nombre_comercial' => 'LOSOS ACABADOS',
                'regimen_fiscal' => '601', 'cp_fiscal' => '97130', 'emitida_el' => '2026-10-03', 'persona' => 'moral']]);

        // Persona física con un régimen que ya terminó y otro vigente
        $fisica = $this->constanciaPdf('<h3>CONSTANCIA DE SITUACIÓN FISCAL</h3><p>MÉRIDA , YUCATÁN A 1 DE SEPTIEMBRE DE 2026</p>
            <table><tr><td>RFC:</td><td>PECC850312KJ7</td></tr><tr><td>Nombre (s):</td><td>CARLOS</td></tr><tr><td>Primer Apellido:</td><td>PECH</td></tr><tr><td>Segundo Apellido:</td><td>CANUL</td></tr></table>
            <p>Código Postal:97000</p><h4>Regímenes:</h4><table>
            <tr><td>Régimen de Incorporación Fiscal</td><td>01/01/2015</td><td>31/12/2021</td></tr>
            <tr><td>Régimen Simplificado de Confianza</td><td>01/01/2022</td><td></td></tr></table>');
        $this->post(route('admin.documentos.constancia'), ['archivo' => $fisica], ['Accept' => 'application/json'])
            ->assertJson(['ok' => true, 'datos' => ['rfc' => 'PECC850312KJ7', 'razon_social' => 'CARLOS PECH CANUL', 'regimen_fiscal' => '626', 'regimenes' => ['621', '626'], 'persona' => 'fisica']]);

        // Un PDF cualquiera no es constancia
        $this->post(route('admin.documentos.constancia'), ['archivo' => $this->constanciaPdf('<p>Factura de luz</p>')], ['Accept' => 'application/json'])->assertJson(['ok' => false]);
        $this->get(route('admin.clientes.create'))->assertOk()->assertSee('data-leer-constancia', false);
        $this->get(route('admin.proveedores.create'))->assertOk()->assertSee('Llenar con su Constancia');
    }

    public function test_la_foto_de_la_constancia_la_lee_la_ia(): void
    {
        $foto = UploadedFile::fake()->image('constancia.jpg', 800, 1100);
        $this->actingAs($this->dueno)->post(route('admin.documentos.constancia'), ['archivo' => $foto], ['Accept' => 'application/json'])
            ->assertJson(['ok' => false, 'motivo' => 'sin_ia']);

        $this->conIa();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'registrar_constancia', 'input' => [
            'es_constancia' => true, 'rfc' => 'los200115ab3', 'razon_social' => 'Losos', 'regimen_fiscal' => '601', 'regimenes' => ['601'], 'cp_fiscal' => '97130', 'emitida_el' => '2026-10-03',
        ]]], 'usage' => ['input_tokens' => 2000, 'output_tokens' => 90]])]);
        $this->post(route('admin.documentos.constancia'), ['archivo' => UploadedFile::fake()->image('constancia.jpg', 800, 1100)], ['Accept' => 'application/json'])
            ->assertJson(['ok' => true, 'origen' => 'ia', 'datos' => ['rfc' => 'LOS200115AB3', 'razon_social' => 'LOSOS', 'regimen_fiscal' => '601']]);
        $this->assertSame('constancia', \DB::table('gasto_lecturas')->value('tipo'));
    }

    public function test_lista_de_precios_actualiza_costos_y_mantiene_el_margen(): void
    {
        $prov = Proveedor::create(['empresa' => 'Comex', 'nombre' => 'Ventas']);
        $p1 = Producto::create(['clave' => 'PV-100', 'nombre' => 'Pintura vinílica blanca 19 L', 'costo' => 1000, 'precio' => 1500, 'unidad' => 'cubeta']);
        $p2 = Producto::create(['nombre' => 'Rodillo profesional 9 pulgadas', 'costo' => 80, 'precio' => 120]);
        $p3 = Producto::create(['nombre' => 'Sellador acrílico 5x1 cubeta 19 litros', 'costo' => 900, 'precio' => 1300]);
        $p4 = Producto::create(['nombre' => 'Lija de agua 220', 'costo' => 8, 'precio' => 15]);

        $this->actingAs($this->dueno)->get(route('admin.productos.index'))->assertDontSee(route('admin.productos.lista'));
        $this->post(route('admin.productos.lista.leer'), ['archivo' => UploadedFile::fake()->create('lista.pdf', 200, 'application/pdf')])->assertNotFound();

        $this->conIa();
        $this->get(route('admin.productos.index'))->assertSee(route('admin.productos.lista'));
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'registrar_lista', 'input' => [
            'es_lista' => true, 'proveedor' => 'Comex', 'precios_con_iva' => true, 'filas' => [
                ['clave' => 'pv100', 'descripcion' => 'VINILICA BLANCA CUBETA', 'precio' => 1276, 'unidad' => 'cubeta'],
                ['clave' => null, 'descripcion' => 'Rodillo profesional 9 pulgadas', 'precio' => 104.4, 'unidad' => 'pza'],
                ['clave' => 'SA-5', 'descripcion' => 'Sellador acrilico 5x1 19 L', 'precio' => 986, 'unidad' => null],
                ['clave' => 'X-1', 'descripcion' => 'Brocha de pelo 4"', 'precio' => 58, 'unidad' => null],
                ['clave' => null, 'descripcion' => 'TOTAL', 'precio' => null, 'unidad' => null],
            ]]]], 'usage' => ['input_tokens' => 5000, 'output_tokens' => 400]])]);

        $this->post(route('admin.productos.lista.leer'), ['archivo' => UploadedFile::fake()->create('lista-comex.pdf', 200, 'application/pdf'), 'proveedor_id' => $prov->id])
            ->assertRedirect(route('admin.productos.lista.revisar'));
        $this->get(route('admin.productos.lista.revisar'))->assertOk()
            ->assertSee('Misma clave')->assertSee('Mismo nombre')->assertSee('Parecido · revísalo')->assertSee('Brocha de pelo')->assertSee('ya incluyen IVA');

        $this->post(route('admin.productos.lista.aplicar'), ['margen' => 1, 'filas' => [
            ['aplicar' => 1, 'producto_id' => $p1->id, 'costo' => '1100'],
            ['aplicar' => 1, 'producto_id' => $p2->id, 'costo' => '90'],
            ['producto_id' => $p3->id, 'costo' => '850'],   // parecido sin marcar: no se toca
        ]])->assertRedirect(route('admin.productos.index'))->assertSessionHas('ok', 'Actualizamos el costo de 2 productos (2 subieron, 0 bajaron) y su precio de venta con el mismo margen.');
        $this->assertSame([1100.0, 1650.0, $prov->id], [(float) $p1->fresh()->costo, (float) $p1->fresh()->precio, $p1->fresh()->proveedor_id]);
        $this->assertSame([90.0, 135.0], [(float) $p2->fresh()->costo, (float) $p2->fresh()->precio]);
        $this->assertSame(900.0, (float) $p3->fresh()->costo);
        $this->assertSame(8.0, (float) $p4->fresh()->costo);
        $this->get(route('admin.productos.lista.revisar'))->assertRedirect(route('admin.productos.lista'));
    }
}
