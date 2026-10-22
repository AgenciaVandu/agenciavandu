<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\Membresia;
use App\Models\Paquete;
use App\Models\Rol;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Gastos\Cfdi;
use App\Support\Gastos\LectorTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Gastos sueltos: módulo que prende la plataforma por negocio; foto del ticket leída con IA, XML de factura o a mano;
 * quien sube ve los suyos y Finanzas ve todo; se resta en los reportes.
 */
class GastosTest extends TestCase
{
    use RefreshDatabase;

    private User $alvar;
    private Cuenta $tienda;
    private User $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'America/Merida'));
        config(['services.anthropic.key' => null]);
        $this->alvar = User::factory()->create(['name' => 'Alvar Buenfil', 'plataforma' => true]);
        [$this->tienda, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Losos', 'giro' => 'comercio', 'dueno_nombre' => 'Luis', 'dueno_email' => 'luis@losos.mx']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function prender(Cuenta $c, bool $si = true, int $tope = 150): void
    {
        $this->actingAs($this->alvar)->put(route('admin.plataforma.update', $c), [
            'nombre' => $c->nombre, 'giro' => $c->giro, '_extras' => 1, 'extras' => $si ? ['gastos'] : [], 'gastos_tope' => $tope,
        ])->assertSessionHas('ok');
        $c->refresh();
        Cuentas::activar($c);
    }

    private function usuario(string $rol, string $email): User
    {
        Cuentas::activar($this->tienda);
        return User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => 'x', 'activo' => true, 'rol_id' => Rol::where('nombre', $rol)->value('id')]);
    }

    private function cfdi(string $uuid = '6F1A2B3C-4D5E-4F60-8A7B-9C0D1E2F3A4B', string $total = '1160.00'): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-10-20T11:15:00" SubTotal="1000.00" Total="$total" Moneda="MXN" TipoDeComprobante="I" FormaPago="04" Exportacion="01" LugarExpedicion="97000">
  <cfdi:Emisor Rfc="HDM001017AS1" Nombre="HOME DEPOT MEXICO" RegimenFiscal="601"/>
  <cfdi:Receptor Rfc="LOS200101AB1" Nombre="LOSOS SA DE CV" UsoCFDI="G03" DomicilioFiscalReceptor="97000" RegimenFiscalReceptor="601"/>
  <cfdi:Conceptos>
    <cfdi:Concepto ClaveProdServ="27111700" Cantidad="1" ClaveUnidad="H87" Descripcion="Taladro inalámbrico 20V" ValorUnitario="1000.00" Importe="1000.00" ObjetoImp="02">
      <cfdi:Impuestos><cfdi:Traslados><cfdi:Traslado Base="1000.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="160.00"/></cfdi:Traslados></cfdi:Impuestos>
    </cfdi:Concepto>
  </cfdi:Conceptos>
  <cfdi:Impuestos TotalImpuestosTrasladados="160.00"><cfdi:Traslados><cfdi:Traslado Base="1000.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="160.00"/></cfdi:Traslados></cfdi:Impuestos>
  <cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="$uuid" FechaTimbrado="2026-10-20T11:16:00" RfcProvCertif="SAT970701NN3" SelloCFD="x" NoCertificadoSAT="1" SelloSAT="x"/></cfdi:Complemento>
</cfdi:Comprobante>
XML;
    }

    private function ticket(): UploadedFile
    {
        $img = imagecreatetruecolor(300, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start(); imagejpeg($img); $jpg = ob_get_clean();
        return UploadedFile::fake()->createWithContent('ticket.jpg', $jpg);
    }

    public function test_solo_la_plataforma_lo_prende_y_todo_el_equipo_puede_subir(): void
    {
        Cuentas::activar($this->tienda);
        $this->actingAs($this->dueno)->get(route('admin.gastos'))->assertNotFound();

        $this->prender($this->tienda);
        $this->assertTrue($this->tienda->tiene('gastos'));
        $this->assertContains('gastos', Rol::where('nombre', 'Vendedor')->first()->permisos);
        $this->actingAs($this->dueno)->get(route('admin.gastos'))->assertOk()->assertSee('Gastos');

        // Un dueño de negocio no puede prenderse módulos
        $this->actingAs($this->dueno)->put(route('admin.plataforma.update', $this->tienda), ['nombre' => 'X', 'giro' => 'comercio', '_extras' => 1, 'extras' => []])->assertForbidden();

        // Se le quita a un rol desde Usuarios y roles
        $vende = $this->usuario('Vendedor', 'vende@losos.mx');
        $this->actingAs($vende)->get(route('admin.gastos.create'))->assertOk()->assertSee('Foto del ticket');
        Rol::where('nombre', 'Vendedor')->first()->update(['permisos' => ['ventas', 'cotizaciones', 'clientes']]);
        $this->actingAs($vende->fresh())->get(route('admin.gastos'))->assertForbidden();

        $this->prender($this->tienda, false);
        $this->actingAs($this->dueno)->get(route('admin.gastos'))->assertNotFound();
    }

    public function test_cada_quien_ve_sus_gastos_y_finanzas_ve_todos(): void
    {
        $this->prender($this->tienda);
        $vende = $this->usuario('Vendedor', 'vende@losos.mx');
        $this->actingAs($vende)->post(route('admin.gastos.store'), [
            'fecha' => '2026-10-21', 'concepto' => 'Gasolina magna 30 L', 'categoria' => 'combustible', 'total' => '690', 'iva' => '95.17', 'forma_pago' => 'tarjeta', 'foto' => $this->ticket(),
        ])->assertRedirect(route('admin.gastos'));
        $g = Gasto::firstOrFail();
        $this->assertSame([594.83, 'foto', $vende->id], [$g->subtotal, $g->origen, $g->user_id]);
        Storage::assertExists($g->foto);
        $this->get(route('admin.gastos.archivo', [$g, 'foto']))->assertOk();

        $this->actingAs($this->dueno)->post(route('admin.gastos.store'), ['fecha' => '2026-10-22', 'concepto' => 'Comida con cliente', 'categoria' => 'comida', 'total' => '850'])->assertRedirect();

        $this->get(route('admin.gastos'));   // ya se mostró el aviso de guardado
        $this->actingAs($vende)->get(route('admin.gastos'))->assertSee('Gasolina magna')->assertDontSee('Comida con cliente');
        $mio = Gasto::where('concepto', 'Comida con cliente')->first();
        $this->get(route('admin.gastos.edit', $mio))->assertForbidden();
        $this->get(route('admin.gastos.exportar'))->assertForbidden();

        $this->actingAs($this->dueno)->get(route('admin.gastos'))->assertSee('Gasolina magna')->assertSee('Comida con cliente')->assertSee('$1,445');
        $this->get(route('admin.gastos', ['iva' => 'con']))->assertSee('$1,540');
        $this->get(route('admin.gastos.exportar'))->assertOk();
        $this->post(route('admin.gastos.store'), ['fecha' => '2026-12-01', 'concepto' => 'X', 'categoria' => 'otros', 'total' => '10'])->assertSessionHasErrors('fecha');
        $this->post(route('admin.gastos.store'), ['fecha' => '2026-10-01', 'concepto' => 'X', 'categoria' => 'otros', 'total' => '10', 'iva' => '12'])->assertSessionHasErrors('iva');
    }

    public function test_factura_xml_con_datos_exactos_y_sin_repetirse(): void
    {
        $d = Cfdi::leer($this->cfdi());
        $this->assertSame(['6F1A2B3C-4D5E-4F60-8A7B-9C0D1E2F3A4B', '2026-10-20', 'HOME DEPOT MEXICO', 'HDM001017AS1', 1000.0, 160.0, 1160.0, 'tarjeta', 'herramienta'],
            [$d['uuid'], $d['fecha'], $d['proveedor'], $d['rfc'], $d['subtotal'], $d['iva'], $d['total'], $d['forma_pago'], $d['categoria']]);
        $this->assertNull(Cfdi::leer('<html>no</html>'));
        $this->assertNull(Cfdi::leer('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>'));

        $this->prender($this->tienda);
        $xml = fn () => UploadedFile::fake()->createWithContent('factura.xml', $this->cfdi());
        $this->actingAs($this->dueno)->postJson(route('admin.gastos.xml'), ['xml' => $xml()])->assertOk()->assertJsonPath('datos.total', 1160)->assertJsonPath('repetido', null);
        $this->post(route('admin.gastos.store'), ['fecha' => '2026-10-20', 'concepto' => 'Taladro inalámbrico 20V', 'categoria' => 'herramienta', 'total' => '1160', 'iva' => '160', 'xml' => $xml()])->assertRedirect();
        $g = Gasto::firstOrFail();
        $this->assertSame(['xml', '6F1A2B3C-4D5E-4F60-8A7B-9C0D1E2F3A4B'], [$g->origen, $g->uuid]);
        $this->postJson(route('admin.gastos.xml'), ['xml' => $xml()])->assertJsonPath('repetido.fecha', '2026-10-20');
        $this->post(route('admin.gastos.store'), ['fecha' => '2026-10-20', 'concepto' => 'Otra vez', 'categoria' => 'herramienta', 'total' => '1160', 'xml' => $xml()])->assertSessionHasErrors('xml');
        $this->assertSame(1, Gasto::count());
        $this->get(route('admin.gastos.archivo', [$g, 'xml']))->assertOk();
    }

    public function test_lectura_del_ticket_con_ia_tope_y_sin_llave(): void
    {
        $this->prender($this->tienda, true, 2);
        $this->actingAs($this->dueno);
        $this->postJson(route('admin.gastos.leer'), ['foto' => $this->ticket()])->assertOk()->assertJson(['ok' => false, 'motivo' => 'sin_ia']);

        config(['services.anthropic.key' => 'sk-prueba']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'tool_use', 'name' => 'registrar_gasto', 'input' => [
                'es_comprobante' => true, 'proveedor' => 'OXXO', 'rfc' => 'CCO8605231N4', 'fecha' => '2026-10-21', 'concepto' => 'Agua y café',
                'categoria' => 'comida', 'total' => 87.5, 'iva' => null, 'forma_pago' => 'efectivo', 'dudas' => ['iva'],
            ]]],
            'usage' => ['input_tokens' => 1500, 'output_tokens' => 120],
        ])]);
        $this->postJson(route('admin.gastos.leer'), ['foto' => $this->ticket()])->assertOk()
            ->assertJson(['ok' => true, 'datos' => ['proveedor' => 'OXXO', 'total' => 87.5, 'categoria' => 'comida', 'fecha' => '2026-10-21', 'rfc' => 'CCO8605231N4'], 'dudas' => ['iva']]);
        Http::assertSent(fn ($r) => $r['model'] === 'claude-haiku-5-5' && $r['tool_choice']['name'] === 'registrar_gasto' && $r['messages'][0]['content'][0]['type'] === 'image');

        // Lo que no se ve bien no se inventa: fecha futura o total negativo quedan vacíos y en dudas
        $l = LectorTicket::limpiar(['fecha' => '2031-01-01', 'total' => -5, 'categoria' => 'inventada', 'dudas' => []]);
        $this->assertSame([null, null, 'otros'], [$l['datos']['fecha'], $l['datos']['total'], $l['datos']['categoria']]);
        $this->assertEqualsCanonicalizing(['fecha', 'total'], $l['dudas']);

        $this->postJson(route('admin.gastos.leer'), ['foto' => $this->ticket()])->assertJson(['ok' => true]);
        $this->postJson(route('admin.gastos.leer'), ['foto' => $this->ticket()])->assertJson(['ok' => false, 'motivo' => 'tope']);
        Http::assertSentCount(2);
    }

    public function test_los_gastos_se_restan_en_finanzas_y_en_el_gimnasio(): void
    {
        $this->prender($this->tienda);
        $this->actingAs($this->dueno)->post(route('admin.gastos.store'), ['fecha' => '2026-10-10', 'concepto' => 'Renta de bodega', 'categoria' => 'renta', 'total' => '8000'])->assertRedirect();
        $this->get(route('admin.finanzas'))->assertOk()->assertSee('Lo que te queda')->assertSee('Renta $8,000');

        [$gym, $laura] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura', 'dueno_email' => 'laura@axion.mx']);
        $this->prender($gym);
        $atleta = \App\Models\Cliente::create(['nombre' => 'Sofía', 'telefono' => '9991112233']);
        \App\Support\Fitness\Membresias::asignar($atleta, Paquete::create(['nombre' => '8 clases', 'precio' => 1000, 'creditos' => 8, 'vigencia_dias' => 30]), ['pagado' => true]);
        $this->actingAs($laura)->post(route('admin.gastos.store'), ['fecha' => '2026-10-15', 'concepto' => 'Luz', 'categoria' => 'servicios', 'total' => '400'])->assertRedirect();
        $this->get(route('admin.finanzas'))->assertOk()->assertSee('Te queda')->assertSee('$600');
        $this->assertSame(1, Gasto::count(), 'Cada negocio ve solo sus gastos');
    }
}
