<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\Dropbox\Dropbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** La factura XML de una orden de compra comprueba el gasto y corrige el monto capturado si el proveedor cobró otro */
class FacturasOrdenesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Proveedor $bordados;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vandu.dropbox.simulado' => false, 'vandu.dropbox.app_key' => null, 'vandu.dropbox.app_secret' => null]);
        Dropbox::usar(null);
        Storage::fake('local');
        $this->admin = User::factory()->create(['name' => 'Alvar Buenfil']);
        $this->bordados = Proveedor::create(['empresa' => 'Bordados Mérida', 'nombre' => 'Carlos Pech', 'rubro' => 'Bordados', 'telefono' => '999 123 4567']);
    }

    private function orden(): OrdenCompra
    {
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel Xcanatún']);
        $pr = Proyecto::create(['cliente_id' => $c->id, 'nombre' => 'Uniformes del hotel', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 20000]);
        $this->actingAs($this->admin)->post('/admin/ordenes', [
            'proveedor_id' => $this->bordados->id, 'proyecto_id' => $pr->id, 'titulo' => 'Uniformes', 'fecha' => '2026-10-11', 'con_iva' => '1', 'iva_porcentaje' => 16,
            'conceptos' => [['descripcion' => 'Camisa bordada', 'cantidad' => 20, 'unidad' => 'pzas', 'precio' => 250], ['descripcion' => 'Playera bordada', 'cantidad' => 9, 'unidad' => 'pzas', 'precio' => 120]],
        ])->assertRedirect();
        $o = OrdenCompra::latest('id')->firstOrFail();
        $o->update(['estado' => 'entregada']);
        return $o;
    }

    private function xml(string $uuid = 'A1B2C3D4-0000-4000-8000-000000000001', string $sub = '6500.00', string $iva = '1040.00', string $total = '7540.00', string $rfc = 'BME150101AB1'): UploadedFile
    {
        $x = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-10-21T10:00:00" SubTotal="$sub" Total="$total" TipoDeComprobante="I" FormaPago="03" Moneda="MXN" Exportacion="01" LugarExpedicion="97000">
  <cfdi:Emisor Rfc="$rfc" Nombre="BORDADOS MERIDA SA DE CV" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="AVA200101AA1" Nombre="AGENCIA VANDU" UsoCFDI="G03" DomicilioFiscalReceptor="97000" RegimenFiscalReceptor="601"/>
  <cfdi:Conceptos><cfdi:Concepto ClaveProdServ="53101600" Cantidad="29" ClaveUnidad="H87" Descripcion="Uniformes bordados" ValorUnitario="1" Importe="$sub" ObjetoImp="02"/></cfdi:Conceptos>
  <cfdi:Impuestos TotalImpuestosTrasladados="$iva"><cfdi:Traslados><cfdi:Traslado Base="$sub" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="$iva"/></cfdi:Traslados></cfdi:Impuestos>
  <cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="$uuid" FechaTimbrado="2026-10-21T10:01:00" RfcProvCertif="SAT970701NN3" SelloCFD="x" NoCertificadoSAT="1" SelloSAT="x"/></cfdi:Complemento>
</cfdi:Comprobante>
XML;
        return UploadedFile::fake()->createWithContent('factura.xml', $x);
    }

    public function test_el_proveedor_sube_su_factura_y_corrige_el_monto(): void
    {
        $o = $this->orden();
        $this->assertSame([6080.0, 7052.8, false], [$o->subtotal, $o->total, $o->comprobada]);

        $portal = $this->bordados->fresh()->portal_url;
        $this->post("$portal/orden/{$o->id}/archivos", ['tipo' => 'factura', 'archivos' => [UploadedFile::fake()->create('factura.pdf', 40, 'application/pdf'), $this->xml()]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $o->refresh();
        $this->assertSame(['facturada', 6500.0, 1040.0, 7540.0, 487.2], [$o->estado, $o->factura_subtotal, $o->factura_iva, $o->factura_total, $o->diferencia]);
        $this->assertSame([6500.0, 7540.0], [$o->subtotal_final, $o->total_final]);
        $this->assertSame('BME150101AB1', $this->bordados->fresh()->rfc, 'El RFC se toma de su factura');
        $this->get("$portal/orden/{$o->id}")->assertSee('Facturado')->assertSee('factura por $7,540.00');

        // En el panel se ve la diferencia y los reportes usan lo facturado
        $this->actingAs($this->admin)->get(route('admin.ordenes.show', $o))->assertOk()->assertSee('Comprobado con la factura: $7,540.00')->assertSee('$487.20 más');
        $this->get(route('admin.finanzas', ['seccion' => 'proveedores', 'iva' => 'con']))->assertOk()->assertSee('$7,540');

        // La misma factura no entra dos veces: ni en otra orden ni como gasto
        $otra = $this->orden();
        $this->post(route('admin.ordenes.subir', $otra), ['tipo' => 'factura', 'archivos' => [$this->xml()]])->assertSessionHasErrors('archivos');
        $this->assertFalse($otra->fresh()->comprobada);

        // Si se quita el XML, vuelve a contar lo capturado
        $o->archivos()->whereNotNull('uuid')->first()->delete();
        $this->assertSame([false, 7052.8], [$o->fresh()->comprobada, $o->fresh()->total_final]);
    }

    public function test_yo_subo_la_factura_y_varias_facturas_se_suman(): void
    {
        $o = $this->orden();
        $this->actingAs($this->admin)->post(route('admin.ordenes.subir', $o), ['tipo' => 'factura', 'archivos' => [$this->xml('A1B2C3D4-0000-4000-8000-000000000002', '3000.00', '480.00', '3480.00')]])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'Factura leída: $3,480.00'));
        $this->post(route('admin.ordenes.subir', $o), ['tipo' => 'otro', 'archivos' => [$this->xml('A1B2C3D4-0000-4000-8000-000000000003', '3080.00', '492.80', '3572.80')]]);
        $o->refresh();
        $this->assertSame([7052.8, 0.0, 'facturada'], [$o->factura_total, $o->diferencia, $o->estado], 'Dos facturas que suman lo mismo que la orden');
        $this->assertSame(2, $o->archivos()->where('tipo', 'factura')->count(), 'Un XML de factura se guarda como factura aunque se eligiera otro tipo');
        $this->get(route('admin.ordenes.show', $o))->assertSee('Comprobado con 2 facturas')->assertSee('Coincide con lo que capturaste');

        // RFC distinto al del proveedor: aviso
        $this->bordados->update(['rfc' => 'XAXX010101000']);
        $this->get(route('admin.ordenes.show', $o))->assertSee('El RFC de la factura no es el de Bordados Mérida');
    }
}
