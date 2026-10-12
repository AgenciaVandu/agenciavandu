<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenCompra;
use App\Models\Proveedor;
use App\Models\Proyecto;
use App\Models\Rol;
use App\Models\User;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Proveedores con órdenes de compra; ellos suben facturas, archivos y comentarios desde su enlace */
class ProveedoresTest extends TestCase
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

    private function proyecto(): Proyecto
    {
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel Xcanatún']);
        return Proyecto::create(['cliente_id' => $c->id, 'nombre' => 'Uniformes del hotel', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 20000]);
    }

    private function datosOrden(array $extra = []): array
    {
        return $extra + [
            'proveedor_id'  => $this->bordados->id,
            'titulo'        => 'Uniformes Hotel Xcanatún',
            'fecha'         => '2026-10-11',
            'fecha_entrega' => '2026-10-20',
            'indicaciones'  => "Logo al frente lado izquierdo, 8 cm.\nHilo blanco.",
            'con_iva'       => '1',
            'iva_porcentaje' => 16,
            'conceptos'     => [
                ['descripcion' => 'Camisa bordada', 'cantidad' => 20, 'unidad' => 'pzas', 'precio' => 250],
                ['descripcion' => 'Playera bordada', 'cantidad' => 9, 'unidad' => 'pzas', 'precio' => 120],
            ],
        ];
    }

    private function orden(array $extra = []): OrdenCompra
    {
        $this->actingAs($this->admin)->post('/admin/ordenes', $this->datosOrden($extra))->assertRedirect();
        return OrdenCompra::latest('id')->firstOrFail();
    }

    public function test_alta_de_proveedor_con_rubro_y_lista_por_rubro(): void
    {
        $this->actingAs($this->admin)->get('/admin/proveedores/create')->assertOk()->assertSee('Artículos promocionales');
        $this->post('/admin/proveedores', ['empresa' => 'Imprenta Sol', 'nombre' => 'Lucía', 'rubro' => ' impresion ', 'telefono' => '9990001122', 'rfc' => 'abc010101xyz'])
            ->assertRedirect();
        $p = Proveedor::where('empresa', 'Imprenta Sol')->firstOrFail();
        $this->assertSame('Impresión', $p->rubro, 'Usa el rubro que ya existe aunque se escriba distinto');
        $this->assertSame('ABC010101XYZ', $p->rfc);

        $this->get('/admin/proveedores?rubro=Bordados')->assertOk()->assertSee('Bordados Mérida')->assertDontSee('Imprenta Sol');
        $this->get('/admin/proveedores?q=Sol')->assertOk()->assertSee('Imprenta Sol')->assertDontSee('Bordados Mérida');
        $this->post('/admin/proveedores', ['empresa' => ''])->assertSessionHasErrors('empresa');
    }

    public function test_orden_con_varios_conceptos_totales_folio_y_pdf(): void
    {
        $pr = $this->proyecto();
        $o = $this->orden(['proyecto_id' => $pr->id]);

        $this->assertSame('OC-0001', $o->folio);
        $this->assertSame('borrador', $o->estado);
        $this->assertCount(2, $o->conceptos);
        $this->assertEquals(6080, $o->subtotal);      // 20×250 + 9×120
        $this->assertEquals(972.8, $o->iva);
        $this->assertEquals(7052.8, $o->total);
        $this->assertSame('20 pzas', $o->conceptos[0]->cantidad_texto);

        $this->get("/admin/ordenes/{$o->id}")->assertOk()->assertSee('Camisa bordada')->assertSee('$7,052.80')->assertSee('Es un borrador');
        $pdf = $this->get("/admin/ordenes/{$o->id}/pdf")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        // El proyecto y el proveedor muestran la orden
        $this->get("/admin/proyectos/{$pr->id}")->assertOk()->assertSee('OC-0001')->assertSee('$6,080.00');
        $this->get("/admin/proveedores/{$this->bordados->id}")->assertOk()->assertSee('OC-0001')->assertSee('Uniformes del hotel');
        $this->get('/admin/ordenes')->assertOk()->assertSee('OC-0001');

        // Segunda orden: siguiente folio; editar reemplaza conceptos
        $o2 = $this->orden(['titulo' => 'Gorras']);
        $this->assertSame('OC-0002', $o2->folio);
        $this->put("/admin/ordenes/{$o2->id}", $this->datosOrden(['titulo' => 'Gorras', 'con_iva' => '0', 'conceptos' => [['descripcion' => 'Gorra', 'cantidad' => 30, 'precio' => 80]]]))->assertRedirect();
        $o2->refresh()->load('conceptos');
        $this->assertCount(1, $o2->conceptos);
        $this->assertEquals(2400, $o2->total);
    }

    public function test_el_proveedor_solo_ve_lo_enviado_y_sube_factura_archivos_y_comentarios(): void
    {
        $o = $this->orden();
        $portal = $this->bordados->portal_url;

        // Borrador: no la ve
        $this->app['auth']->forgetGuards();
        $this->get($portal)->assertOk()->assertDontSee('Uniformes Hotel Xcanatún');
        $this->get("$portal/orden/{$o->id}")->assertNotFound();

        // Se envía por WhatsApp con el enlace de la orden
        $wa = $this->actingAs($this->admin)->post("/admin/ordenes/{$o->id}/enviar", ['por' => 'whatsapp']);
        $wa->assertRedirect();
        $this->assertStringStartsWith('https://wa.me/529991234567?text=', $wa->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode($o->url_proveedor), $wa->headers->get('Location'));
        $this->assertSame('enviada', $o->fresh()->estado);

        $this->app['auth']->forgetGuards();
        $this->get($portal)->assertOk()->assertSee('Hola, Carlos')->assertSee('Uniformes Hotel Xcanatún')->assertSee('Falta factura');
        $this->get("$portal/orden/{$o->id}")->assertOk()->assertSee('Camisa bordada')->assertSee('Logo al frente');
        $this->assertNotNull($o->fresh()->vista_at);
        $this->assertSame('application/pdf', $this->get("$portal/orden/{$o->id}/pdf")->headers->get('Content-Type'));

        // Factura (PDF + XML a la vez) → la orden pasa a Facturada
        $this->post("$portal/orden/{$o->id}/archivos", ['tipo' => 'factura', 'archivos' => [
            UploadedFile::fake()->create('F-123.pdf', 120, 'application/pdf'),
            UploadedFile::fake()->createWithContent('F-123.xml', '<cfdi:Comprobante/>'),
        ]])->assertRedirect();
        $this->assertSame('facturada', $o->fresh()->estado);
        $this->assertSame(2, $o->archivos()->where('tipo', 'factura')->where('autor', 'proveedor')->count());

        // Ponchado y comentario
        $this->post("$portal/orden/{$o->id}/archivos", ['tipo' => 'ponchado', 'archivos' => [UploadedFile::fake()->createWithContent('logo.dst', 'DST')]])->assertRedirect();
        $this->post("$portal/orden/{$o->id}/comentar", ['texto' => 'Las playeras salen el jueves.'])->assertRedirect();

        // No acepta ejecutables ni le deja subir "diseños" (eso es nuestro)
        $this->post("$portal/orden/{$o->id}/archivos", ['tipo' => 'otro', 'archivos' => [UploadedFile::fake()->createWithContent('virus.exe', 'MZ')]])->assertSessionHasErrors();
        $this->post("$portal/orden/{$o->id}/archivos", ['tipo' => 'diseno', 'archivos' => [UploadedFile::fake()->createWithContent('a.pdf', '%PDF')]])->assertSessionHasErrors('tipo');

        $html = $this->get("$portal/orden/{$o->id}")->assertOk()->getContent();
        foreach (['F-123.pdf', 'F-123.xml', 'logo.dst', 'Las playeras salen el jueves.', 'Recibida'] as $t) {
            $this->assertStringContainsString($t, $html, "Falta: $t");
        }

        // El panel lo ve todo y responde
        $this->actingAs($this->admin)->get("/admin/ordenes/{$o->id}")->assertOk()->assertSee('F-123.xml')->assertSee('logo.dst')->assertSee('Las playeras salen el jueves.');
        $ponchado = $o->archivos()->where('tipo', 'ponchado')->first();
        $this->get("/admin/ordenes/{$o->id}/archivos/{$ponchado->id}")->assertOk()->assertHeader('Content-Disposition');
        $this->post("/admin/ordenes/{$o->id}/comentar", ['texto' => 'Perfecto, gracias.'])->assertRedirect();
        $this->patch("/admin/ordenes/{$o->id}/estado", ['estado' => 'pagada'])->assertRedirect();

        $this->app['auth']->forgetGuards();
        $this->get("$portal/orden/{$o->id}")->assertSee('Perfecto, gracias.');
        // Ya pagada: no puede quitar su factura
        $fact = $o->archivos()->where('tipo', 'factura')->first();
        $this->delete("$portal/orden/{$o->id}/archivos/{$fact->id}")->assertStatus(422);
        $this->get($portal)->assertSee('Historial');
    }

    public function test_un_proveedor_no_ve_lo_de_otro(): void
    {
        $o = $this->orden();
        $this->actingAs($this->admin)->post("/admin/ordenes/{$o->id}/enviar")->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/ordenes/{$o->id}/archivos", ['tipo' => 'diseno', 'archivos' => [UploadedFile::fake()->createWithContent('logo-hotel.ai', 'AI')]])->assertRedirect();
        $diseno = $o->archivos()->firstOrFail();
        $this->assertSame('agencia', $diseno->autor);

        $otro = Proveedor::create(['empresa' => 'Imprenta Sol']);
        $this->app['auth']->forgetGuards();
        $url = $otro->portal_url;
        $this->get($url)->assertOk()->assertDontSee('Uniformes');
        $this->get("$url/orden/{$o->id}")->assertNotFound();
        $this->get("$url/orden/{$o->id}/pdf")->assertNotFound();
        $this->post("$url/orden/{$o->id}/comentar", ['texto' => 'hola'])->assertNotFound();
        $this->get("$url/orden/{$o->id}/archivos/{$diseno->id}")->assertNotFound();

        // El dueño ve nuestro diseño pero no lo puede borrar
        $propio = $this->bordados->portal_url;
        $this->get("$propio/orden/{$o->id}")->assertSee('logo-hotel.ai')->assertSee('Archivos de la orden');
        $this->delete("$propio/orden/{$o->id}/archivos/{$diseno->id}")->assertNotFound();
        $this->get('/proveedor/' . str_repeat('x', 32))->assertNotFound();
    }

    public function test_sin_permiso_de_proveedores_no_entra(): void
    {
        $foto = User::factory()->create(['rol_id' => Rol::where('nombre', 'Fotógrafo')->value('id')]);
        $this->actingAs($foto)->get('/admin/proveedores')->assertForbidden();
        $this->actingAs($foto)->get('/admin/ordenes/create')->assertForbidden();

        $rol = Rol::create(['nombre' => 'Compras', 'permisos' => ['proveedores']]);
        $compras = User::factory()->create(['rol_id' => $rol->id]);
        $this->actingAs($compras)->get('/admin/proveedores')->assertOk();
        $this->actingAs($compras)->get('/admin/ordenes/create')->assertOk()->assertSee('Bordados Mérida');
    }

    public function test_con_dropbox_los_archivos_van_a_la_carpeta_del_proveedor(): void
    {
        config(['vandu.dropbox.simulado' => true, 'vandu.dropbox.carpeta' => 'PruebaProveedores']);
        Dropbox::usar(null);
        File::deleteDirectory(DropboxSimulado::base() . '/PruebaProveedores');
        try {
            $o = $this->orden();
            $this->post("/admin/ordenes/{$o->id}/enviar")->assertRedirect();
            $this->app['auth']->forgetGuards();
            $this->post($this->bordados->portal_url . "/orden/{$o->id}/archivos", ['tipo' => 'factura', 'archivos' => [UploadedFile::fake()->create('F-9.pdf', 10, 'application/pdf')]])->assertRedirect();

            $a = $o->archivos()->firstOrFail();
            $this->assertSame('dropbox', $a->origen);
            $this->assertSame('/PruebaProveedores/Proveedores/Bordados Mérida/OC-0001 Uniformes Hotel Xcanatún/Facturas/F-9.pdf', $a->ruta);

            // Borrar la orden borra sus archivos
            $this->actingAs($this->admin)->delete("/admin/ordenes/{$o->id}")->assertRedirect();
            $this->assertDatabaseCount('orden_compra_archivos', 0);
        } finally {
            File::deleteDirectory(DropboxSimulado::base() . '/PruebaProveedores');
        }
    }

    public function test_las_paginas_saben_cuando_hay_novedades_para_actualizarse_solas(): void
    {
        $o = $this->orden();
        $this->post("/admin/ordenes/{$o->id}/enviar")->assertRedirect();
        $admin = $this->get("/admin/ordenes/{$o->id}")->assertOk()->assertSee('Hay novedades en esta página');
        $v0 = $this->getJson("/admin/ordenes/{$o->id}/version")->assertOk()->json('v');

        $this->app['auth']->forgetGuards();
        $portal = $this->bordados->portal_url;
        $this->get("$portal/orden/{$o->id}")->assertOk()->assertSee(json_encode(route('proveedor.version', [$this->bordados->portal_token, 'orden' => $o->id])), false);
        $lista0 = $this->getJson("$portal/version")->assertOk()->json('v');
        $vistaAntes = $o->fresh()->vista_at;

        // Preguntar no cambia nada ni cuenta como visita
        $this->assertSame($v0, $this->getJson("$portal/version?orden={$o->id}")->json('v'));
        $this->assertEquals($vistaAntes, $o->fresh()->vista_at);

        // El proveedor comenta → cambia la huella de la orden y la de su lista
        $this->post("$portal/orden/{$o->id}/comentar", ['texto' => 'Listo el ponchado.'])->assertRedirect();
        $this->assertNotSame($v0, $this->getJson("$portal/version?orden={$o->id}")->json('v'));
        $this->assertNotSame($lista0, $this->getJson("$portal/version")->json('v'));

        // Cambio de estado desde el panel también
        $v1 = $this->actingAs($this->admin)->getJson("/admin/ordenes/{$o->id}/version")->json('v');
        $this->patch("/admin/ordenes/{$o->id}/estado", ['estado' => 'en_proceso']);
        $this->assertNotSame($v1, $this->getJson("/admin/ordenes/{$o->id}/version")->json('v'));

        // Otro proveedor no puede preguntar por esta orden
        $this->app['auth']->forgetGuards();
        $otro = Proveedor::create(['empresa' => 'Imprenta Sol'])->portal_url;
        $this->getJson("$otro/version?orden={$o->id}")->assertNotFound();
    }

    public function test_mis_archivos_para_producir_van_desde_la_orden_y_salen_primero(): void
    {
        // Se adjuntan al crear la orden
        $this->actingAs($this->admin)->post('/admin/ordenes', $this->datosOrden(['tipo' => 'produccion', 'archivos' => [
            UploadedFile::fake()->create('lona-3x2.pdf', 200, 'application/pdf'),
            UploadedFile::fake()->createWithContent('logo-hotel.dst', 'DST'),
        ]]))->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, 'con 2 archivos'));
        $o = OrdenCompra::latest('id')->firstOrFail();
        $this->assertSame(['produccion', 'produccion'], $o->archivos()->pluck('tipo')->all());
        $this->assertSame(['agencia', 'agencia'], $o->archivos()->pluck('autor')->all());

        // Y al editar se agregan más (referencia)
        $this->put("/admin/ordenes/{$o->id}", $this->datosOrden(['tipo' => 'diseno', 'archivos' => [UploadedFile::fake()->createWithContent('mockup.jpg', 'JPG')]]))->assertRedirect();
        $this->assertSame(3, $o->archivos()->count());
        $this->assertSame(['lona-3x2.pdf', 'logo-hotel.dst', 'mockup.jpg'], $o->fresh()->adjuntos->pluck('nombre')->all(), 'Producción primero');
        $this->post('/admin/ordenes', $this->datosOrden(['tipo' => 'produccion', 'archivos' => [UploadedFile::fake()->createWithContent('x.exe', 'MZ')]]))->assertSessionHasErrors();

        $admin = $this->get("/admin/ordenes/{$o->id}")->assertOk()->getContent();
        $this->assertLessThan(strpos($admin, 'Qué se pidió'), strpos($admin, 'Archivos para el proveedor'), 'Arriba de los conceptos');
        $this->get("/admin/ordenes/{$o->id}/archivos.zip")->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertStringStartsWith('%PDF', \App\Support\OrdenCompraPdf::generar($o->fresh())->output());
        $this->assertStringContainsString('lona-3x2.pdf', view('ordenes.pdf', ['o' => $o->fresh()->load('conceptos', 'proveedor')])->render());

        $this->post("/admin/ordenes/{$o->id}/enviar")->assertRedirect();
        $this->app['auth']->forgetGuards();
        $portal = $this->bordados->portal_url;

        $this->get($portal)->assertOk()->assertSee('3 archivos para descargar');
        $html = $this->get("$portal/orden/{$o->id}")->assertOk()->getContent();
        $this->assertStringContainsString('Archivos para producir', $html);
        $this->assertStringContainsString('Descargar todos', $html);
        $this->assertLessThan(strpos($html, 'Camisa bordada'), strpos($html, 'lona-3x2.pdf'), 'Los archivos van antes que los conceptos');

        $zip = $this->get("$portal/orden/{$o->id}/archivos.zip")->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'z');
        file_put_contents($tmp, $zip->streamedContent());
        $z = new \ZipArchive();
        $z->open($tmp);
        $nombres = [];
        for ($i = 0; $i < $z->numFiles; $i++) $nombres[] = $z->getNameIndex($i);
        sort($nombres);
        $this->assertSame(['logo-hotel.dst', 'lona-3x2.pdf', 'mockup.jpg'], $nombres);
        @unlink($tmp);

        // El proveedor no puede subir "para producción" ni borrar los nuestros
        $this->post("$portal/orden/{$o->id}/archivos", ['tipo' => 'produccion', 'archivos' => [UploadedFile::fake()->createWithContent('a.pdf', '%PDF')]])->assertSessionHasErrors('tipo');
        $this->delete("$portal/orden/{$o->id}/archivos/" . $o->archivos()->first()->id)->assertNotFound();
    }
}
