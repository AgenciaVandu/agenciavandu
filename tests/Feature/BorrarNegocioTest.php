<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Ejemplo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Eliminar un negocio de prueba con todo lo suyo, y que su correo quede libre para volver a darlo de alta */
class BorrarNegocioTest extends TestCase
{
    use RefreshDatabase;

    public function test_eliminar_negocio_libera_el_correo_y_no_toca_a_los_demas(): void
    {
        Storage::fake('local');
        $alvar = User::factory()->create(['name' => 'Alvar', 'plataforma' => true]);
        $nuestro = Cliente::create(['nombre' => 'Cliente de la agencia']);

        // Un gimnasio de prueba con sus ejemplos (clases, paquetes, atletas, reservaciones) y un archivo
        [$ax, $dueno] = PlataformaController::crearCuenta(['nombre' => 'AX TEST', 'giro' => 'fitness', 'dueno_nombre' => 'Pruebas', 'dueno_email' => 'pruebas@ax.mx', 'ejemplos' => true]);
        Cuentas::como($ax, fn () => Ejemplo::cargar());
        Storage::disk('local')->put("atletas/{$ax->id}/foto.webp", 'x');
        $this->assertGreaterThan(0, DB::table('reservaciones')->where('cuenta_id', $ax->id)->count());
        Cuentas::activar(1);

        $this->actingAs($alvar)->get('/admin/plataforma')->assertOk()->assertSee('Suspender o eliminar')->assertSee('pruebas@ax.mx');
        $this->delete("/admin/plataforma/{$ax->id}", ['confirmar' => 'otro nombre', '_cuenta' => $ax->id])->assertSessionHasErrors('confirmar');
        $this->assertNotNull(Cuenta::find($ax->id));

        $this->delete("/admin/plataforma/{$ax->id}", ['confirmar' => 'ax test'])->assertRedirect(route('admin.plataforma'))
            ->assertSessionHas('ok', fn ($m) => str_contains($m, 'pruebas@ax.mx'));
        $this->assertNull(Cuenta::find($ax->id));
        foreach (\App\Support\Plataforma\BorrarNegocio::tablasConCuenta() as $t) {
            $this->assertSame(0, DB::table($t)->where('cuenta_id', $ax->id)->count(), "Quedaron filas en $t");
        }
        Storage::disk('local')->assertMissing("atletas/{$ax->id}/foto.webp");
        $this->assertModelExists($nuestro);
        $this->assertModelExists($alvar);

        // El mismo correo se puede volver a dar de alta desde cero
        $this->post('/admin/plataforma', ['nombre' => 'AX Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Pruebas', 'dueno_email' => 'pruebas@ax.mx', 'enviar_correo' => 0, 'ejemplos' => 0])
            ->assertRedirect('/admin/plataforma');
        $this->assertSame(1, User::sinCuenta()->where('email', 'pruebas@ax.mx')->count());

        // La principal y la propia no se eliminan
        $this->delete('/admin/plataforma/1', ['confirmar' => 'Agencia Vandu'])->assertStatus(422);
    }

    public function test_el_gimnasio_no_ve_archivos_ni_dropbox(): void
    {
        [$ax, $dueno] = PlataformaController::crearCuenta(['nombre' => 'Box', 'giro' => 'fitness', 'dueno_nombre' => 'Pepe', 'dueno_email' => 'pepe@box.mx']);
        $html = $this->actingAs($dueno)->get('/admin/clases')->assertOk()->getContent();
        $this->assertStringNotContainsString(route('admin.dropbox'), $html);
        $this->assertStringNotContainsString(route('admin.archivos'), $html);
        $this->get('/admin/archivos')->assertForbidden();
        $this->assertTrue(Cuenta::find(1)->tiene('archivos'), 'La agencia sigue con Archivos');
    }
}
