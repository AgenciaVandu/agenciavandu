<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Support\Cuentas;
use App\Support\IconoApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Cada negocio puede poner su propio ícono a la app (panel instalado y portal del atleta) */
class IconoAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_subir_ver_en_manifiesto_y_quitar(): void
    {
        Storage::fake('local');
        [$gym, $dueno] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura', 'dueno_email' => 'laura@axion.mx']);
        Cuentas::activar($gym);
        $this->actingAs($dueno);

        // Sin ícono propio: el de la plataforma
        $this->assertSame(route('app.icono', 'icono-192.png'), IconoApp::url($gym, 'icono-192.png'));

        $this->post(route('admin.negocio.update'), ['nombre' => 'Axion Fit', 'icono' => UploadedFile::fake()->image('isotipo.png', 600, 400), 'icono_fondo' => '#1A103D'])
            ->assertSessionHasNoErrors();
        $gym->refresh();
        $this->assertTrue(IconoApp::tiene($gym));
        $this->assertSame('#1A103D', $gym->ajuste('app.fondo'));
        foreach (array_keys(IconoApp::TAMANOS) as $archivo) Storage::disk('local')->assertExists("cuentas/{$gym->id}/app/$archivo");
        $this->assertSame([180, 180], array_slice(getimagesize(Storage::disk('local')->path("cuentas/{$gym->id}/app/apple-touch-icon.png")), 0, 2));

        // El manifiesto del panel y el del portal apuntan al ícono del negocio, y se sirve
        $url = IconoApp::url($gym, 'icono-512.png');
        $this->assertStringContainsString('c=' . $gym->id, $url);
        $this->get(route('app.manifiesto'))->assertOk()->assertJsonFragment(['src' => $url]);
        $this->get(route('atleta.manifiesto', ['n' => $gym->slug]))->assertOk()->assertJsonFragment(['src' => $url]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('admin.negocio'))->assertOk()->assertSee('Ícono de la app');

        // Un archivo que no es imagen no pasa
        $this->post(route('admin.negocio.update'), ['nombre' => 'Axion Fit', 'icono' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('icono');

        $this->post(route('admin.negocio.update'), ['nombre' => 'Axion Fit', 'quitar_icono' => 1])->assertSessionHasNoErrors();
        $this->assertFalse(IconoApp::tiene($gym->fresh()));
        Storage::disk('local')->assertMissing("cuentas/{$gym->id}/app/icono-512.png");
    }
}
