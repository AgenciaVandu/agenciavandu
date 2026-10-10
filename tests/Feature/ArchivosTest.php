<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Vista "Archivos": navegar Dropbox desde el panel (con el Dropbox simulado) */
class ArchivosTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vandu.dropbox.simulado' => true, 'vandu.dropbox.carpeta' => 'PruebaArchivos']);
        Dropbox::usar(null);
        $this->base = DropboxSimulado::base() . '/PruebaArchivos';
        File::deleteDirectory($this->base);
        File::ensureDirectoryExists($this->base . '/Proyectos/Hotel X/Galería');
        $img = imagecreatetruecolor(40, 30);
        imagejpeg($img, $this->base . '/Proyectos/Hotel X/Galería/foto 1.jpg');
        File::put($this->base . '/Proyectos/Hotel X/Factura A1.pdf', '%PDF-1.4');
        $this->actingAs(User::factory()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lista_carpetas_y_archivos_con_migas(): void
    {
        $this->get('/admin/archivos')->assertOk()->assertSee('Archivos');

        $this->getJson('/admin/archivos/listar')->assertOk()
            ->assertJsonPath('ruta', '/PruebaArchivos')
            ->assertJsonPath('carpetas.0.nombre', 'Proyectos');

        $r = $this->getJson('/admin/archivos/listar?ruta=' . urlencode('/PruebaArchivos/Proyectos/Hotel X'))->assertOk();
        $r->assertJsonPath('carpetas.0.nombre', 'Galería')->assertJsonPath('archivos.0.tipo', 'pdf')->assertJsonPath('migas.3.nombre', 'Hotel X');

        $foto = $this->getJson('/admin/archivos/listar?ruta=' . urlencode('/PruebaArchivos/Proyectos/Hotel X/Galería'))->json('archivos.0');
        $this->assertSame('foto', $foto['tipo']);
        $this->get('/admin/archivos/miniatura?id=' . urlencode($foto['id']))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/admin/archivos/ver?id=' . urlencode($foto['id']))->assertRedirect();
        $this->get('/admin/archivos/ver?id=../../etc')->assertNotFound();
    }

    public function test_busca_por_nombre_y_dice_donde_esta(): void
    {
        $this->getJson('/admin/archivos/buscar?q=factura')->assertOk()
            ->assertJsonPath('archivos.0.nombre', 'Factura A1.pdf')
            ->assertJsonPath('archivos.0.en', 'Proyectos / Hotel X');
        $this->getJson('/admin/archivos/buscar?q=a')->assertStatus(422);
    }
}
