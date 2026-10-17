<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use App\Models\Rol;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\ImportarProductos;
use App\Support\Inventario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Catálogo con existencias: importar de Excel, cotizar desde el catálogo, descontar al aceptar y página pública por producto */
class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $vendedor;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Cuenta::whereKey(1)->update(['giro' => 'comercio']);
        Cuentas::activar(1);
        $this->admin = User::factory()->create(['name' => 'Laura Dueña']);
        $rol = Rol::create(['nombre' => 'Vendedor', 'permisos' => ['cotizaciones', 'clientes']]);
        $this->vendedor = User::factory()->create(['name' => 'Pedro Canul', 'rol_id' => $rol->id, 'telefono' => '999 123 4567']);
        $this->cliente = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Oficinas del Sureste']);
    }

    private function producto(array $extra = []): Producto
    {
        $p = Producto::create($extra + ['nombre' => 'Silla ejecutiva Milán', 'clave' => 'SIL-001', 'precio' => 3000, 'costo' => 1800, 'unidad' => 'pieza']);
        if (! array_key_exists('existencia', $extra)) Inventario::mover($p, 'entrada', 10, 'Inicial');
        return $p->fresh();
    }

    private function cotizacion(array $conceptos, array $extra = []): array
    {
        return $extra + [
            'cliente_id' => $this->cliente->id, 'cliente_nombre' => 'Ana', 'cliente_empresa' => 'Oficinas del Sureste', 'fecha' => '2026-10-16',
            'titulo' => 'Mobiliario', 'tipo' => array_key_first(config('vandu.proyectos')), 'emisor_nombre' => 'Laura', 'modo_iva' => 'mas_iva', 'iva_porcentaje' => 16,
            'vigente_hasta' => now()->addDays(10)->format('Y-m-d\TH:i'), 'estado' => 'borrador', 'conceptos' => $conceptos,
        ];
    }

    public function test_alta_de_producto_con_foto_existencia_inicial_y_enlace_automatico(): void
    {
        $this->actingAs($this->admin)->post('/admin/productos', [
            'nombre' => 'Escritorio en L 120 cm', 'clave' => 'ESC-120', 'categoria' => 'Escritorios', 'unidad' => 'pieza',
            'precio' => 5890, 'costo' => 3650, 'controla_existencia' => 1, 'existencia_inicial' => 5, 'minimo' => 2, 'publico' => 1,
            'foto' => UploadedFile::fake()->image('escritorio.jpg', 2400, 1800),
        ])->assertRedirect();

        $p = Producto::firstOrFail();
        $this->assertSame('escritorio-en-l-120-cm', $p->slug);
        $this->assertSame('Escritorios', $p->categoria->nombre);
        $this->assertEquals(5, $p->existencia);
        $this->assertSame('entrada', $p->movimientos()->first()->tipo);
        $this->assertStringEndsWith('.webp', $p->foto);
        [$w] = getimagesize(Storage::disk('local')->path($p->foto));
        $this->assertSame(1600, $w);   // reducida
        $this->assertTrue(Storage::disk('local')->exists(dirname($p->foto) . '/min-' . basename($p->foto)));

        // Otro con el mismo nombre no choca: lleva su clave
        $otro = Producto::create(['nombre' => 'Escritorio en L 120 cm', 'clave' => 'ESC-120B', 'precio' => 1]);
        $this->assertSame('escritorio-en-l-120-cm-esc-120b', $otro->slug);
    }

    public function test_si_cambia_el_nombre_el_enlace_viejo_redirige(): void
    {
        $p = $this->producto();
        $viejo = $p->slug;
        $p->update(['nombre' => 'Silla ejecutiva Milán negra']);

        $this->assertSame('silla-ejecutiva-milan-negra', $p->fresh()->slug);
        $this->get('/catalogo/' . $viejo . '?v=abc')->assertRedirect('/catalogo/silla-ejecutiva-milan-negra?v=abc')->assertStatus(301);
        $this->get('/catalogo/silla-ejecutiva-milan-negra')->assertOk()->assertSee('Silla ejecutiva Milán negra');
    }

    public function test_movimientos_de_entrada_salida_y_conteo(): void
    {
        $p = $this->producto();
        $this->actingAs($this->admin);
        $this->post("/admin/productos/{$p->id}/movimiento", ['tipo' => 'entrada', 'cantidad' => 5, 'nota' => 'Factura 123']);
        $this->post("/admin/productos/{$p->id}/movimiento", ['tipo' => 'salida', 'cantidad' => 2, 'nota' => 'Merma']);
        $this->assertEquals(13, $p->fresh()->existencia);

        $this->post("/admin/productos/{$p->id}/movimiento", ['tipo' => 'ajuste', 'cantidad' => 11]);
        $p->refresh();
        $this->assertEquals(11, $p->existencia);
        $ajuste = $p->movimientos()->first();
        $this->assertSame('ajuste', $ajuste->tipo);
        $this->assertEquals(-2, $ajuste->cantidad);
        $this->assertSame($this->admin->id, $ajuste->user_id);
    }

    public function test_cotizacion_aceptada_descuenta_y_si_se_cancela_regresa(): void
    {
        $p = $this->producto();
        $this->actingAs($this->admin)->post('/admin/presupuestos', $this->cotizacion([
            ['producto_id' => $p->id, 'titulo' => $p->nombre, 'cantidad' => 3, 'precio' => 3000],
            ['titulo' => 'Instalación', 'cantidad' => 1, 'precio' => 500],
        ]))->assertRedirect();
        $cot = Presupuesto::latest('id')->firstOrFail();
        $this->assertEquals(10, $p->fresh()->existencia, 'Un borrador no descuenta');

        $cot->update(['estado' => 'aceptada']);
        $this->assertEquals(7, $p->fresh()->existencia);
        Inventario::sincronizarCotizacion($cot->fresh());   // no descuenta dos veces
        $this->assertEquals(7, $p->fresh()->existencia);

        // Se edita aceptada: de 3 a 5 piezas, solo sale la diferencia
        $this->put("/admin/presupuestos/{$cot->id}", $this->cotizacion([
            ['producto_id' => $p->id, 'titulo' => $p->nombre, 'cantidad' => 5, 'precio' => 3000],
        ], ['estado' => 'aceptada']))->assertRedirect();
        $this->assertEquals(5, $p->fresh()->existencia);

        $cot->fresh()->update(['estado' => 'cancelada']);
        $this->assertEquals(10, $p->fresh()->existencia);
        $this->assertSame(['venta', 'venta', 'devolucion'], ProductoMovimiento::where('presupuesto_id', $cot->id)->orderBy('id')->pluck('tipo')->all());
    }

    public function test_el_vendedor_consulta_el_catalogo_sin_costos_y_no_lo_administra(): void
    {
        $p = $this->producto();
        $this->actingAs($this->vendedor);

        $this->get('/admin/productos')->assertOk()->assertSee('Silla ejecutiva Milán')->assertDontSee('1,800')->assertDontSee('Inventario a costo');
        $this->get("/admin/productos/{$p->id}")->assertOk()->assertDontSee('Mover existencias')->assertDontSee('margen');
        $this->get('/admin/productos/create')->assertForbidden();
        $this->get('/admin/productos/importar')->assertForbidden();
        $this->post("/admin/productos/{$p->id}/movimiento", ['tipo' => 'entrada', 'cantidad' => 99])->assertForbidden();

        $json = $this->getJson('/admin/productos/buscar?q=silla')->assertOk()->json();
        $this->assertSame('Silla ejecutiva Milán', $json[0]['nombre']);
        $this->assertArrayNotHasKey('costo', $json[0]);

        // En el editor no hay costeo, y aunque mande costos, se guarda el costo real del producto
        $this->get('/admin/presupuestos/create')->assertOk()->assertDontSee('Calcular precio con costos')->assertSee('Elegir del catálogo');
        $this->post('/admin/presupuestos', $this->cotizacion([[
            'producto_id' => $p->id, 'titulo' => $p->nombre, 'cantidad' => 2, 'precio' => 3000,
            'costos' => [['clave' => 'proveedor', 'nombre' => 'Proveedor', 'modo' => 'unidad', 'clase' => 'tercero', 'monto' => 1]],
        ]]))->assertRedirect();
        $c = Presupuesto::latest('id')->first()->conceptos->first();
        $this->assertSame($p->id, $c->producto_id);
        $this->assertEquals([['clave' => 'producto', 'nombre' => 'Costo del producto', 'modo' => 'unidad', 'clase' => 'tercero', 'monto' => 1800.0]], $c->costos);
    }

    public function test_el_dueno_ve_costos_y_margen_en_el_buscador(): void
    {
        $this->producto();
        $json = $this->actingAs($this->admin)->getJson('/admin/productos/buscar?q=SIL')->json();
        $this->assertEquals(1800, $json[0]['costo']);
        $this->get('/admin/productos')->assertSee('margen 40%');
    }

    public function test_importar_csv_con_columnas_propias_y_recordarlas(): void
    {
        $this->producto();   // SIL-001 ya existe con 10 piezas
        $csv = "Lista de precios octubre\n"
            . "Código;Descripción;Familia;P. Venta;Stock\n"
            . "SIL-001;Silla ejecutiva Milán;Sillas;\"3,712.00\";4\n"
            . "ESC-120;Escritorio en L 120 cm;Escritorios;6832.40;7\n"
            . ";;;;\n"
            . "ESC-120;Repetido;Escritorios;1;1\n";
        $archivo = UploadedFile::fake()->createWithContent('catalogo.csv', mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'));

        $r = $this->actingAs($this->admin)->post('/admin/productos/importar', ['archivo' => $archivo])->assertOk();
        $mapa = $r->viewData('mapa');
        $this->assertSame(['clave' => 0, 'nombre' => 1, 'categoria' => 2, 'descripcion' => null, 'unidad' => null, 'precio' => 3, 'costo' => null, 'existencia' => 4, 'minimo' => null], $mapa);

        $this->post('/admin/productos/importar/aplicar', [
            'token' => $r->viewData('token'), 'mapa' => $mapa, 'iva_incluido' => 1, 'existencias' => 'reemplazar',
        ])->assertRedirect('/admin/productos')->assertSessionHas('ok', fn ($m) => str_contains($m, '1 producto nuevo y 1 actualizado'));

        $silla = Producto::where('clave', 'SIL-001')->first();
        $this->assertEquals(3200, $silla->precio);         // 3,712 con IVA → 3,200
        $this->assertEquals(4, $silla->existencia);        // conteo del archivo
        $this->assertSame('importacion', $silla->movimientos()->first()->tipo);
        $esc = Producto::where('clave', 'ESC-120')->first();
        $this->assertSame('Escritorio en L 120 cm', $esc->nombre);
        $this->assertEquals(5890, $esc->precio);
        $this->assertSame('Escritorios', $esc->categoria->nombre);
        $this->assertEquals(7, $esc->existencia);

        // La próxima vez, con otro orden de columnas, se reconoce por el título que se eligió
        $guardado = Cuentas::actual()->fresh()->ajuste('catalogo.importacion');
        $this->assertSame('P. Venta', $guardado['columnas']['precio']);
        $sug = ImportarProductos::sugerir(['Stock', 'P. Venta', 'Código', 'Descripción'], $guardado['columnas']);
        $this->assertSame(['clave' => 2, 'nombre' => 3, 'precio' => 1, 'existencia' => 0], array_intersect_key($sug, array_flip(['clave', 'nombre', 'precio', 'existencia'])));
    }

    public function test_importar_xlsx_sumando_mercancia(): void
    {
        $p = $this->producto();
        $ruta = $this->xlsx([['Clave', 'Producto', 'Precio', 'Existencia'], ['SIL-001', 'Silla ejecutiva Milán', 3100, 6], ['MES-01', 'Mesa de juntas', 12500.5, 2]]);
        $r = $this->actingAs($this->admin)->post('/admin/productos/importar', ['archivo' => new UploadedFile($ruta, 'catalogo.xlsx', null, null, true)])->assertOk();
        $this->post('/admin/productos/importar/aplicar', ['token' => $r->viewData('token'), 'mapa' => $r->viewData('mapa'), 'iva_incluido' => 0, 'existencias' => 'sumar'])->assertRedirect();

        $this->assertEquals(16, $p->fresh()->existencia);
        $this->assertEquals(3100, $p->fresh()->precio);
        $this->assertEquals(12500.5, Producto::where('clave', 'MES-01')->value('precio'));
    }

    public function test_numeros_en_formatos_de_excel(): void
    {
        $this->assertSame(1234.5, ImportarProductos::numero('$1,234.50'));
        $this->assertSame(1234.5, ImportarProductos::numero('1.234,50'));
        $this->assertSame(12.5, ImportarProductos::numero('12,5'));
        $this->assertSame(1234.0, ImportarProductos::numero('1,234'));
        $this->assertSame(12.0, ImportarProductos::numero('12 pzas'));
        $this->assertNull(ImportarProductos::numero(''));
    }

    public function test_pagina_publica_con_precio_iva_y_whatsapp_del_vendedor(): void
    {
        $p = $this->producto(['descripcion' => 'Respaldo de malla y base cromada.']);
        $codigo = $this->vendedor->codigo_vendedor;

        $this->get('/catalogo/' . $p->slug . '?v=' . $codigo)->assertOk()
            ->assertSee('$3,480.00')                    // con IVA
            ->assertSee('$3,000.00 más IVA')
            ->assertSee('wa.me/529991234567', false)
            ->assertSee('Te atiende Pedro Canul')
            ->assertSee('"@type":"Product"', false)
            ->assertDontSee('1,800');

        // Sin precio: invita a pedirlo
        $p->update(['mostrar_precio' => false]);
        $this->get('/catalogo/' . $p->slug)->assertOk()->assertDontSee('$3,480.00')->assertSee('Pedir precio por WhatsApp');

        // Interno: solo el equipo lo ve (vista previa)
        $p->update(['publico' => false]);
        $this->get('/catalogo/' . $p->slug)->assertNotFound();
        $this->actingAs($this->admin)->get('/catalogo/' . $p->slug)->assertOk()->assertSee('Vista previa');
    }

    public function test_foto_publica_solo_con_su_nombre(): void
    {
        $this->actingAs($this->admin)->post('/admin/productos', ['nombre' => 'Lámpara', 'precio' => 100, 'publico' => 1, 'foto' => UploadedFile::fake()->image('l.png', 300, 300)]);
        $p = Producto::firstOrFail();
        $this->get($p->miniatura_url)->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get("/catalogo/foto/{$p->id}/otra.webp")->assertNotFound();
    }

    public function test_sin_el_modulo_no_hay_productos(): void
    {
        Cuenta::whereKey(1)->update(['giro' => 'agencia']);
        Cuentas::activar(1);
        $p = Producto::create(['nombre' => 'Algo', 'precio' => 1]);

        $this->actingAs($this->admin)->get('/admin/productos')->assertNotFound();
        $this->get('/admin')->assertOk()->assertDontSee('bi-box-seam', false);
        $this->get('/catalogo/' . $p->slug)->assertNotFound();
    }

    /** Un .xlsx mínimo, como lo guarda Excel (textos en sharedStrings) */
    private function xlsx(array $filas): string
    {
        $textos = [];
        $sheet = '';
        foreach ($filas as $r => $fila) {
            $sheet .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($fila) as $c => $v) {
                $ref = chr(65 + $c) . ($r + 1);
                if (is_string($v)) {
                    $i = array_search($v, $textos, true);
                    if ($i === false) { $textos[] = $v; $i = count($textos) - 1; }
                    $sheet .= '<c r="' . $ref . '" t="s"><v>' . $i . '</v></c>';
                } else {
                    $sheet .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
                }
            }
            $sheet .= '</row>';
        }
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($ruta, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Catálogo" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/hoja.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . implode('', array_map(fn ($t) => '<si><t>' . htmlspecialchars($t) . '</t></si>', $textos)) . '</sst>');
        $zip->addFromString('xl/worksheets/hoja.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheet . '</sheetData></worksheet>');
        $zip->close();
        return $ruta;
    }
}
