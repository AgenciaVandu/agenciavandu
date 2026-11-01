<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaFactura;
use App\Support\Cuentas;
use App\Support\Facturas\Timbrado;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Facturación multiempresa: cada negocio con su organización en la cuenta de Facturapi de Nerby, su sello y su logo */
class FacturacionNerbyTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tienda;
    private User $dueno;
    private string $estado = 'valid';
    private ?string $cancelacion = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Storage::fake('local');
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', 'America/Merida'));
        config(['services.facturapi.user_key' => 'sk_user_nerby123']);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar($this->tienda->refresh());
        $this->actingAs($this->dueno);

        $xml = '<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Total="290" TipoDeComprobante="I"><cfdi:Receptor Rfc="HSO010101AB1"/><cfdi:Complemento><tfd:TimbreFiscalDigital UUID="11111111-2222-4333-8444-555555555555"/></cfdi:Complemento></cfdi:Comprobante>';
        Http::fake(function (HttpRequest $r) use ($xml) {
            $u = $r->url(); $m = $r->method();
            return match (true) {
                $m === 'POST' && str_ends_with($u, '/v2/organizations') => Http::response(['id' => 'org_lupe']),
                $m === 'PUT' && str_ends_with($u, '/organizations/org_lupe/legal') => Http::response(['id' => 'org_lupe']),
                str_ends_with($u, '/organizations/org_lupe/logo'), str_ends_with($u, '/organizations/org_lupe/customization') => Http::response(['id' => 'org_lupe']),
                $m === 'GET' && str_ends_with($u, '/organizations/org_lupe/apikeys/test') => Http::response('"sk_test_lupe"', 200, ['Content-Type' => 'application/json']),
                $m === 'PUT' && str_ends_with($u, '/organizations/org_lupe/certificate') => Http::response(['id' => 'org_lupe', 'legal' => ['tax_id' => 'LUPE800101AB1'],
                    'certificate' => ['has_certificate' => true, 'expires_at' => '2030-05-01T00:00:00.000Z', 'serial_number' => '30001000000500003416']]),
                $m === 'PUT' && str_ends_with($u, '/organizations/org_lupe/apikeys/live') => Http::response('"sk_live_lupe"', 200, ['Content-Type' => 'application/json']),
                $m === 'POST' && str_ends_with($u, '/v2/invoices') => Http::response(['id' => 'inv_1', 'uuid' => '11111111-2222-4333-8444-555555555555', 'series' => 'A', 'folio_number' => 7]),
                str_ends_with($u, '/invoices/inv_1/pdf') => Http::response('%PDF-1.4 timbrada'),
                str_ends_with($u, '/invoices/inv_1/xml') => Http::response($xml),
                $m === 'DELETE' && str_contains($u, '/invoices/inv_1') => Http::response(['id' => 'inv_1', 'status' => $this->estado, 'cancellation_status' => $this->cancelacion]),
                $m === 'GET' && str_ends_with($u, '/invoices/inv_1') => Http::response(['id' => 'inv_1', 'status' => $this->estado, 'cancellation_status' => $this->cancelacion]),
                default => Http::response(['message' => 'no esperado ' . $m . ' ' . $u], 500),
            };
        });
    }

    protected function tearDown(): void
    {
        \App\Support\Licencias\Estado::olvidar();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venta(): Venta
    {
        $hotel = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel Sol', 'email_factura' => 'conta@hotel.mx',
            'rfc' => 'HSO010101AB1', 'razon_social' => 'Hotel Sol', 'regimen_fiscal' => '601', 'cp_fiscal' => '97000']);
        $p = Producto::create(['nombre' => 'Paleta de mango', 'precio' => 25, 'unidad' => 'pieza']);
        Inventario::mover($p, 'entrada', 50, 'Inicial');
        $this->post(route('admin.pedidos.store'), ['cliente_id' => $hotel->id, 'con_iva' => 1, 'productos' => [['producto_id' => $p->id, 'cantidad' => 10, 'precio' => 25]],
            'pago' => 'contado', 'forma' => 'efectivo', 'entrega' => 'ahora'])->assertSessionHasNoErrors();
        return Venta::latest('id')->firstOrFail();
    }

    public function test_alta_del_negocio_sello_timbrado_y_cancelacion(): void
    {
        // Sin pasos hechos: pide datos fiscales y no ofrece pegar una clave
        $this->get(route('admin.facturacion'))->assertOk()->assertSee('Tus datos fiscales')->assertSee('Primero guarda tus datos fiscales')
            ->assertDontSee('Clave secreta')->assertDontSee('e.firma o CSD');

        // Paso 1: datos fiscales → organización y clave de pruebas
        $this->post(route('admin.facturacion.emisor'), ['razon_social' => 'Guadalupe Canché', 'regimen' => '626', 'cp' => '97 000', 'direccion' => 'Calle 21 #104'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'facturas de prueba'));
        $a = Timbrado::ajustes();
        $this->assertSame(['org_lupe', 'sk_test_lupe', true, '97000'], [$a['org_id'], $a['test_key'], $a['pruebas'], $a['emisor']['cp']]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/organizations/org_lupe/legal') && $r['legal_name'] === 'GUADALUPE CANCHÉ' && $r['tax_system'] === '626'
            && $r['address']['zip'] === '97000' && ! isset($r['address']['country']) && $r->hasHeader('Authorization', 'Bearer sk_user_nerby123'));
        $this->assertTrue(Timbrado::activo());
        $this->assertTrue(Timbrado::enPruebas());

        // Paso 2: sello. Se valida y se manda a Facturapi sin guardarlo
        $this->post(route('admin.facturacion.sello'), ['cer' => UploadedFile::fake()->createWithContent('sello.txt', 'x'), 'key' => UploadedFile::fake()->createWithContent('llave.key', 'y'), 'password' => 'secreta'])
            ->assertSessionHasErrors(['cer', 'acepto']);
        $this->post(route('admin.facturacion.sello'), [
            'cer' => UploadedFile::fake()->createWithContent('00001000000500003416.cer', 'CERTIFICADO'), 'key' => UploadedFile::fake()->createWithContent('CSD_Lupe.key', 'LLAVE'),
            'password' => 'MiClave123', 'acepto' => 1,
        ])->assertSessionHas('ok', fn ($m) => str_contains($m, 'vence el 1 de mayo de 2030'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/organizations/org_lupe/certificate') && $r->isMultipart()
            && collect($r->data())->pluck('name')->sort()->values()->all() === ['cer', 'key', 'password']);
        $a = Timbrado::ajustes();
        $this->assertSame(['sk_live_lupe', false, 'LUPE800101AB1', '2030-05-01'], [$a['live_key'], $a['pruebas'], $a['certificado']['rfc'], $a['certificado']['vence']]);
        $this->assertSame([], Storage::disk('local')->allFiles());   // ni el .cer ni el .key quedan en disco
        $guardado = (string) DB::table('integraciones')->value('datos');
        foreach (['MiClave123', 'sk_live_lupe', 'sk_test_lupe'] as $secreto) $this->assertStringNotContainsString($secreto, $guardado);
        $this->get(route('admin.facturacion'))->assertSee('Lista para facturar')->assertSee('LUPE800101AB1')->assertDontSee('MiClave123')->assertDontSee('sk_live_lupe');

        // Sin timbres no deja timbrar; con timbres sí, y gasta uno
        $v = $this->venta();
        $this->post(route('admin.facturas.timbrar', ['venta', $v->id]), ['forma_pago' => '01', 'metodo_pago' => 'PUE'])->assertSessionHas('error', fn ($m) => str_contains($m, 'no te quedan timbres'));
        \App\Support\Facturas\Timbres::cargar($this->tienda, 'regalo', 3);
        $this->post(route('admin.facturas.timbrar', ['venta', $v->id]), ['forma_pago' => '01', 'metodo_pago' => 'PUE'])->assertSessionHas('ok');
        $this->assertSame(2, \App\Support\Facturas\Timbres::disponibles($this->tienda));
        $this->assertSame(['A', 1], [VentaFactura::value('serie'), VentaFactura::value('folio')]);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v2/invoices') && $r->hasHeader('Authorization', 'Bearer sk_live_lupe'));
        $this->assertSame(1, Timbrado::timbradasEsteMes($this->tienda));

        // Cancelación que el cliente debe aceptar → pendiente; al revisar, ya cancelada → vuelve a Por facturar
        $this->estado = 'valid'; $this->cancelacion = 'pending';
        $this->get(route('admin.facturas', ['ver' => 'por_enviar']))->assertSee('Cancelar factura');
        $this->post(route('admin.facturas.cancelar', ['venta', $v->id]), ['motivo' => '02'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'tiene que aceptarla'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/invoices/inv_1?motive=02'));
        $this->get(route('admin.facturas', ['ver' => 'por_enviar']))->assertSee('Cancelación esperando al cliente');
        $this->estado = 'canceled'; $this->cancelacion = 'accepted';
        $this->post(route('admin.facturas.revisar', ['venta', $v->id]))->assertSessionHas('ok', fn ($m) => str_contains($m, 'ya quedó cancelada'));
        $this->assertSame(['cancelada', 'cancelada'], VentaFactura::pluck('tipo')->all());
        $this->get(route('admin.facturas', ['ver' => 'por_facturar']))->assertSee($v->folio);
        $this->assertSame(1, Timbrado::timbradasEsteMes($this->tienda));   // el timbre ya se usó

        // Modo de pruebas otra vez
        $this->put(route('admin.facturacion.modo'), ['pruebas' => 1])->assertSessionHas('ok');
        $this->assertSame('sk_test_lupe', Timbrado::clave());
    }

    public function test_sin_cuenta_de_nerby_sigue_la_clave_propia(): void
    {
        config(['services.facturapi.user_key' => null]);
        $this->get(route('admin.facturacion'))->assertOk()->assertSee('Clave secreta')->assertDontSee('Tus datos fiscales')->assertDontSee('e.firma o CSD');
        $this->post(route('admin.facturacion.emisor'), ['razon_social' => 'X', 'regimen' => '626', 'cp' => '97000'])->assertNotFound();
    }
}
