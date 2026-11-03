<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\FacturaGlobal;
use App\Models\FacturaSerie;
use App\Models\Licencia;
use App\Models\LicenciaComprobante;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\TimbrePaquete;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaFactura;
use App\Support\Cuentas;
use App\Support\Facturas\Timbrado;
use App\Support\Facturas\Timbres;
use App\Support\Licencias\Estado;
use App\Support\Ventas\Registrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Versión "Solo facturación": factura directa, series, complementos de pago, notas de crédito, global, reportes y timbres */
class FacturacionCompletaTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $despacho;
    private User $dueno;
    private array $enviados = [];
    private int $n = 0;
    private ?string $rechazo = null;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Storage::fake('local');
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', 'America/Merida'));
        config(['services.facturapi.user_key' => 'sk_user_nerby']);
        [$this->despacho, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Despacho Uc', 'giro' => 'facturacion', 'dueno_nombre' => 'Rosa Uc', 'dueno_email' => 'rosa@despacho.mx']);
        Cuentas::activar($this->despacho->refresh());
        Timbrado::guardarAjustes(['org_id' => 'org_1', 'live_key' => 'sk_live_1', 'test_key' => 'sk_test_1', 'pruebas' => false,
            'certificado' => ['tiene' => true, 'vence' => '2030-01-01', 'rfc' => 'UCRR800101AB1'], 'emisor' => ['razon_social' => 'ROSA UC', 'regimen' => '612', 'cp' => '97000']]);
        Timbres::cargar($this->despacho, 'regalo', 20);
        $this->actingAs($this->dueno);

        Http::fake(function (HttpRequest $r) {
            $u = $r->url(); $m = $r->method();
            if ($m === 'POST' && str_ends_with($u, '/v2/invoices')) {
                if ($this->rechazo) return Http::response(['message' => $this->rechazo], 400);
                $this->enviados[] = $r->data();
                $this->n++;
                return Http::response(['id' => 'inv_' . $this->n, 'uuid' => sprintf('00000000-0000-4000-8000-%012d', $this->n)]);
            }
            if (preg_match('#/invoices/inv_(\d+)/(pdf|xml)$#', $u, $x)) {
                return $x[2] === 'pdf' ? Http::response('%PDF-1.4 ' . $x[1]) : Http::response('<?xml version="1.0"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" TipoDeComprobante="P"/>');
            }
            if ($m === 'DELETE') return Http::response(['status' => 'canceled', 'cancellation_status' => 'accepted']);
            if (str_contains($u, '/catalogs/products')) return Http::response(['data' => [['key' => '84111500', 'description' => 'Servicios contables'], ['key' => '84111502', 'description' => 'Servicios de contabilidad financiera']]]);
            if (str_contains($u, '/catalogs/units')) return Http::response(['data' => [['key' => 'E48', 'description' => 'Unidad de servicio']]]);
            return Http::response(['message' => "no esperado $m $u"], 500);
        });
    }

    protected function tearDown(): void
    {
        \App\Support\Licencias\Estado::olvidar();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cliente(): Cliente
    {
        return Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel Sol', 'email_factura' => 'conta@hotel.mx', 'rfc' => 'HSO010101AB1', 'razon_social' => 'Hotel Sol',
            'regimen_fiscal' => '601', 'cp_fiscal' => '97000', 'uso_cfdi' => 'G03', 'dias_credito' => 15]);
    }

    private function nueva(Cliente $c, array $extra = [])
    {
        return $this->post(route('admin.facturas.guardar'), $extra + [
            'cliente_id' => $c->id, 'uso_cfdi' => 'G03', 'metodo_pago' => 'PPD', 'con_iva' => 1,
            'conceptos' => [['descripcion' => 'Asesoría contable de octubre', 'clave_sat' => '84111500', 'unidad_sat' => 'E48', 'cantidad' => 1, 'precio' => 1000, 'guardar' => 1]],
        ]);
    }

    public function test_solo_facturacion_factura_directa_complementos_y_nota_de_credito(): void
    {
        // El negocio que solo factura entra directo a Facturas y no ve ventas, cotizaciones ni proyectos
        $this->get(route('admin.resumen'))->assertRedirect(route('admin.facturas'));
        $this->get(route('admin.facturas'))->assertOk()->assertSee('Nueva factura')->assertSee('Factura global')->assertSee('Reportes')
            ->assertDontSee(route('admin.presupuestos.index'))->assertDontSee(route('admin.proyectos.index'));
        $c = $this->cliente();
        $this->get(route('admin.facturas.crear', ['cliente' => $c->id]))->assertOk()->assertSee('Timbrar factura')->assertSee('Hotel Sol');

        // Factura PPD: concepto nuevo al catálogo con su clave SAT
        $this->nueva($c)->assertSessionHas('ok', fn ($m) => str_contains($m, 'Factura A-1 timbrada'));
        $v = Venta::latest('id')->first();
        $i = $this->enviados[0];
        $this->assertSame(['PPD', '99', 'G03', 'A', 1, '84111500', 'E48', 0.16], [$i['payment_method'], $i['payment_form'], $i['use'], $i['series'], $i['folio_number'],
            $i['items'][0]['product']['product_key'], $i['items'][0]['product']['unit_key'], $i['items'][0]['product']['taxes'][0]['rate']]);
        $this->assertSame(['PPD', 1160.0, 1160.0], [$v->metodo_pago, (float) $v->total, (float) $v->saldo]);
        $p = Producto::where('nombre', 'Asesoría contable de octubre')->first();
        $this->assertSame(['84111500', 'E48', false], [$p->clave_sat, $p->unidad_sat, $p->controla_existencia]);
        $this->assertSame(19, Timbres::disponibles($this->despacho));
        $this->get(route('admin.facturas.venta', $v))->assertOk()->assertSee('A-1')->assertSee('Registrar pago')->assertSee('Notas de crédito');

        // Primer pago con su complemento (parcialidad 1, saldo anterior = total)
        $this->post(route('admin.facturas.venta.pago', $v), ['monto' => 500, 'forma' => 'transferencia', 'fecha' => '2026-10-26', 'complemento' => 1])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'Complemento de pago timbrado'));
        $pg = $this->enviados[1];
        $rel = $pg['complements'][0]['data'][0]['related_documents'][0];
        $this->assertSame(['P', 'CP', '03', '00000000-0000-4000-8000-000000000001', 500.0, 1, 1160.0, 431.03],
            [$pg['type'], $pg['series'], $pg['complements'][0]['data'][0]['payment_form'], $rel['uuid'], (float) $rel['amount'], $rel['installment'], (float) $rel['last_balance'], (float) $rel['taxes'][0]['base']]);
        // Segundo pago: parcialidad 2 con el saldo que quedaba
        $this->post(route('admin.facturas.venta.pago', $v), ['monto' => 660, 'forma' => 'efectivo', 'fecha' => '2026-10-26', 'complemento' => 1])->assertSessionHas('ok');
        $rel = $this->enviados[2]['complements'][0]['data'][0]['related_documents'][0];
        $this->assertSame([2, 660.0, 660.0, '01'], [$rel['installment'], (float) $rel['last_balance'], (float) $rel['amount'], $this->enviados[2]['complements'][0]['data'][0]['payment_form']]);
        $this->assertSame(2, VentaFactura::where('tipo', 'complemento')->distinct('facturapi_id')->count('facturapi_id'));
        $this->assertSame(['CP', 2], [VentaFactura::where('facturapi_id', 'inv_3')->value('serie'), (int) VentaFactura::where('facturapi_id', 'inv_3')->value('folio')]);

        // Nota de crédito relacionada; no puede pasar del total
        $this->post(route('admin.facturas.venta.nota', $v), ['monto' => 5000, 'motivo' => 'Descuento', 'forma_pago' => '03'])->assertSessionHas('error', fn ($m) => str_contains($m, 'no puede ser mayor'));
        $this->post(route('admin.facturas.venta.nota', $v), ['monto' => 116, 'motivo' => 'Descuento por pronto pago', 'forma_pago' => '03'])->assertSessionHas('ok');
        $nc = $this->enviados[3];
        $this->assertSame(['E', 'NC', 'G02', '01', ['00000000-0000-4000-8000-000000000001'], 100.0],
            [$nc['type'], $nc['series'], $nc['use'], $nc['related_documents'][0]['relationship'], $nc['related_documents'][0]['documents'], round((float) $nc['items'][0]['product']['price'], 2)]);
        $this->assertSame(16, Timbres::disponibles($this->despacho));

        // Enviar el complemento por correo
        $comp = VentaFactura::where('tipo', 'complemento')->first();
        $this->post(route('admin.facturas.venta.enviar-cfdi', [$v, $comp->id]))->assertSessionHas('ok', fn ($m) => str_contains($m, 'conta@hotel.mx'));

        // La factura no se cancela mientras tenga complementos vigentes
        $this->post(route('admin.facturas.cancelar', ['venta', $v->id]), ['motivo' => '02'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Primero cancela sus complementos'));
        foreach (VentaFactura::where('tipo', 'complemento')->get()->unique('facturapi_id') as $f) {
            $this->post(route('admin.facturas.venta.cancelar-cfdi', [$v, $f->id]), ['motivo' => '02'])->assertSessionHas('ok');
        }
        $this->post(route('admin.facturas.cancelar', ['venta', $v->id]), ['motivo' => '02'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'cancelada'));
        $this->get(route('admin.facturas.venta', $v))->assertOk()->assertSee('Facturas canceladas');
    }

    public function test_series_propias_y_sin_timbres(): void
    {
        $c = $this->cliente();
        $this->post(route('admin.facturacion.series.store'), ['serie' => 'b', 'tipo' => 'I', 'descripcion' => 'Servicios', 'siguiente' => 100])->assertSessionHas('ok');
        $b = FacturaSerie::where('serie', 'B')->first();
        $this->post(route('admin.facturacion.series.store'), ['serie' => 'B', 'tipo' => 'I'])->assertSessionHasErrors('serie');
        $this->nueva($c, ['serie_id' => $b->id, 'metodo_pago' => 'PUE', 'forma_pago' => '04'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'B-100'));
        $this->assertSame(['B', 100, 'PUE', '04'], [$this->enviados[0]['series'], $this->enviados[0]['folio_number'], $this->enviados[0]['payment_method'], $this->enviados[0]['payment_form']]);
        $this->assertSame(101, $b->fresh()->siguiente);
        $this->assertEquals(0, Venta::latest('id')->first()->saldo);   // PUE: ya pagada
        $this->get(route('admin.facturacion'))->assertOk()->assertSee('Series y folios')->assertSee('Servicios');

        // Se acaban los timbres: no crea la venta ni llama a Facturapi
        \App\Models\TimbreMovimiento::where('cuenta_id', $this->despacho->id)->delete();
        $antes = Venta::count();
        $this->nueva($c)->assertSessionHas('error', fn ($m) => str_contains($m, 'no te quedan timbres'));
        $this->assertSame([$antes, 1], [Venta::count(), count($this->enviados)]);

        // Facturapi la rechaza: tampoco se queda la venta y el folio no avanza
        Timbres::cargar($this->despacho, 'regalo', 5);
        $this->rechazo = 'El RFC del receptor no existe en la lista del SAT';
        $this->nueva($c, ['serie_id' => $b->id])->assertSessionHas('error', fn ($m) => str_contains($m, 'El RFC del receptor'));
        $this->assertSame([$antes, 101, 5], [Venta::count(), $b->fresh()->siguiente, Timbres::disponibles($this->despacho)]);
    }

    public function test_factura_global_y_reportes(): void
    {
        // Dos ventas de mostrador cobradas y una de un cliente con RFC (esa no entra)
        $vender = fn (array $x) => Registrar::crear($x + ['productos' => [['descripcion' => 'Consulta', 'cantidad' => 1, 'precio' => 500]], 'con_iva' => true, 'pago' => 'contado', 'forma' => 'efectivo', 'entrega' => 'ahora'], $this->dueno);
        $v1 = $vender([]); $v2 = $vender([]);
        $vender(['cliente_id' => $this->cliente()->id]);
        $this->get(route('admin.facturas.global', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))->assertOk()->assertSee($v1->folio)->assertSee('$1,160.00');

        $this->post(route('admin.facturas.global.emitir'), ['desde' => '2026-10-01', 'hasta' => '2026-10-31', 'periodicidad' => 'month'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'G-1') && str_contains($m, '2 ventas'));
        $g = $this->enviados[0];
        $this->assertSame(['XAXX010101000', '616', 'S01', 'month', '10', 2026, 2, $v1->folio, '01', 'PUE'],
            [$g['customer']['tax_id'], $g['customer']['tax_system'], $g['use'], $g['global']['periodicity'], $g['global']['months'], $g['global']['year'], count($g['items']), $g['items'][0]['product']['sku'], $g['payment_form'], $g['payment_method']]);
        $glob = FacturaGlobal::first();
        $this->assertSame([2, 1160.0], [Venta::where('global_id', $glob->id)->count(), $glob->total]);
        $this->assertTrue(\App\Support\Facturas\Globales::candidatas('2026-10-01', '2026-10-31')->isEmpty());
        $this->get(route('admin.facturas.global.archivo', [$glob, 'pdf']))->assertOk();

        // Reportes del mes
        $this->get(route('admin.facturas.reportes', ['mes' => '2026-10']))->assertOk()->assertSee('$1,160.00')->assertSee('Factura global');
        $this->get(route('admin.facturas.reportes.excel', ['mes' => '2026-10']))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $zip = $this->get(route('admin.facturas.reportes.zip', ['mes' => '2026-10']))->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'z'); file_put_contents($tmp, $zip->getContent());
        $z = new \ZipArchive(); $z->open($tmp);
        $this->assertNotFalse($z->locateName('Globales/G-1.xml'));
        $z->close(); @unlink($tmp);

        // Al cancelarla, las ventas quedan libres otra vez
        $this->post(route('admin.facturas.global.cancelar', $glob), ['motivo' => '02'])->assertSessionHas('ok');
        $this->assertSame(2, \App\Support\Facturas\Globales::candidatas('2026-10-01', '2026-10-31')->count());
    }

    public function test_paquetes_de_timbres_compra_y_confirmacion(): void
    {
        $alvar = User::factory()->create(['plataforma' => true]);
        Cuentas::activar(Cuentas::principalId());
        $this->actingAs($alvar)->post(route('admin.plataforma.timbres.paquetes.store'), ['nombre' => 'Paquete 100', 'timbres' => 100, 'precio' => 450])->assertSessionHas('ok');
        $paq = TimbrePaquete::first();
        $plan = Plan::where('tipo', 'plan')->first();
        $this->get(route('admin.plataforma.timbres'))->assertOk()->assertSee('Paquete 100')->assertSee('$4.50');
        // Plan con 10 timbres al mes y licencia para el despacho
        $plan->update(['timbres_mes' => 10]);
        $this->put(route('admin.plataforma.licencia.update', $this->despacho), ['tipo' => 'suscripcion', 'plan_id' => $plan->id, 'periodo' => 'mensual',
            'inicio' => '2026-10-01', 'dias_gracia' => 5, 'dias_lectura' => 10])->assertSessionHasNoErrors();
        $this->post(route('admin.plataforma.licencia.pagar', $this->despacho), ['fecha' => '2026-10-01', 'concepto' => 'periodo', 'monto' => 500, 'periodos' => 1, 'forma' => 'transferencia'])->assertSessionHas('ok');
        Estado::olvidar();

        // El negocio ve sus timbres y pide un paquete con su comprobante
        Cuentas::activar($this->despacho->refresh());
        \App\Models\TimbreMovimiento::where('cuenta_id', $this->despacho->id)->delete();
        $this->actingAs($this->dueno)->get(route('admin.facturacion'))->assertOk()->assertSee('Tus timbres')->assertSee('Tu plan incluye 10 al mes')->assertSee('Paquete 100');
        $this->assertSame(10, Timbres::disponibles($this->despacho));
        $this->post(route('admin.facturacion.comprar'), ['paquete_id' => $paq->id, 'fecha' => '2026-10-26', 'archivo' => UploadedFile::fake()->image('spei.jpg')])->assertSessionHas('ok');
        $comp = LicenciaComprobante::where('paquete_id', $paq->id)->firstOrFail();
        $this->assertEquals(450, $comp->monto);

        // Plataforma confirma: se registra el pago y se cargan los 100
        Cuentas::activar(Cuentas::principalId());
        $this->actingAs($alvar)->get(route('admin.plataforma.licencia', $this->despacho))->assertOk()->assertSee('Paquete 100')->assertSee('Al confirmar se cargan solos');
        $this->post(route('admin.plataforma.licencia.confirmar', [$this->despacho, $comp]), ['concepto' => 'timbres', 'monto' => 450, 'forma' => 'transferencia'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, '100 timbres'));
        $this->assertSame(110, Timbres::disponibles($this->despacho));
        $this->assertSame('timbres', \App\Models\LicenciaPago::latest('id')->value('concepto'));
        // Regalo y ajuste a mano
        $this->post(route('admin.plataforma.licencia.timbres', $this->despacho), ['tipo' => 'regalo', 'cantidad' => 5])->assertSessionHas('ok');
        $this->post(route('admin.plataforma.licencia.timbres', $this->despacho), ['tipo' => 'ajuste', 'cantidad' => -3])->assertSessionHas('ok');
        $this->assertSame(112, Timbres::disponibles($this->despacho));

        // Se gastan primero los del plan
        Cuentas::activar($this->despacho->refresh());
        $this->actingAs($this->dueno);
        Timbres::gastar('prueba');
        $this->assertSame(['plan_restantes' => 9, 'comprados' => 102], array_intersect_key(Timbres::resumen($this->despacho), ['plan_restantes' => 1, 'comprados' => 1]));
    }

    public function test_plataforma_crea_paquetes_y_los_asigna(): void
    {
        // Solo la plataforma entra
        $this->actingAs($this->dueno)->get(route('admin.plataforma.timbres'))->assertForbidden();
        $alvar = User::factory()->create(['plataforma' => true]);
        Cuentas::activar(Cuentas::principalId());
        $this->actingAs($alvar)->get(route('admin.plataforma.timbres'))->assertOk()->assertSee('Nuevo paquete')->assertSee($this->despacho->nombre);
        // Crear, editar
        $this->post(route('admin.plataforma.timbres.paquetes.store'), ['nombre' => 'Paquete 50', 'timbres' => 50])->assertSessionHasErrorsIn('nuevo', 'precio');
        $this->post(route('admin.plataforma.timbres.paquetes.store'), ['nombre' => 'Paquete 50', 'timbres' => 50, 'precio' => 300])->assertSessionHas('ok');
        $paq = TimbrePaquete::firstOrFail();
        $this->put(route('admin.plataforma.timbres.paquetes.update', $paq), ['nombre' => 'Paquete 50', 'timbres' => 50, 'precio' => 250, 'activo' => 1])->assertSessionHas('ok');
        $this->assertEquals(5, $paq->fresh()->por_timbre);

        // Asignar cobrado (con licencia: queda el pago) y de regalo
        $plan = Plan::where('tipo', 'plan')->first();
        $this->put(route('admin.plataforma.licencia.update', $this->despacho), ['tipo' => 'suscripcion', 'plan_id' => $plan->id, 'periodo' => 'mensual',
            'inicio' => '2026-10-01', 'dias_gracia' => 5, 'dias_lectura' => 10])->assertSessionHasNoErrors();
        \App\Models\TimbreMovimiento::where('cuenta_id', $this->despacho->id)->delete();
        Estado::olvidar();
        $antes = Timbres::disponibles($this->despacho);
        $this->post(route('admin.plataforma.timbres.asignar'), ['cuenta_id' => $this->despacho->id, 'paquete_id' => $paq->id, 'como' => 'cobrado', 'forma' => 'efectivo'])
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'pago quedó registrado'));
        $this->assertEquals(250, \App\Models\LicenciaPago::latest('id')->value('monto'));
        $this->post(route('admin.plataforma.timbres.asignar'), ['cuenta_id' => $this->despacho->id, 'paquete_id' => $paq->id, 'como' => 'regalo'])
            ->assertSessionHas('ok', fn ($m) => str_starts_with($m, 'Regalaste'));
        $this->assertSame($antes + 100, Timbres::disponibles($this->despacho));
        $this->assertSame(1, \App\Models\LicenciaPago::where('concepto', 'timbres')->count());
        $this->get(route('admin.plataforma.timbres'))->assertOk()->assertSee('Últimas asignaciones')->assertSee('Regalo · Paquete 50');
        // A la cuenta de la plataforma no se le asigna
        $this->post(route('admin.plataforma.timbres.asignar'), ['cuenta_id' => Cuentas::principalId(), 'paquete_id' => $paq->id, 'como' => 'regalo'])->assertSessionHasErrors('cuenta_id');

        // Ya asignado: eliminar lo oculta
        $this->delete(route('admin.plataforma.timbres.paquetes.destroy', $paq))->assertSessionHas('ok', fn ($m) => str_contains($m, 'ocultamos'));
        $this->assertFalse($paq->fresh()->activo);
        $otro = TimbrePaquete::create(['nombre' => 'Sin usar', 'timbres' => 10, 'precio' => 60, 'activo' => true]);
        $this->delete(route('admin.plataforma.timbres.paquetes.destroy', $otro));
        $this->assertNull($otro->fresh());
    }

    public function test_catalogo_sat_y_objeto_de_impuesto(): void
    {
        // Busca en el catálogo del SAT mientras se escribe
        $this->getJson(route('admin.sat.buscar', 'productos') . '?q=contable')->assertOk()->assertJson([['clave' => '84111500', 'texto' => 'Servicios contables']]);
        $this->getJson(route('admin.sat.buscar', 'unidades') . '?q=servicio')->assertOk()->assertJson([['clave' => 'E48']]);
        $this->getJson(route('admin.sat.buscar', 'otra') . '?q=x')->assertNotFound();
        // Sin conexión con Facturapi: las más usadas
        $this->assertSame('H87', \App\Support\Facturas\CatalogoSat::buscar('unidades', 'p')[0]['clave'] ?? null);

        // El producto guarda su información SAT
        $this->post(route('admin.productos.store'), ['nombre' => 'Asesoría fiscal', 'precio' => 800, 'clave_sat' => '84111500', 'clave_sat_texto' => 'Servicios contables',
            'unidad_sat' => 'e48', 'unidad_sat_texto' => 'Unidad de servicio', 'objeto_imp' => '02'])->assertSessionHasNoErrors();
        $p = Producto::where('nombre', 'Asesoría fiscal')->first();
        $this->assertSame(['84111500', 'Servicios contables', 'E48', '02'], [$p->clave_sat, $p->clave_sat_texto, $p->unidad_sat, $p->objeto_imp]);
        $this->get(route('admin.productos.edit', $p))->assertOk()->assertSee('Información SAT')->assertSee('Objeto de impuesto');
        $this->post(route('admin.productos.store'), ['nombre' => 'Mal', 'precio' => 1, 'clave_sat' => '123'])->assertSessionHasErrors('clave_sat');

        // En la factura: del catálogo toma sus claves; un concepto "no objeto" va sin IVA
        $c = $this->cliente();
        $this->nueva($c, ['conceptos' => [
            ['producto_id' => $p->id, 'descripcion' => 'Asesoría fiscal', 'cantidad' => 1, 'precio' => 800, 'objeto_imp' => '02'],
            ['descripcion' => 'Viáticos', 'clave_sat' => '01010101', 'unidad_sat' => 'ACT', 'cantidad' => 1, 'precio' => 200, 'objeto_imp' => '01'],
        ]])->assertSessionHas('ok');
        [$a, $b] = $this->enviados[0]['items'];
        $this->assertSame(['84111500', 'E48', '02', 0.16], [$a['product']['product_key'], $a['product']['unit_key'], $a['product']['taxability'], $a['product']['taxes'][0]['rate']]);
        $this->assertSame(['01', []], [$b['product']['taxability'], $b['product']['taxes']]);
        $this->get(route('admin.facturas.crear'))->assertOk()->assertSee('Clave de producto o servicio')->assertSee('Objeto de impuesto');
    }
}
