<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Mail\CorreoVandu;
use App\Models\Conciliacion;
use App\Models\Cuenta;
use App\Models\CuentaDinero;
use App\Models\Gasto;
use App\Models\MovimientoDinero;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\Venta;
use App\Support\Cuentas;
use App\Support\Dinero\Movimientos;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Cuentas de dinero (caja, banco, terminal), conciliación, flujo de efectivo y paquete para el contador */
class DineroTest extends TestCase
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
        $alvar = User::factory()->create(['plataforma' => true]);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Paletas Lupe', 'giro' => 'comercio', 'dueno_nombre' => 'Lupe', 'dueno_email' => 'lupe@paletas.mx']);
        $this->actingAs($alvar)->put(route('admin.plataforma.update', $this->tienda), [
            'nombre' => $this->tienda->nombre, 'giro' => 'comercio', '_extras' => 1, 'extras' => ['gastos'], 'gastos_tope' => 150,
        ])->assertSessionHas('ok');
        Cuentas::activar($this->tienda->refresh());
        Movimientos::olvidar();
        $this->paleta = Producto::create(['nombre' => 'Paleta de mango', 'precio' => 25, 'costo' => 9, 'unidad' => 'pieza']);
        Inventario::mover($this->paleta, 'entrada', 500, 'Inicial');
        $this->actingAs($this->dueno);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vender(int $cantidad, string $forma, array $extra = []): Venta
    {
        $this->post(route('admin.pedidos.store'), $extra + [
            'productos' => [['producto_id' => $this->paleta->id, 'cantidad' => $cantidad, 'precio' => 25]], 'pago' => 'contado', 'forma' => $forma, 'entrega' => 'ahora',
        ])->assertSessionHasNoErrors();
        return Venta::latest('id')->firstOrFail();
    }

    private function cuentas(): array
    {
        return CuentaDinero::orderBy('orden')->get()->keyBy('tipo')->all();
    }

    public function test_cuentas_saldos_traspaso_y_conciliacion(): void
    {
        // Lo registrado antes de tener cuentas se acomoda al crearlas
        $this->vender(10, 'efectivo');                                                   // $250 efectivo
        $this->get(route('admin.finanzas', 'dinero'))->assertOk()->assertSee('Crear Caja, Banco y Terminal');
        $this->post(route('admin.finanzas.cuentas.basicas'), ['saldo_desde' => '2026-10-01'])->assertSessionHas('ok');
        Movimientos::olvidar();
        ['efectivo' => $caja, 'banco' => $banco, 'terminal' => $terminal] = $this->cuentas();
        $this->assertSame($caja->id, \App\Models\VentaPago::first()->cuenta_dinero_id);

        // Saldo inicial del banco
        $this->put(route('admin.finanzas.cuentas.update', $banco), ['nombre' => 'BBVA', 'tipo' => 'banco', 'formas' => ['transferencia'], 'saldo_inicial' => 10000, 'saldo_desde' => '2026-10-01', 'activa' => 1])
            ->assertSessionHas('ok');

        // Lo nuevo cae solo en su cuenta
        $this->vender(4, 'tarjeta');                                                    // $100 terminal
        $this->vender(20, 'transferencia');                                              // $500 banco
        $g = Gasto::create(['fecha' => '2026-10-20', 'concepto' => 'Gasolina', 'categoria' => 'combustible', 'subtotal' => 300, 'iva' => 48, 'total' => 348, 'forma_pago' => 'efectivo', 'origen' => 'manual']);
        $this->assertSame($caja->id, $g->cuenta_dinero_id);
        $prov = Proveedor::create(['empresa' => 'Frutas del Sureste']);
        $o = OrdenCompra::create(['proveedor_id' => $prov->id, 'titulo' => 'Mango', 'estado' => 'entregada', 'con_iva' => false, 'iva_porcentaje' => 16]);
        $o->conceptos()->create(['descripcion' => 'Mango', 'cantidad' => 10, 'precio' => 120, 'unidad' => 'kg']);
        $o->update(['estado' => 'pagada']);
        $this->assertSame([$banco->id, '2026-10-26'], [$o->fresh()->cuenta_dinero_id, $o->fresh()->pagada_el->toDateString()]);

        // Traspaso: deposito la caja al banco
        $this->post(route('admin.finanzas.movimientos.store'), ['tipo' => 'traspaso', 'de_id' => $caja->id, 'a_id' => $banco->id, 'monto' => 200, 'fecha' => '2026-10-25'])->assertSessionHas('ok');
        // Retiro del dueño
        $this->post(route('admin.finanzas.movimientos.store'), ['tipo' => 'retiro', 'de_id' => $banco->id, 'monto' => 1000, 'fecha' => '2026-10-25'])->assertSessionHas('ok');

        $s = Movimientos::saldos();
        // Caja: 250 − 348 − 200 = −298 · Banco: 10000 + 500 − 1200 + 200 − 1000 = 8500 · Terminal: 100
        $this->assertEquals([-298, 8500, 100], [$s[$caja->id]['saldo'], $s[$banco->id]['saldo'], $s[$terminal->id]['saldo']]);

        $this->get(route('admin.finanzas', 'dinero'))->assertOk()->assertSee('Dinero disponible')->assertSee('$8,302.00')->assertSee('Gasolina')->assertSee('Retiro del dueño');

        // Conciliar el banco: el estado de cuenta dice 8,450 (comisión que no se registró)
        $this->post(route('admin.finanzas.cuentas.conciliar', $banco), ['fecha' => '2026-10-26', 'saldo_real' => 8450])->assertSessionHas('ok', fn ($m) => str_contains($m, 'diferencia de −$50.00'));
        $this->post(route('admin.finanzas.cuentas.conciliar', $banco), ['fecha' => '2026-10-26', 'saldo_real' => 8450, 'ajustar' => 1, 'nota' => 'Comisiones'])->assertSessionHas('ok', fn ($m) => str_contains($m, 'quedó cuadrada'));
        $this->assertEquals(8450, Movimientos::saldos()[$banco->id]['saldo']);
        $this->assertSame(2, Conciliacion::count());
        $this->assertTrue(MovimientoDinero::where('tipo', 'ajuste')->where('de_id', $banco->id)->where('monto', 50)->exists());

        // Un gasto sin forma de pago queda "sin cuenta" y se asigna a mano
        $sin = Gasto::create(['fecha' => '2026-10-22', 'concepto' => 'Hielo', 'categoria' => 'materiales', 'subtotal' => 80, 'iva' => 0, 'total' => 80, 'origen' => 'manual']);
        $this->assertNull($sin->cuenta_dinero_id);
        $this->get(route('admin.finanzas', 'dinero'))->assertSee('Sin cuenta')->assertSee('Hielo');
        $this->post(route('admin.finanzas.asignar'), ['clase' => 'gasto', 'id' => $sin->id, 'cuenta_dinero_id' => $caja->id])->assertSessionHas('ok');
        $this->assertSame($caja->id, $sin->fresh()->cuenta_dinero_id);

        // Validaciones
        $this->post(route('admin.finanzas.movimientos.store'), ['tipo' => 'traspaso', 'de_id' => $caja->id, 'a_id' => $caja->id, 'monto' => 5, 'fecha' => '2026-10-25'])->assertSessionHasErrors('a_id');
    }

    public function test_reiniciar_cuentas_no_toca_ventas_ni_gastos(): void
    {
        $this->post(route('admin.finanzas.cuentas.basicas'), ['saldo_desde' => '2026-10-01']);
        Movimientos::olvidar();
        ['efectivo' => $caja, 'banco' => $banco] = $this->cuentas();
        $v = $this->vender(10, 'efectivo');
        $g = Gasto::create(['fecha' => '2026-10-20', 'concepto' => 'Hielo', 'categoria' => 'materiales', 'subtotal' => 80, 'iva' => 0, 'total' => 80, 'forma_pago' => 'efectivo', 'origen' => 'manual']);
        $this->post(route('admin.finanzas.movimientos.store'), ['tipo' => 'traspaso', 'de_id' => $caja->id, 'a_id' => $banco->id, 'monto' => 100, 'fecha' => '2026-10-25']);
        $this->post(route('admin.finanzas.cuentas.conciliar', $banco), ['fecha' => '2026-10-26', 'saldo_real' => 500, 'ajustar' => 1]);
        $this->get(route('admin.finanzas', 'dinero'))->assertSee('Reiniciar cuentas y flujo');

        // Eliminar una sola cuenta
        $this->delete(route('admin.finanzas.cuentas.destroy', $banco))->assertSessionHas('ok');
        $this->assertSame(0, MovimientoDinero::count());
        $this->assertSame(0, Conciliacion::count());

        // Reiniciar todo: hay que escribir REINICIAR
        $this->delete(route('admin.finanzas.cuentas.reiniciar'), ['confirmar' => 'si'])->assertSessionHasErrors('confirmar');
        $this->delete(route('admin.finanzas.cuentas.reiniciar'), ['confirmar' => 'reiniciar'])->assertSessionHas('ok');
        $this->assertSame(0, CuentaDinero::count());
        $this->assertNull($g->fresh()->cuenta_dinero_id);
        $this->assertNull(\App\Models\VentaPago::first()->cuenta_dinero_id);
        $this->assertNotNull($v->fresh(), 'La venta sigue');
        $this->assertSame(1, Gasto::count(), 'El gasto sigue');
        $this->get(route('admin.finanzas', 'dinero'))->assertOk()->assertSee('Crear Caja, Banco y Terminal');
        $this->get(route('admin.finanzas'))->assertOk()->assertSee('Da de alta tu caja y tu banco');

        // Empezar de nuevo: lo registrado se vuelve a acomodar
        $this->post(route('admin.finanzas.cuentas.basicas'), ['saldo_desde' => '2026-10-01']);
        $this->assertNotNull($g->fresh()->cuenta_dinero_id);

        // Quien no administra el negocio no puede reiniciar
        $otro = User::factory()->create(['rol_id' => \App\Models\Rol::create(['nombre' => 'Contador', 'permisos' => ['finanzas']])->id]);
        $this->actingAs($otro)->delete(route('admin.finanzas.cuentas.reiniciar'), ['confirmar' => 'REINICIAR'])->assertForbidden();
    }

    public function test_flujo_y_proyeccion(): void
    {
        $this->post(route('admin.finanzas.cuentas.basicas'), ['saldo_desde' => '2026-10-01']);
        $this->vender(40, 'efectivo');                                                   // entra 1,000
        $this->vender(20, 'efectivo', ['pago' => 'credito', 'dias' => 7, 'cliente_nombre' => 'Tienda Mary', 'cliente_telefono' => '9991112233']);             // 500 a crédito, vence el 2 nov
        Gasto::create(['fecha' => '2026-10-10', 'concepto' => 'Renta', 'categoria' => 'renta', 'subtotal' => 2000, 'iva' => 0, 'total' => 2000, 'forma_pago' => 'efectivo', 'origen' => 'manual']);
        $prov = Proveedor::create(['empresa' => 'Azúcar del Mayab']);
        $o = OrdenCompra::create(['proveedor_id' => $prov->id, 'titulo' => 'Azúcar', 'estado' => 'entregada', 'con_iva' => false, 'iva_porcentaje' => 16, 'fecha_entrega' => '2026-11-04']);
        $o->conceptos()->create(['descripcion' => 'Azúcar', 'cantidad' => 1, 'precio' => 900, 'unidad' => 'bulto']);

        $f = \App\Support\Dinero\Flujo::datos('2026-10-01', '2026-10-26');
        $this->assertEquals([1000, 2000, -1000, -1000], [$f['entradas'], $f['salidas'], $f['neto'], $f['saldoHoy']]);
        $semana2 = $f['proyeccion']['semanas'][1];                                       // 2 al 8 de noviembre
        $this->assertEquals([500, 900, -1400], [$semana2['entra'], $semana2['sale'], $semana2['saldo']]);
        $this->assertNotNull($f['proyeccion']['alerta']);

        $this->get(route('admin.finanzas', 'dinero'))->assertOk()->assertSee('Próximas 8 semanas')->assertSee('Azúcar del Mayab')->assertSee('Ojo:');
    }

    public function test_paquete_para_el_contador(): void
    {
        Mail::fake();
        Storage::put('gastos/f.xml', '<cfdi:Comprobante/>');
        Storage::put('gastos/t.jpg', 'jpeg');
        Gasto::create(['fecha' => '2026-09-08', 'concepto' => 'Luz', 'proveedor' => 'CFE', 'categoria' => 'servicios', 'subtotal' => 1000, 'iva' => 160, 'total' => 1160,
            'origen' => 'xml', 'xml' => 'gastos/f.xml', 'uuid' => 'ABCDEF12-0000-0000-0000-000000000000', 'forma_pago' => 'transferencia']);
        Gasto::create(['fecha' => '2026-09-10', 'concepto' => 'Gasolina reparto', 'categoria' => 'combustible', 'subtotal' => 500, 'iva' => 80, 'total' => 580, 'origen' => 'foto', 'foto' => 'gastos/t.jpg']);
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00', 'America/Merida'));
        $this->vender(10, 'efectivo', ['con_iva' => 1]);
        Carbon::setTestNow(Carbon::parse('2026-10-26 10:00:00', 'America/Merida'));

        $this->get(route('admin.finanzas', 'utilidad'))->assertOk()->assertSee('Paquete para el contador');
        $r = $this->get(route('admin.finanzas.paquete', ['mes' => '2026-09']))->assertOk();
        $this->assertStringContainsString('paletas-lupe-contabilidad-2026-09.zip', $r->headers->get('content-disposition'));
        $ruta = $r->baseResponse->getFile()->getPathname();
        $z = new \ZipArchive();
        $this->assertTrue($z->open($ruta) === true);
        $nombres = collect(range(0, $z->numFiles - 1))->map(fn ($i) => $z->getNameIndex($i));
        $this->assertTrue($nombres->contains('Resumen Septiembre 2026.xlsx'));
        $this->assertTrue($nombres->contains('LEEME.txt'));
        $this->assertTrue($nombres->contains(fn ($n) => str_starts_with($n, 'Gastos/Facturas/2026-09-08 CFE ABCDEF12') && str_ends_with($n, '.xml')));
        $this->assertTrue($nombres->contains(fn ($n) => str_starts_with($n, 'Gastos/Tickets/2026-09-10')));
        $leeme = $z->getFromName('LEEME.txt');
        $this->assertStringContainsString('Gastos SIN factura (1)', $leeme);
        $this->assertStringContainsString('Gasolina reparto', $leeme);
        // El Excel se abre: trae las hojas y los datos
        file_put_contents($x = tempnam(sys_get_temp_dir(), 'x'), $z->getFromName('Resumen Septiembre 2026.xlsx'));
        $z->close();
        $xl = new \ZipArchive(); $xl->open($x);
        $this->assertStringContainsString('Facturas emitidas', $xl->getFromName('xl/workbook.xml'));
        $this->assertStringContainsString('Gasolina reparto', $xl->getFromName('xl/worksheets/sheet3.xml'));
        $this->assertStringContainsString('V-0001', $xl->getFromName('xl/worksheets/sheet2.xml'));
        $this->assertNotFalse(simplexml_load_string($xl->getFromName('xl/worksheets/sheet1.xml')));
        $xl->close();

        // Enviar por correo: se guarda el correo y llega un enlace firmado que funciona sin sesión
        $this->post(route('admin.finanzas.paquete.enviar'), ['mes' => '2026-09', 'email' => 'conta@despacho.mx', 'nombre' => 'Mario'])->assertSessionHas('ok');
        $this->assertSame('conta@despacho.mx', $this->tienda->fresh()->ajuste('contador.email'));
        $url = null;
        Mail::assertSent(CorreoVandu::class, function ($m) use (&$url) { $url = $m->url; return $m->hasTo('conta@despacho.mx') && str_contains($m->asunto, 'Septiembre 2026'); });
        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/zip');
        $this->get(route('contador.paquete', ['cuenta' => $this->tienda->id, 'mes' => '2026-09']))->assertForbidden();
    }
}
