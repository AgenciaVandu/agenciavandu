<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Correo;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\ProyectoSolicitud;
use App\Models\User;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxSimulado;
use App\Support\SubidaCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Le pedimos archivos al cliente y él los sube (por partes) desde la página de su proyecto */
class SolicitudesArchivosTest extends TestCase
{
    use RefreshDatabase;

    private Proyecto $p;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['vandu.dropbox.simulado' => false]);
        Dropbox::usar(null);
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X', 'email' => 'ana@hotel.mx', 'telefono' => '9991234567']);
        $pre = Presupuesto::nuevaPara($c)->fill(['estado' => 'aceptada']);
        $pre->save();
        $this->p = Proyecto::create(['cliente_id' => $c->id, 'presupuesto_id' => $pre->id, 'nombre' => 'Video corporativo', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 11600]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(DropboxSimulado::base() . '/PruebaSolicitudes');
        Dropbox::usar(null);
        parent::tearDown();
    }

    private function solicitud(array $extra = []): ProyectoSolicitud
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('admin.proyectos.solicitudes.store', $this->p), $extra + ['titulo' => 'Logotipo en vector', 'instrucciones' => 'En AI o SVG', 'fecha_limite' => now()->addDays(3)->toDateString()])
            ->assertRedirect(route('admin.proyectos.show', $this->p) . '#archivos-cliente')->assertSessionHas('ok');
        return $this->p->solicitudes()->latest('id')->first();
    }

    private function subir(array $datos, string $contenido)
    {
        return $this->postJson(route('proyecto.subir', $this->p->token), $datos + ['parte' => UploadedFile::fake()->createWithContent('parte', $contenido)]);
    }

    /** Sube un archivo en partes de $parte bytes, como lo hace el navegador */
    private function subirPorPartes(string $nombre, string $contenido, int $parte, ?int $solicitud = null)
    {
        $base = ['nombre' => $nombre, 'tamano' => strlen($contenido), 'solicitud' => $solicitud];
        if (strlen($contenido) <= $parte) return $this->subir($base + ['accion' => 'iniciar', 'unico' => 1], $contenido);
        $sesion = $this->subir($base + ['accion' => 'iniciar'], substr($contenido, 0, $parte))->assertOk()->json('sesion');
        $offset = $parte;
        while ($offset + $parte < strlen($contenido)) {
            $this->subir($base + ['accion' => 'agregar', 'sesion' => $sesion, 'offset' => $offset], substr($contenido, $offset, $parte))->assertOk();
            $offset += $parte;
        }
        return $this->subir($base + ['accion' => 'terminar', 'sesion' => $sesion, 'offset' => $offset], substr($contenido, $offset));
    }

    public function test_el_admin_pide_archivos_y_el_cliente_ve_la_solicitud(): void
    {
        $s = $this->solicitud();
        $this->get(route('admin.proyectos.show', $this->p))->assertOk()
            ->assertSee('Archivos del cliente')->assertSee('Logotipo en vector')->assertSee('Esperando')
            ->assertSee('data-correo="solicitud_archivos"', false)->assertSee('wa.me/529991234567', false);

        $this->post(route('admin.proyectos.solicitudes.store', $this->p), ['titulo' => ''])->assertSessionHasErrors('titulo');

        auth()->logout();
        $this->get($this->p->url_publica)->assertOk()->assertSee('Necesitamos unos archivos tuyos')
            ->assertSee('Archivos que necesitamos')->assertSee('Logotipo en vector')->assertSee('En AI o SVG')
            ->assertSee('data-solicitud="' . $s->id . '"', false);
        $this->get($this->p->cliente->portal_url)->assertOk()->assertSee('Necesitamos unos archivos tuyos para avanzar');

        // Cerrada ya no se le muestra nada para subir: solo cuando el equipo pide archivos
        $this->actingAs(User::factory()->create());
        $this->patch(route('admin.proyectos.solicitudes.estado', [$this->p, $s]))->assertSessionHas('ok');
        $this->assertSame('cerrada', $s->fresh()->estado);
        auth()->logout();
        $this->get($this->p->url_publica)->assertOk()->assertDontSee('Logotipo en vector')->assertDontSee('id="enviar-archivos"', false)
            ->assertDontSee('Necesitamos unos archivos tuyos');
        $this->get($this->p->cliente->portal_url)->assertOk()->assertDontSee('#enviar-archivos', false);
    }

    public function test_el_cliente_sube_por_partes_sin_dropbox(): void
    {
        $s = $this->solicitud();
        auth()->logout();
        $contenido = str_repeat('A', 10) . str_repeat('B', 10) . 'fin';
        $r = $this->subirPorPartes('Logo final.ai', $contenido, 10, $s->id)->assertOk()->assertJsonPath('archivo.nombre', 'Logo final.ai');

        $a = $this->p->archivos()->findOrFail($r->json('archivo.id'));
        $this->assertSame(['cliente', false, $s->id, 'local'], [$a->grupo, $a->visible, $a->solicitud_id, $a->origen]);
        $this->assertSame($contenido, Storage::disk('local')->get($a->ruta));
        $this->assertNotNull($s->fresh()->recibida_at);

        // Un archivo pequeño va en una sola llamada, sin solicitud
        $this->subirPorPartes('audio.mp3', 'ID3', 10)->assertOk();
        $this->assertSame(2, $this->p->archivos()->where('grupo', 'cliente')->count());

        // El cliente no puede verlos por el enlace público; el admin sí (y se descargan)
        $this->get(route('proyecto.archivo', [$this->p->token, $a->id]))->assertNotFound();
        $this->actingAs(User::factory()->create());
        $ver = $this->get(route('admin.proyectos.archivo.ver', [$this->p, $a]))->assertOk();
        $this->assertStringContainsString('attachment', $ver->headers->get('content-disposition'));
        $this->get(route('admin.proyectos.show', $this->p))->assertSee('Logo final.ai')->assertSee('audio.mp3')->assertSee('Llegaron 1');
    }

    public function test_no_acepta_programas_ni_sesiones_ajenas(): void
    {
        $this->solicitud();
        auth()->logout();
        $this->subir(['accion' => 'iniciar', 'unico' => 1, 'nombre' => 'virus.exe'], 'MZ')->assertStatus(422);
        $this->subir(['accion' => 'iniciar', 'unico' => 1, 'nombre' => 'foto.php.jpg'], '<?php')->assertStatus(422);
        $this->subir(['accion' => 'iniciar', 'nombre' => 'gigante.zip', 'tamano' => SubidaCliente::maxBytes() + 1], 'x')->assertStatus(413);

        // Una sesión abierta en otro proyecto no se puede continuar desde este enlace
        $otro = Proyecto::create(['cliente_id' => $this->p->cliente_id, 'nombre' => 'Otro', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 0]);
        $otro->solicitudes()->create(['titulo' => 'Fotos', 'estado' => 'abierta']);
        $sesion = $this->postJson(route('proyecto.subir', $otro->token), ['accion' => 'iniciar', 'nombre' => 'a.zip', 'parte' => UploadedFile::fake()->createWithContent('parte', 'aaaa')])->assertOk()->json('sesion');
        $this->subir(['accion' => 'terminar', 'nombre' => 'a.zip', 'sesion' => $sesion, 'offset' => 4], 'bbbb')->assertNotFound();
        $this->assertSame(0, $this->p->archivos()->count());

        // Una solicitud cerrada o de otro proyecto no se usa
        $s = $this->p->solicitudes()->first();
        $this->solicitud(['titulo' => 'Fotos del equipo']);
        $s->update(['estado' => 'cerrada']);
        auth()->logout();
        $id = $this->subir(['accion' => 'iniciar', 'unico' => 1, 'nombre' => 'logo.svg', 'solicitud' => $s->id], '<svg/>')->assertOk()->json('archivo.id');
        $this->assertNull($this->p->archivos()->find($id)->solicitud_id);

        // Sin solicitudes abiertas no recibe nada
        $this->p->solicitudes()->update(['estado' => 'cerrada']);
        $this->subir(['accion' => 'iniciar', 'unico' => 1, 'nombre' => 'otro.svg'], '<svg/>')->assertStatus(422)->assertJsonPath('message', 'Por ahora no te pedimos archivos para este proyecto.');
    }

    public function test_con_dropbox_van_a_la_carpeta_del_cliente(): void
    {
        config(['vandu.dropbox.simulado' => true, 'vandu.dropbox.carpeta' => 'PruebaSolicitudes']);
        Dropbox::usar(null);
        $s = $this->solicitud(['titulo' => 'Fotos del equipo']);
        auth()->logout();

        $contenido = str_repeat('x', 25);
        $r = $this->subirPorPartes('equipo.zip', $contenido, 10, $s->id)->assertOk();
        $a = $this->p->archivos()->findOrFail($r->json('archivo.id'));
        $this->assertSame('dropbox', $a->origen);
        $this->assertStringContainsString('/Del cliente/Fotos del equipo/equipo.zip', $a->ruta);
        $this->assertSame($contenido, file_get_contents(DropboxSimulado::base() . $a->ruta));
    }

    public function test_correo_para_pedir_archivos(): void
    {
        $this->solicitud();
        $ctx = ['cliente' => $this->p->cliente, 'presupuesto' => null, 'proyecto' => $this->p];
        $v = \App\Support\Correos::variables($ctx);
        $this->assertStringContainsString('• Logotipo en vector (para el', $v['{solicitudes}']);
        $this->assertStringEndsWith('#enviar-archivos', \App\Support\Correos::enlace($ctx, 'solicitud_archivos'));
        $this->assertSame([], \App\Support\PlantillasCorreo::variablesSinValor(config('vandu.correo.plantillas.solicitud_archivos'), 'solicitud_archivos'));
    }
}
