<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Correo;
use App\Models\Presupuesto;
use App\Models\PresupuestoFactura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** La factura de cada cotización: se sube en el panel, el cliente la descarga y se envía por correo ya adjunta */
class FacturasTest extends TestCase
{
    use RefreshDatabase;

    private Presupuesto $p;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create());
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X', 'email' => 'ana@hotel.mx', 'email_factura' => 'conta@hotel.mx']);
        $this->p = Presupuesto::nuevaPara($c)->fill(['estado' => 'aceptada']);
        $this->p->save();
        $this->p->conceptos()->create(['titulo' => 'Video corporativo', 'descripcion' => '', 'cantidad' => 1, 'precio' => 22000, 'orden' => 0]);
    }

    private function subir(array $archivos)
    {
        return $this->post(route('admin.presupuestos.facturas.subir', $this->p), ['facturas' => $archivos]);
    }

    private function pdf(string $n = 'A123.pdf') { return UploadedFile::fake()->createWithContent($n, '%PDF-1.4 factura'); }
    private function xml(string $n = 'A123.xml') { return UploadedFile::fake()->createWithContent($n, '<?xml version="1.0"?><cfdi:Comprobante/>'); }

    public function test_sube_la_factura_y_el_cliente_la_descarga(): void
    {
        $this->subir([$this->xml(), $this->pdf()])->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, '2 archivos'));
        $this->assertSame(['A123.pdf', 'A123.xml'], $this->p->fresh()->facturas_ordenadas->pluck('nombre')->all());

        $this->get(route('admin.presupuestos.edit', $this->p))->assertOk()
            ->assertSee('A123.pdf')->assertSee('Por enviar')->assertSee('data-correo="factura"', false);

        // En la cotización del cliente y en su espacio
        $pdf = $this->p->facturas()->where('nombre', 'A123.pdf')->sole();
        auth()->logout();
        $this->get($this->p->url_publica)->assertOk()->assertSee('Factura PDF')->assertSee('Factura XML');
        $r = $this->get(route('presupuesto.factura', [$this->p->token, $pdf->id]))->assertOk();
        $this->assertStringContainsString('attachment', $r->headers->get('content-disposition'));
        $this->get($this->p->cliente->portal_url)->assertOk()->assertSee('Tus facturas')->assertSee($this->p->folio);

        // No se puede bajar la factura de otra cotización con este enlace
        $otra = Presupuesto::nuevaPara($this->p->cliente); $otra->save();
        $this->get(route('presupuesto.factura', [$otra->token, $pdf->id]))->assertNotFound();
    }

    public function test_mismo_nombre_reemplaza_y_solo_pdf_xml_zip(): void
    {
        $this->subir([$this->pdf()]);
        $vieja = PresupuestoFactura::sole();
        $this->subir([$this->pdf()]);
        $this->assertSame(1, PresupuestoFactura::count());
        Storage::disk('local')->assertMissing($vieja->ruta);

        $this->subir([UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('facturas.0');
        $this->subir([])->assertSessionHasErrors('facturas');
    }

    public function test_enviar_factura_adjunta_la_guardada_y_la_marca_enviada(): void
    {
        Mail::fake();
        $this->subir([$this->pdf(), $this->xml()]);

        $this->post('/admin/correos', [
            'contexto_tipo' => 'presupuesto', 'contexto_id' => $this->p->id, 'plantilla' => 'factura',
            'para' => 'conta@hotel.mx', 'asunto' => 'Factura', 'cuerpo' => 'Hola Ana', 'adjuntar_factura' => 1,
        ])->assertSessionHasNoErrors();

        Mail::assertSent(CorreoVandu::class, fn ($m) => array_column($m->archivos, 'nombre') === ['A123.pdf', 'A123.xml'] && str_starts_with($m->archivos[0]['data'], '%PDF'));
        $this->assertSame(['A123.pdf', 'A123.xml'], Correo::sole()->adjuntos);
        $this->assertNotNull(PresupuestoFactura::first()->enviada_at);
        $this->assertTrue($this->p->eventos()->where('tipo', 'factura')->exists());
        $this->get(route('admin.presupuestos.edit', $this->p))->assertSee('Enviar de nuevo');
        $this->get(route('admin.clientes.show', $this->p->cliente))->assertOk()->assertSee($this->p->folio)->assertSee('Enviada');
    }

    public function test_ficha_del_cliente_por_secciones_con_acceso_a_facturas(): void
    {
        $c = $this->p->cliente;
        // Aceptada sin factura: aparece como pendiente y en el menú de Factura
        $this->get(route('admin.clientes.show', $c))->assertOk()
            ->assertSee('Por atender')->assertSee("{$this->p->folio} está aceptada y no tiene factura")->assertSee('Subir factura de ' . $this->p->folio)
            ->assertSee('Cotizaciones')->assertSee('Facturación')->assertSee('Correos y mensajes');

        $this->subir([$this->pdf(), $this->xml()]);
        $enviar = route('admin.presupuestos.edit', [$this->p, 'correo' => 'factura']);
        $this->get(route('admin.clientes.show', $c))->assertSee('está lista para enviar')->assertSee($enviar, false);
        $this->get(route('admin.clientes.show', [$c, 'ver' => 'facturacion']))->assertOk()->assertSee('A123.pdf')->assertSee('Datos fiscales')->assertSee('Constancia de Situación Fiscal');
        $this->get(route('admin.clientes.show', [$c, 'ver' => 'cotizaciones']))->assertOk()->assertSee('Por enviar')->assertSee('Enviar por correo');
        $this->get(route('admin.clientes.show', [$c, 'ver' => 'proyectos']))->assertOk()->assertSee('Sin proyectos');
        $this->get(route('admin.clientes.show', [$c, 'ver' => 'correos']))->assertOk()->assertSee('Mensajes desde el sitio');
        $this->get(route('admin.clientes.show', [$c, 'ver' => 'otra-cosa']))->assertOk()->assertSee('Por atender');
    }

    public function test_cotizacion_por_secciones(): void
    {
        $edit = route('admin.presupuestos.edit', $this->p);
        $html = $this->get($edit)->assertOk()->assertSee('Contenido')->assertSee('Condiciones')->assertSee('Pago y encabezado')
            ->assertSee('Envío y respuesta')->assertSee('Factura y proyecto')->getContent();
        $this->assertStringContainsString('seccion\u0022:\u0022contenido', $html);
        $this->assertStringContainsString('seccion\u0022:\u0022factura', $this->get($edit . '?ver=factura')->getContent());
        // Todos los campos siguen en el mismo formulario
        foreach (['name="observaciones"', 'name="clabe"', 'name="emisor_nombre"', 'name="notas_internas"', 'name="vigente_hasta"', 'name="estado"'] as $campo) {
            $this->assertStringContainsString($campo, $html);
        }
        // Una cotización nueva solo tiene las secciones de contenido
        $this->get(route('admin.presupuestos.create'))->assertOk()->assertSee('Pago y encabezado')->assertDontSee('Envío y respuesta');

        // Si el error está en otra sección, se abre esa sección
        $this->from($edit . '?ver=contenido')->put(route('admin.presupuestos.update', $this->p), [
            'cliente_id' => $this->p->cliente_id, 'cliente_nombre' => 'Ana López', 'fecha' => '2026-10-12', 'titulo' => 'Cotización',
            'emisor_nombre' => '', 'modo_iva' => 'mas_iva', 'iva_porcentaje' => 16, 'vigente_hasta' => now()->addDays(10)->format('Y-m-d\\TH:i'),
            'estado' => 'aceptada', 'conceptos' => [['titulo' => 'Video', 'cantidad' => 1, 'precio' => 22000]],
        ])->assertSessionHasErrors('emisor_nombre')->assertRedirect();
        $this->assertStringContainsString('seccion\u0022:\u0022pago', $this->get($edit . '?ver=contenido')->getContent());
    }

    public function test_aceptada_destaca_proyecto_y_factura(): void
    {
        $edit = route('admin.presupuestos.edit', $this->p);
        $this->get($edit)->assertSee('¡Cotización aceptada!')->assertSee('Crear el proyecto')->assertSee('Subir la factura')
            ->assertSee(route('admin.proyectos.create', [$this->p, 'tipo' => \App\Models\Proyecto::tipoSugerido($this->p)]), false);

        $this->subir([$this->pdf(), $this->xml()]);
        $this->get($edit)->assertSee('Enviar la factura')->assertSee('A123.pdf · A123.xml');

        \App\Models\Proyecto::desdePresupuesto($this->p->fresh('conceptos'), array_key_first(config('vandu.proyectos')));
        PresupuestoFactura::query()->update(['enviada_at' => now()]);
        $this->get($edit)->assertDontSee('¡Cotización aceptada!');

        // Una que no está aceptada no lo muestra
        $this->p->update(['estado' => 'enviada']);
        $this->get($edit)->assertDontSee('¡Cotización aceptada!');
    }

    public function test_lista_de_cotizaciones_abre_con_todas(): void
    {
        $borrador = Presupuesto::nuevaPara($this->p->cliente); $borrador->save();
        $borrador->conceptos()->create(['titulo' => 'Logo nuevo', 'descripcion' => '', 'cantidad' => 1, 'precio' => 5000, 'orden' => 0]);

        // Sin filtro se ven todas, con sus tarjetas de resumen
        $this->get(route('admin.presupuestos.index'))->assertOk()
            ->assertSee($this->p->folio)->assertSee($borrador->folio)
            ->assertSee('Por responder')->assertSee('Aceptado este mes')->assertSee('Borrador sin enviar')
            ->assertSee('Crear proyecto')->assertSee('Sin factura');
        // Una vista vacía no deja la pantalla en blanco: ofrece ver todas
        $this->get(route('admin.presupuestos.index', ['filtro' => 'vigentes']))->assertOk()->assertSee('Nada en «Por responder»')->assertSee('Ver todas (2)');
        $this->get(route('admin.presupuestos.index', ['filtro' => 'borrador']))->assertSee($borrador->folio)->assertDontSee('>' . $this->p->folio . '<', false);
        // Buscar también por lo cotizado
        $this->get(route('admin.presupuestos.index', ['q' => 'Logo']))->assertSee($borrador->folio)->assertDontSee('Video corporativo');
    }

    public function test_complementos_de_pago_ppd(): void
    {
        Mail::fake();
        $c = $this->p->cliente; $c->update(['metodo_pago' => 'credito']);
        $this->assertSame('PPD', $this->p->fresh()->metodo_sat_efectivo, 'A crédito sugiere PPD');
        $this->subir([$this->pdf(), $this->xml()]);
        $pr = \App\Models\Proyecto::desdePresupuesto($this->p->fresh('conceptos'), array_key_first(config('vandu.proyectos')));
        $pago = $pr->pagos()->orderBy('orden')->first();
        $pago->update(['pagado_el' => now()->subDay()]);

        // Pagado sin complemento: se avisa en la cotización y en el cliente
        $edit = route('admin.presupuestos.edit', [$this->p, 'ver' => 'factura']);
        $this->get($edit)->assertSee('Complementos de pago')->assertSee('falta su complemento');
        $this->get(route('admin.clientes.show', $c))->assertSee("Falta el complemento del {$pago->concepto}");

        // Se sube ligado al pago
        $this->post(route('admin.presupuestos.complementos.subir', $this->p), [
            'pago_id' => $pago->id, 'complemento' => [$this->pdf('REP-1.pdf'), $this->xml('REP-1.xml')],
        ])->assertRedirect($edit)->assertSessionHasNoErrors();
        $comps = PresupuestoFactura::where('tipo', 'complemento')->get();
        $this->assertCount(2, $comps);
        $this->assertSame(1, $comps->pluck('lote')->unique()->count());
        $this->assertSame(['A123.pdf', 'A123.xml'], $this->p->fresh()->facturas_ordenadas->pluck('nombre')->all(), 'La factura no se mezcla con los complementos');
        $lote = $comps->first()->lote;
        $this->get($edit)->assertSee('REP-1.pdf')->assertSee('data-correo="complemento@' . $lote . '"', false);
        $this->get(route('admin.clientes.show', $c))->assertSee('complemento de pago de ' . $this->p->folio . ' está listo para enviar')->assertDontSee('Falta el complemento');

        // No se puede ligar a un pago de otro proyecto
        $this->post(route('admin.presupuestos.complementos.subir', $this->p), ['pago_id' => 999999, 'complemento' => [$this->pdf('X.pdf')]])->assertSessionHasErrors('pago_id');

        // El correo del complemento lleva solo sus archivos y lo marca enviado
        $pl = \App\Support\Correos::plantillas(\App\Support\Correos::contexto('presupuesto', $this->p->id));
        $this->assertArrayHasKey("complemento@$lote", $pl);
        $this->assertSame('conta@hotel.mx', $pl["complemento@$lote"]['para']);
        $this->post('/admin/correos', [
            'contexto_tipo' => 'presupuesto', 'contexto_id' => $this->p->id, 'plantilla' => "complemento@$lote",
            'para' => 'conta@hotel.mx', 'asunto' => 'Complemento', 'cuerpo' => 'Hola', 'adjuntar_factura' => 1,
        ])->assertSessionHasNoErrors();
        Mail::assertSent(CorreoVandu::class, fn ($m) => array_column($m->archivos, 'nombre') === ['REP-1.pdf', 'REP-1.xml']);
        $this->assertNotNull(PresupuestoFactura::where('lote', $lote)->first()->enviada_at);
        $this->assertNull(PresupuestoFactura::where('tipo', 'factura')->first()->enviada_at, 'La factura sigue sin enviar');
        $this->assertTrue($this->p->eventos()->where('tipo', 'complemento')->exists());

        // El cliente lo descarga desde su cotización
        auth()->logout();
        $this->get($this->p->url_publica)->assertSee('Complemento de pago PDF');
        $this->get(route('presupuesto.factura', [$this->p->token, $comps->first()->id]))->assertOk();
    }

    public function test_desde_el_proyecto_tambien_lleva_la_factura(): void
    {
        Mail::fake();
        $this->subir([$this->pdf()]);
        $pr = \App\Models\Proyecto::create(['cliente_id' => $this->p->cliente_id, 'presupuesto_id' => $this->p->id, 'tipo' => array_key_first(config('vandu.proyectos')), 'nombre' => 'Video', 'estado' => 'activo', 'monto_total' => 25520]);
        $this->post('/admin/correos', [
            'contexto_tipo' => 'proyecto', 'contexto_id' => $pr->id, 'plantilla' => 'factura',
            'para' => 'conta@hotel.mx', 'asunto' => 'Factura', 'cuerpo' => 'Hola', 'adjuntar_factura' => 1,
        ])->assertSessionHasNoErrors();
        Mail::assertSent(CorreoVandu::class, fn ($m) => array_column($m->archivos, 'nombre') === ['A123.pdf']);
    }

    public function test_quitar_y_borrar_cotizacion_limpian_archivos(): void
    {
        $this->subir([$this->pdf(), $this->xml()]);
        [$a, $b] = PresupuestoFactura::orderBy('id')->get()->all();
        $this->delete(route('admin.presupuestos.facturas.borrar', [$this->p, $a]))->assertRedirect();
        Storage::disk('local')->assertMissing($a->ruta);

        $this->delete(route('admin.presupuestos.destroy', $this->p))->assertRedirect();
        Storage::disk('local')->assertMissing($b->ruta);
        $this->assertSame(0, PresupuestoFactura::count());
    }
}
