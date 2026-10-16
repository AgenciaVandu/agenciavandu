<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\DisenoDocumento;
use App\Support\PresupuestoPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cada negocio elige el diseño de sus cotizaciones: plantilla, color, letra, papel y hoja membretada */
class DisenoDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create();
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['plantilla' => 'clasica', 'color' => '#242424', 'letra' => 'geist', 'papel' => 'a4', 'sup' => 45, 'inf' => 25, 'izq' => 18, 'der' => 18, 'en_todas' => 1, 'con_encabezado' => 0];
    }

    private function cotizacion(): Presupuesto
    {
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Hotel X']);
        $p = Presupuesto::nuevaPara($c);
        $p->save();
        $p->conceptos()->create(['titulo' => 'Sitio web', 'descripcion' => 'Cinco secciones', 'cantidad' => 1, 'precio' => 20000, 'orden' => 0]);
        return $p->fresh('conceptos');
    }

    public function test_pagina_de_diseno_y_pdf_de_muestra_sin_cotizaciones(): void
    {
        $this->actingAs($this->admin)->get('/admin/negocio/cotizaciones')->assertOk()->assertSee('Hoja membretada')->assertSee('Elegante');
        $r = $this->get('/admin/negocio/cotizaciones/muestra.pdf')->assertOk();
        $this->assertStringStartsWith('application/pdf', $r->headers->get('Content-Type'));
    }

    public function test_cada_plantilla_genera_su_pdf(): void
    {
        $p = $this->cotizacion();
        foreach (array_keys(DisenoDocumento::PLANTILLAS) as $pl) {
            $this->actingAs($this->admin)->post('/admin/negocio/cotizaciones', $this->datos(['plantilla' => $pl, 'color' => '#1F3A5F', 'letra' => $pl === 'elegante' ? 'times' : 'helvetica', 'papel' => 'carta']))->assertRedirect();
            Cuentas::activar(1);
            $this->assertSame($pl, DisenoDocumento::de()['plantilla']);
            $pdf = PresupuestoPdf::generar($p)->output();
            $this->assertStringStartsWith('%PDF', $pdf, "Plantilla $pl");
        }
        $this->assertSame('#1f3a5f', DisenoDocumento::de()['color']);
        $this->assertSame('carta', DisenoDocumento::de()['papel']);
    }

    public function test_hoja_membretada_en_el_pdf_y_sus_franjas_en_el_enlace(): void
    {
        $p = $this->cotizacion();
        $this->actingAs($this->admin)->post('/admin/negocio/cotizaciones', $this->datos([
            'plantilla' => 'minimal', 'color' => '#0F5E4B',
            'membrete' => UploadedFile::fake()->image('membrete.png', 2480, 3508),
        ]))->assertRedirect('/admin/negocio/cotizaciones');

        Cuentas::activar(1);
        $d = DisenoDocumento::de();
        $this->assertNotNull($d['membrete']);
        Storage::disk('local')->assertExists($d['membrete']);
        [$w] = getimagesize(Storage::disk('local')->path($d['membrete']));
        $this->assertSame(1800, $w);
        Storage::disk('local')->assertExists($d['franja_sup']);   // la imagen de prueba no es blanca: tiene franjas

        $this->assertStringStartsWith('%PDF', PresupuestoPdf::generar($p)->output());

        // En el enlace del cliente va la franja de arriba en lugar del encabezado
        auth()->logout();
        $this->get('/cotizacion/' . $p->token)->assertOk()->assertSee('class="franja"', false)->assertSee('--hdr: #0f5e4b', false)->assertDontSee('<header class="hdr">', false);

        // Quitarla borra los archivos
        $this->actingAs($this->admin)->post('/admin/negocio/cotizaciones', $this->datos(['quitar_membrete' => 1]))->assertRedirect();
        Storage::disk('local')->assertMissing($d['membrete']);
        Cuentas::activar(1);
        $this->assertNull(DisenoDocumento::de()['membrete']);
    }

    public function test_valida_colores_y_archivos(): void
    {
        $this->actingAs($this->admin)->post('/admin/negocio/cotizaciones', $this->datos(['color' => 'rojo']))->assertSessionHasErrors('color');
        $this->post('/admin/negocio/cotizaciones', $this->datos(['membrete' => UploadedFile::fake()->create('x.docx', 10)]))->assertSessionHasErrors('membrete');
    }

    public function test_sin_permiso_de_configuracion_no_se_cambia(): void
    {
        $rol = \App\Models\Rol::create(['nombre' => 'Vendedor', 'permisos' => ['cotizaciones']]);
        $v = User::factory()->create(['rol_id' => $rol->id]);
        $this->actingAs($v)->get('/admin/negocio/cotizaciones')->assertForbidden();
    }
}
