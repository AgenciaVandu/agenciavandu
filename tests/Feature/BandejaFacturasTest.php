<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Presupuesto;
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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bandeja de facturas: por facturar, por enviar y enviadas; el cliente la pide desde su enlace; timbrado con Facturapi */
class BandejaFacturasTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $tienda;
    private User $dueno;
    private Producto $paleta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', 'America/Merida'));
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        Cuentas::activar($this->tienda->refresh());
        $this->paleta = Producto::create(['nombre' => 'Paleta de mango', 'precio' => 25, 'costo' => 9, 'unidad' => 'pieza']);
        Inventario::mover($this->paleta, 'entrada', 500, 'Inicial');
        $this->actingAs($this->dueno);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vender(int $cantidad, array $extra = []): Venta
    {
        $this->post(route('admin.pedidos.store'), $extra + [
            'productos' => [['producto_id' => $this->paleta->id, 'cantidad' => $cantidad, 'precio' => 25]], 'pago' => 'contado', 'forma' => 'transferencia', 'entrega' => 'ahora',
        ])->assertSessionHasNoErrors();
        return Venta::latest('id')->firstOrFail();
    }

    private function xml(float $total, string $rfc, string $uuid = 'A1B2C3D4-0000-4000-8000-000000000001'): UploadedFile
    {
        $x = '<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Total="' . $total . '" SubTotal="' . $total . '" TipoDeComprobante="I" Fecha="2026-10-26T10:00:00">'
            . '<cfdi:Emisor Rfc="LUPE800101AAA" Nombre="PALETAS LUPE"/><cfdi:Receptor Rfc="' . $rfc . '" Nombre="CLIENTE"/>'
            . '<cfdi:Conceptos><cfdi:Concepto ClaveProdServ="50202306" Descripcion="Paletas" Importe="' . $total . '"/></cfdi:Conceptos>'
            . '<cfdi:Complemento><tfd:TimbreFiscalDigital UUID="' . $uuid . '"/></cfdi:Complemento></cfdi:Comprobante>';
        return UploadedFile::fake()->createWithContent('F-1.xml', $x);
    }

    private function pdf(): UploadedFile { return UploadedFile::fake()->createWithContent('F-1.pdf', '%PDF-1.4 factura'); }

    public function test_bandeja_subir_enviar_y_enviadas(): void
    {
        Mail::fake();
        $hotel = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel Sol', 'email' => 'ana@hotel.mx', 'email_factura' => 'conta@hotel.mx', 'telefono' => '9991234567',
            'rfc' => 'HSO010101AB1', 'razon_social' => 'HOTEL SOL', 'regimen_fiscal' => '601', 'cp_fiscal' => '97000']);
        $conRfc = $this->vender(10, ['cliente_id' => $hotel->id]);       // $250, cliente con RFC
        $mostrador = $this->vender(2);                                      // al mostrador: no se le factura

        // Por facturar: solo la del cliente con RFC
        $this->get(route('admin.facturas'));   // (se lleva el aviso de la venta)
        $this->get(route('admin.facturas'))->assertOk()->assertSee('Hotel Sol')->assertSee('– Paleta de mango', false)->assertSee($conRfc->folio)->assertDontSee($mostrador->folio)->assertSee('Subir PDF y XML');

        // Se sube el PDF y un XML con otro total: avisa la diferencia
        $this->post(route('admin.facturas.subir', ['venta', $conRfc->id]), ['facturas' => [$this->pdf(), $this->xml(300, 'HSO010101AB1')]])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'El XML dice $300.00'));
        $this->assertSame('A1B2C3D4-0000-4000-8000-000000000001', VentaFactura::whereNotNull('uuid')->value('uuid'));

        // Mismo nombre reemplaza; ahora con el total correcto
        $this->post(route('admin.facturas.subir', ['venta', $conRfc->id]), ['facturas' => [$this->xml(250, 'HSO010101AB1')]])->assertSessionHas('ok', fn ($m) => str_contains($m, 'Ya la puedes enviar'));
        $this->assertSame(2, VentaFactura::count());

        // Por enviar, con su correo de facturación y WhatsApp
        $this->get(route('admin.facturas', ['ver' => 'por_enviar']))->assertOk()->assertSee('Enviar a conta@hotel.mx')->assertSee('WhatsApp')->assertSee('Enviar <span', false);
        $this->get(route('admin.pedidos.show', $conRfc))->assertOk()->assertSee('F-1.pdf')->assertSee('Enviar a conta@hotel.mx');

        // Envío de varias: llega con los dos archivos y queda en Enviadas
        $this->post(route('admin.facturas.enviar-varias'), ['docs' => ['venta-' . $conRfc->id]])->assertSessionHas('ok', '1 factura enviada.');
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('conta@hotel.mx') && count($m->attachments()) === 2);
        $this->assertTrue(VentaFactura::whereNull('enviada_at')->doesntExist());
        $this->get(route('admin.facturas', ['ver' => 'enviadas']))->assertOk()->assertSee('Hotel Sol')->assertSee('Reenviar');
        $this->get(route('admin.facturas', ['ver' => 'por_facturar']))->assertDontSee($conRfc->folio);

        // El cliente la descarga desde su enlace
        auth()->logout();
        $pdf = VentaFactura::where('nombre', 'F-1.pdf')->sole();
        $this->get(route('pedido.publico', $conRfc->token))->assertOk()->assertSee('Tu factura')->assertSee('Descargar PDF')->assertDontSee('¿Necesitas factura?');
        $this->get(route('pedido.publico.factura', [$conRfc->token, $pdf->id]))->assertOk();
        // No con el enlace de otro pedido
        $this->get(route('pedido.publico.factura', [$mostrador->token, $pdf->id]))->assertNotFound();
    }

    public function test_el_cliente_pide_factura_desde_su_pedido(): void
    {
        $v = $this->vender(4);   // al mostrador, sin cliente
        auth()->logout();
        $this->get(route('pedido.publico', $v->token))->assertOk()->assertSee('¿Necesitas factura?');

        // Valida RFC y CP
        $this->post(route('pedido.publico.pedir-factura', $v->token), ['rfc' => 'XYZ', 'razon_social' => 'Juan', 'regimen_fiscal' => '612', 'cp_fiscal' => '970', 'uso_cfdi' => 'G03', 'email_factura' => 'juan@correo.mx'])
            ->assertSessionHasErrors(['rfc', 'cp_fiscal']);

        $this->post(route('pedido.publico.pedir-factura', $v->token), ['rfc' => 'pepj-800101-ab1', 'razon_social' => 'Juan Pérez', 'regimen_fiscal' => '612', 'cp_fiscal' => '97000', 'uso_cfdi' => 'G03', 'email_factura' => 'Juan@Correo.mx'])
            ->assertRedirect(route('pedido.publico', $v->token))->assertSessionHas('ok');
        $v->refresh();
        $this->assertNotNull($v->factura_solicitada_at);
        $this->assertSame(['PEPJ800101AB1', 'JUAN PÉREZ', 'juan@correo.mx'], [$v->cliente->rfc, $v->cliente->razon_social, $v->cliente->email_factura]);
        $this->get(route('pedido.publico', $v->token))->assertSee('Ya pediste tu factura');

        // Aparece primero en la bandeja como pedida por el cliente, y cuenta en el menú
        $this->actingAs($this->dueno);
        $this->get(route('admin.facturas', ['ver' => 'por_facturar']))->assertOk()->assertSee('La pidió el cliente')->assertSee('PEPJ800101AB1');
        $this->get(route('admin.resumen'))->assertOk()->assertSee('Facturas');

        // Si el cliente ya tenía otro RFC, se guarda como otra razón social
        auth()->logout();
        $this->post(route('pedido.publico.pedir-factura', $v->token), ['rfc' => 'EMP010101AB1', 'razon_social' => 'Empresa Juan', 'regimen_fiscal' => '601', 'cp_fiscal' => '97100', 'uso_cfdi' => 'G03', 'email_factura' => 'conta@empresa.mx'])->assertSessionHas('ok');
        $v->refresh();
        $this->assertSame(['PEPJ800101AB1', 'EMP010101AB1'], [$v->cliente->rfc, $v->razon->rfc]);
        $this->assertSame('conta@empresa.mx', (new \App\Support\Facturas\Documento($v->load('razon')))->correo());
    }

    public function test_conectar_facturapi_y_timbrar(): void
    {
        Mail::fake();
        Http::fake([
            'www.facturapi.io/v2/invoices?limit=1' => Http::response(['data' => []]),
            'www.facturapi.io/v2/invoices' => Http::response(['id' => 'inv_123', 'uuid' => '11111111-2222-4333-8444-555555555555', 'total' => 290]),
            'www.facturapi.io/v2/invoices/inv_123/pdf' => Http::response('%PDF-1.4 timbrada'),
            'www.facturapi.io/v2/invoices/inv_123/xml' => Http::response('<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Total="290" TipoDeComprobante="I"><cfdi:Receptor Rfc="HSO010101AB1"/><cfdi:Complemento><tfd:TimbreFiscalDigital UUID="11111111-2222-4333-8444-555555555555"/></cfdi:Complemento></cfdi:Comprobante>'),
        ]);
        $hotel = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel Sol', 'email_factura' => 'conta@hotel.mx',
            'rfc' => 'HSO010101AB1', 'razon_social' => 'Hotel Sol', 'regimen_fiscal' => '601', 'cp_fiscal' => '97000']);
        $v = $this->vender(10, ['cliente_id' => $hotel->id, 'con_iva' => 1]);

        // Sin conectar no hay botón de generar
        $this->get(route('admin.facturas'))->assertDontSee('Timbrar factura')->assertSee('Generar facturas aquí');
        $this->get(route('admin.configuracion'))->assertSee('Facturación');

        // Conectar: la clave se valida, se prueba y nunca se vuelve a mostrar
        $this->put(route('admin.facturacion.guardar'), ['key' => 'mi-clave', 'clave_prod' => '01010101', 'clave_unidad' => 'H87', 'clave_servicio' => 'E48'])->assertSessionHasErrors('key');
        $this->put(route('admin.facturacion.guardar'), ['key' => 'sk_test_abc123', 'clave_prod' => '50202306', 'clave_unidad' => 'H87', 'clave_servicio' => 'E48', 'serie' => 'A'])
            ->assertSessionHas('ok', 'La conexión con Facturapi funciona.');
        $this->assertTrue(Timbrado::activo());
        $this->get(route('admin.facturacion'))->assertOk()->assertSee('Conectado')->assertSee('modo prueba')->assertDontSee('sk_test_abc123');
        $this->assertStringNotContainsString('sk_test_abc123', (string) \DB::table('integraciones')->value('datos'));

        // Timbrar y enviar
        $this->get(route('admin.facturas'))->assertSee('Timbrar factura');
        $this->post(route('admin.facturas.timbrar', ['venta', $v->id]), ['forma_pago' => '03', 'metodo_pago' => 'PUE', 'uso_cfdi' => 'G03', 'enviar' => 1])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'generada'));
        Http::assertSent(function (HttpRequest $r) {
            if ($r->method() !== 'POST') return false;
            $d = $r->data();
            return $d['customer']['tax_id'] === 'HSO010101AB1' && $d['customer']['legal_name'] === 'HOTEL SOL' && $d['customer']['address']['zip'] === '97000'
                && $d['payment_form'] === '03' && $d['payment_method'] === 'PUE' && $d['series'] === 'A'
                && $d['items'][0]['product']['product_key'] === '50202306' && $d['items'][0]['product']['taxes'][0]['rate'] == 0.16;
        });
        $this->assertSame(['nerby', 'nerby'], VentaFactura::pluck('origen')->all());
        $this->assertSame('11111111-2222-4333-8444-555555555555', VentaFactura::whereNotNull('uuid')->value('uuid'));
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('conta@hotel.mx'));
        $this->assertTrue(VentaFactura::whereNull('enviada_at')->doesntExist());

        // No se timbra dos veces
        $this->post(route('admin.facturas.timbrar', ['venta', $v->id]), ['forma_pago' => '03', 'metodo_pago' => 'PUE'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Ya tiene factura'));
    }

    public function test_cotizacion_aceptada_se_puede_pedir_factura(): void
    {
        [$agencia, $duena] = PlataformaController::crearCuenta(['nombre' => 'Estudio', 'giro' => 'agencia', 'dueno_nombre' => 'Eva', 'dueno_email' => 'eva@estudio.mx']);
        Cuentas::activar($agencia->refresh());
        $this->actingAs($duena);
        $c = Cliente::create(['nombre' => 'Luis', 'empresa' => 'Café Luis']);
        $p = Presupuesto::nuevaPara($c)->fill(['estado' => 'aceptada']);
        $p->save();
        $p->conceptos()->create(['titulo' => 'Logo', 'descripcion' => '', 'cantidad' => 1, 'precio' => 5000, 'orden' => 0]);

        $this->get(route('admin.facturas', ['ver' => 'por_facturar']))->assertOk()->assertSee('Café Luis')->assertSee('Faltan datos fiscales');
        auth()->logout();
        $this->get($p->url_publica)->assertOk()->assertSee('¿Necesitas factura?');
        $this->post(route('presupuesto.pedir-factura', $p->token), ['rfc' => 'CLU010101AB1', 'razon_social' => 'Café Luis', 'regimen_fiscal' => '601', 'cp_fiscal' => '97000', 'uso_cfdi' => 'G03', 'email_factura' => 'luis@cafe.mx'])
            ->assertSessionHas('respuesta_ok');
        $this->assertNotNull($p->fresh()->factura_solicitada_at);
        $this->assertSame('CLU010101AB1', $c->fresh()->rfc);

        $this->actingAs($duena);
        $this->get(route('admin.facturas', ['ver' => 'por_facturar']))->assertSee('La pidió el cliente')->assertDontSee('Faltan datos fiscales');
    }
}
