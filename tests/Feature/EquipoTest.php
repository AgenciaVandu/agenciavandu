<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\PushSuscripcion;
use App\Models\Rol;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxSimulado;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Equipo: roles por sección, invitaciones, tareas y entregas a Dropbox */
class EquipoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vandu.dropbox.simulado' => true, 'vandu.dropbox.carpeta' => 'PruebaEquipo']);
        Dropbox::usar(null);
        File::deleteDirectory(DropboxSimulado::base() . '/PruebaEquipo');
        $this->admin = User::factory()->create(['name' => 'Alvar Buenfil']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(DropboxSimulado::base() . '/PruebaEquipo');
        parent::tearDown();
    }

    private function persona(string $rol, array $extra = []): User
    {
        return User::factory()->create($extra + ['rol_id' => Rol::where('nombre', $rol)->value('id')]);
    }

    private function tarea(User $para, array $extra = []): Tarea
    {
        return Tarea::create($extra + ['titulo' => 'Cargar contenido Tatich Maya', 'asignada_a' => $para->id, 'creada_por' => $this->admin->id, 'carpeta' => '/PruebaEquipo/Clientes/Tatich Maya/Fotos']);
    }

    public function test_todas_las_rutas_del_panel_tienen_seccion(): void
    {
        $sueltas = collect(Route::getRoutes())->map->getName()->filter(fn ($n) => $n && str_starts_with($n, 'admin.'))
            ->filter(fn ($n) => Permisos::deRuta($n) === null)->values()->all();
        $this->assertSame([], $sueltas, 'Rutas sin sección: ' . implode(', ', $sueltas));
    }

    public function test_fotografo_solo_ve_sus_tareas(): void
    {
        $foto = $this->persona('Fotógrafo', ['name' => 'Luis Pech', 'puesto' => 'Fotógrafo']);
        $otro = $this->persona('Diseñador');
        $suya = $this->tarea($foto);
        $ajena = $this->tarea($otro, ['titulo' => 'Diseñar posts de octubre']);
        $this->actingAs($foto);

        $this->get('/admin')->assertRedirect(route('admin.tareas.index'));
        foreach (['/admin/finanzas', '/admin/presupuestos', '/admin/clientes', '/admin/usuarios', '/admin/archivos', '/admin/dropbox'] as $u) {
            $this->get($u)->assertForbidden();
        }
        $this->get('/admin/tareas')->assertOk()->assertSee('Mis tareas')->assertSee('Cargar contenido Tatich Maya')->assertDontSee('Diseñar posts de octubre')
            ->assertDontSee('Nueva cotización')->assertDontSee('Finanzas');
        $this->get('/admin/tareas?ver=todas')->assertDontSee('Diseñar posts de octubre');
        $this->get("/admin/tareas/{$suya->id}")->assertOk();
        $this->get("/admin/tareas/{$ajena->id}")->assertForbidden();
        $this->get('/admin/tareas/create')->assertForbidden();
        $this->post('/admin/tareas', ['titulo' => 'X'])->assertForbidden();
        $this->get('/admin/notificaciones')->assertOk()->assertDontSee('Un cliente abre una cotización')->assertSee('Te asignan una tarea');
    }

    public function test_project_crea_y_asigna_tareas_y_puede_editar_roles_solo_el_admin(): void
    {
        Mail::fake();
        $pm = $this->persona('Project');
        $foto = $this->persona('Fotógrafo');
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Tatich Maya']);
        $this->actingAs($pm);
        $this->get('/admin')->assertRedirect(route('admin.proyectos.index'));
        $this->get('/admin/tareas/create')->assertOk();
        $this->post('/admin/tareas', [
            'titulo' => 'Cargar contenido Tatich Maya', 'cliente_id' => $c->id, 'asignada_a' => $foto->id,
            'fecha_limite' => now()->addDays(2)->toDateString(), 'carpeta' => '/PruebaEquipo/Clientes/Tatich Maya/Octubre', 'correo' => 1,
        ])->assertRedirect();
        $t = Tarea::firstOrFail();
        $this->assertSame($foto->id, $t->asignada_a);
        $this->assertDirectoryExists(DropboxSimulado::base() . '/PruebaEquipo/Clientes/Tatich Maya/Octubre');
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo($foto->email) && str_contains($m->asunto, 'Nueva tarea'));
        $this->put('/admin/roles/' . Rol::where('nombre', 'Project')->value('id'), ['nombre' => 'Project', 'permisos' => ['finanzas']])->assertForbidden();
    }

    public function test_invitacion_crea_contrasena_y_entra(): void
    {
        Mail::fake();
        $this->actingAs($this->admin);
        $this->post('/admin/usuarios', ['name' => 'Karla Dzul', 'email' => 'karla@ejemplo.com', 'puesto' => 'Diseñadora', 'telefono' => '9991234567', 'rol_id' => Rol::where('nombre', 'Diseñador')->value('id'), 'enviar_correo' => 1])
            ->assertRedirect()->assertSessionHas('enlace');
        $karla = User::where('email', 'karla@ejemplo.com')->firstOrFail();
        $this->assertTrue($karla->pendiente);
        $this->assertSame('Diseñadora', $karla->puesto);
        $url = session('enlace')['url'];
        $this->assertStringContainsString('wa.me/529991234567', session('enlace')['whatsapp']);
        Mail::assertSent(CorreoVandu::class, fn ($m) => $m->hasTo('karla@ejemplo.com') && $m->url === $url);

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('¡Hola, Karla!');
        $this->post($url, ['name' => 'Karla Dzul', 'password' => 'corta', 'password_confirmation' => 'corta'])->assertSessionHasErrors('password');
        $this->post($url, ['name' => 'Karla Dzul', 'password' => 'secreta123', 'password_confirmation' => 'secreta123'])->assertRedirect(route('admin.tareas.index'));
        $this->assertAuthenticatedAs($karla->fresh());
        $this->assertFalse($karla->fresh()->pendiente);
        $this->get($url)->assertStatus(410); // ya se usó

        auth()->logout();
        $this->post('/admin/login', ['email' => 'karla@ejemplo.com', 'password' => 'secreta123'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_desactivado_no_entra_y_queda_un_super_admin(): void
    {
        $foto = $this->persona('Fotógrafo', ['email' => 'luis@ejemplo.com', 'password' => 'secreta123']);
        $this->actingAs($this->admin);
        $this->put("/admin/usuarios/{$foto->id}", ['name' => $foto->name, 'email' => $foto->email, 'rol_id' => $foto->rol_id])->assertRedirect(); // sin "activo" = desactivado
        $this->assertFalse($foto->fresh()->activo);
        // El único super admin no se puede quitar el rol ni eliminar
        $this->put("/admin/usuarios/{$this->admin->id}", ['name' => 'A', 'email' => $this->admin->email, 'rol_id' => Rol::where('nombre', 'Project')->value('id'), 'activo' => 1])->assertSessionHasErrors('rol_id');
        $this->delete("/admin/usuarios/{$this->admin->id}")->assertSessionHasErrors('usuario');

        auth()->logout();
        $this->post('/admin/login', ['email' => 'luis@ejemplo.com', 'password' => 'secreta123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        // Si ya tenía sesión abierta, lo saca
        $this->actingAs($foto->fresh())->get('/admin/tareas')->assertRedirect(route('login'));
    }

    public function test_roles_editables_dan_y_quitan_secciones(): void
    {
        $dis = $this->persona('Diseñador');
        $rol = $dis->rol;
        $this->actingAs($this->admin)->put("/admin/roles/{$rol->id}", ['nombre' => 'Diseño', 'permisos' => ['redes', 'archivos']])->assertRedirect();
        $this->assertSame(['redes', 'archivos'], $rol->fresh()->permisos);
        $this->actingAs($dis->fresh())->get('/admin/redes')->assertOk();
        $this->get('/admin/finanzas')->assertForbidden();
        // No se borra un rol que alguien tiene
        $this->actingAs($this->admin)->delete("/admin/roles/{$rol->id}")->assertSessionHasErrors('rol');
    }

    public function test_entrega_en_partes_a_la_carpeta_y_flujo_de_revision(): void
    {
        $foto = $this->persona('Fotógrafo', ['name' => 'Luis Pech']);
        $t = $this->tarea($foto);
        $this->actingAs($foto);
        $url = "/admin/tareas/{$t->id}/subir";

        // Un archivo chico: una sola parte
        $this->post($url, ['accion' => 'iniciar', 'nombre' => 'portada.jpg', 'parte' => UploadedFile::fake()->createWithContent('blob', 'JPGDATA')])->assertOk()->assertJson(['ok' => true]);
        // Un video "grande": en tres partes
        $s = $this->post($url, ['accion' => 'iniciar', 'parte' => UploadedFile::fake()->createWithContent('blob', 'AAAA')])->assertOk()->json('sesion');
        $this->post($url, ['accion' => 'agregar', 'sesion' => $s, 'offset' => 4, 'parte' => UploadedFile::fake()->createWithContent('blob', 'BBBB')])->assertOk();
        $this->post($url, ['accion' => 'agregar', 'sesion' => $s, 'offset' => 3, 'parte' => UploadedFile::fake()->createWithContent('blob', 'XX')])->assertStatus(502); // offset incorrecto
        $this->post($url, ['accion' => 'terminar', 'sesion' => $s, 'offset' => 8, 'nombre' => 'video final.mp4', 'parte' => UploadedFile::fake()->createWithContent('blob', 'CC')])->assertOk();

        $dir = DropboxSimulado::base() . '/PruebaEquipo/Clientes/Tatich Maya/Fotos';
        $this->assertSame('AAAABBBBCC', file_get_contents("$dir/video final.mp4"));
        $this->assertFileExists("$dir/portada.jpg");
        $this->assertSame(2, $t->archivos()->count());
        $this->assertSame('en_curso', $t->fresh()->estado);
        $this->get("/admin/tareas/{$t->id}")->assertOk()->assertSee('video final.mp4')->assertSee('Entregar para revisión');

        // Entrega; no puede darla por terminada
        $this->patch("/admin/tareas/{$t->id}/estado", ['estado' => 'revision', 'nota' => 'Van 40 fotos'])->assertRedirect();
        $this->assertSame('revision', $t->fresh()->estado);
        $this->patch("/admin/tareas/{$t->id}/estado", ['estado' => 'terminada'])->assertForbidden();

        // Otra persona no puede subir ni ver los archivos
        $otro = $this->persona('Diseñador');
        $this->actingAs($otro)->post($url, ['accion' => 'iniciar', 'nombre' => 'x.jpg', 'parte' => UploadedFile::fake()->createWithContent('blob', 'X')])->assertForbidden();
        $id = $t->archivos()->first()->dropbox_id;
        $this->get("/admin/tareas/{$t->id}/archivos/$id")->assertForbidden();

        // Quien gestiona la termina
        $this->actingAs($this->admin)->patch("/admin/tareas/{$t->id}/estado", ['estado' => 'terminada'])->assertRedirect();
        $this->assertNotNull($t->fresh()->terminada_at);
        $this->actingAs($foto)->post($url, ['accion' => 'iniciar', 'nombre' => 'tarde.jpg', 'parte' => UploadedFile::fake()->createWithContent('blob', 'X')])->assertStatus(422);
    }

    public function test_avisos_del_negocio_no_llegan_a_quien_no_ve_esa_seccion(): void
    {
        $foto = $this->persona('Fotógrafo');
        foreach ([$this->admin, $foto] as $u) {
            PushSuscripcion::create(['user_id' => $u->id, 'endpoint' => 'https://fcm.googleapis.com/x' . $u->id, 'endpoint_hash' => hash('sha256', 'x' . $u->id), 'p256dh' => 'x', 'auth' => 'y']);
        }
        $quien = PushSuscripcion::with('user.rol')->get()->filter(fn ($s) => \App\Support\Push\Notificar::puedeRecibir($s->user, 'cotizacion_abierta'))->pluck('user_id')->all();
        $this->assertSame([$this->admin->id], $quien);
        $this->assertTrue(\App\Support\Push\Notificar::puedeRecibir($foto, 'tarea_asignada'));
    }

    private function proyectoConEtapas(): \App\Models\Proyecto
    {
        $c = Cliente::create(['nombre' => 'Ana', 'empresa' => 'Tatich Maya']);
        $pr = \App\Models\Proyecto::create(['cliente_id' => $c->id, 'nombre' => 'Sesión de fotos', 'tipo' => 'audiovisual', 'estado' => 'activo', 'monto_total' => 1000]);
        $pr->etapas()->create(['clave' => 'levantamiento', 'nombre' => 'Grabación', 'orden' => 0, 'es_fecha' => true, 'estado' => 'en_curso']);
        $pr->etapas()->create(['clave' => 'entrega', 'nombre' => 'Entrega', 'orden' => 1, 'es_fecha' => true]);
        return $pr;
    }

    private function subirA(Tarea $t, string $nombre, string $contenido = 'JPG'): void
    {
        $this->post("/admin/tareas/{$t->id}/subir", ['accion' => 'iniciar', 'nombre' => $nombre, 'parte' => UploadedFile::fake()->createWithContent('blob', $contenido)])->assertOk();
    }

    public function test_aprobar_incluye_lo_elegido_en_la_galeria_del_proyecto_y_avanza_la_etapa(): void
    {
        $pr = $this->proyectoConEtapas();
        $etapa = $pr->etapas()->where('clave', 'levantamiento')->first();
        $foto = $this->persona('Fotógrafo', ['name' => 'Luis Pech']);
        $t = $this->tarea($foto, ['proyecto_id' => $pr->id, 'cliente_id' => $pr->cliente_id, 'destino' => 'galeria', 'etapa_id' => $etapa->id, 'completar_etapa' => true]);

        $this->actingAs($foto);
        foreach (['alberca.jpg', 'lobby.jpg', 'movida.jpg'] as $n) $this->subirA($t, $n);
        $this->patch("/admin/tareas/{$t->id}/estado", ['estado' => 'revision'])->assertRedirect();
        $this->post("/admin/tareas/{$t->id}/aprobar", [])->assertForbidden(); // solo quien gestiona aprueba

        $this->actingAs($this->admin);
        $this->get("/admin/tareas/{$t->id}")->assertOk()->assertSee('Revisa la entrega')->assertSee('Pedir ajustes');
        $ids = $t->archivos()->whereIn('nombre', ['alberca.jpg', 'lobby.jpg'])->pluck('dropbox_id')->all();
        $this->post("/admin/tareas/{$t->id}/aprobar", ['ids' => $ids, 'publicar' => 1, 'destino' => 'galeria', 'etapa_id' => $etapa->id, 'completar_etapa' => 1])
            ->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, '2 archivos pasaron a la galería') && str_contains($m, 'quedó completada'));

        $this->assertSame('terminada', $t->fresh()->estado);
        $this->assertSame($this->admin->id, $t->fresh()->aprobada_por);
        $this->assertSame('completada', $etapa->fresh()->estado);
        $galeria = $pr->archivos()->where('grupo', 'galeria')->pluck('nombre')->sort()->values()->all();
        $this->assertSame(['alberca.jpg', 'lobby.jpg'], $galeria);
        $this->assertTrue($pr->archivos()->first()->visible);
        $base = DropboxSimulado::base() . \App\Support\ArchivosProyecto::carpetaGaleria($pr->fresh());
        $this->assertFileExists("$base/Cargar contenido Tatich Maya/alberca.jpg"); // en la sección de esa entrega
        $this->assertFileExists(DropboxSimulado::base() . '/PruebaEquipo/Clientes/Tatich Maya/Fotos/movida.jpg'); // la no elegida se queda
        $this->assertSame(2, $t->archivos()->whereNotNull('proyecto_archivo_id')->count());
        $this->get("/admin/tareas/{$t->id}")->assertSee('Ya en el proyecto');
    }

    public function test_pedir_ajustes_no_termina_y_abre_otra_vuelta(): void
    {
        $pr = $this->proyectoConEtapas();
        $foto = $this->persona('Fotógrafo');
        $t = $this->tarea($foto, ['proyecto_id' => $pr->id, 'destino' => 'galeria']);
        $this->actingAs($foto);
        $this->subirA($t, 'uno.jpg');
        $this->patch("/admin/tareas/{$t->id}/estado", ['estado' => 'revision']);

        $this->actingAs($this->admin)->patch("/admin/tareas/{$t->id}/estado", ['estado' => 'en_curso', 'nota' => 'Faltan las de la alberca'])->assertRedirect();
        $t->refresh();
        $this->assertSame('en_curso', $t->estado);
        $this->assertSame(2, $t->ronda);
        $this->assertSame('Faltan las de la alberca', $t->comentarios()->latest('id')->value('texto'));
        $this->assertSame(0, $pr->archivos()->count());

        $this->actingAs($foto);
        $this->subirA($t, 'dos.jpg');
        $this->assertSame(2, $t->archivos()->where('nombre', 'dos.jpg')->value('ronda'));
    }

    public function test_si_la_tarea_es_mia_la_apruebo_directo_a_documentos_de_una_etapa(): void
    {
        $pr = $this->proyectoConEtapas();
        $etapa = $pr->etapas()->where('clave', 'entrega')->first();
        $t = $this->tarea($this->admin, ['proyecto_id' => $pr->id]); // sin destino definido al crearla
        $this->actingAs($this->admin);
        $this->subirA($t, 'logo.pdf', '%PDF');
        $this->get("/admin/tareas/{$t->id}")->assertSee('¿Ya quedó? Apruébala')->assertDontSee('Entregar para revisión');
        $id = $t->archivos()->value('dropbox_id');
        $this->post("/admin/tareas/{$t->id}/aprobar", ['ids' => [$id], 'destino' => 'documento', 'etapa_id' => $etapa->id])->assertRedirect();
        $a = $pr->archivos()->first();
        $this->assertSame('documento', $a->grupo);
        $this->assertSame($etapa->id, $a->etapa_id);
        $this->assertSame('terminada', $t->fresh()->estado);
        $this->assertSame('pendiente', $etapa->fresh()->estado); // no se pidió completarla
    }

    public function test_whatsapp_del_proyecto_manda_el_enlace_del_proyecto(): void
    {
        $pr = $this->proyectoConEtapas();
        $pr->cliente->update(['telefono' => '9991234567']);
        $html = $this->actingAs($this->admin)->get("/admin/proyectos/{$pr->id}")->assertOk()->getContent();
        preg_match('#https://wa\.me/529991234567\?text=([^"]+)#', $html, $m);
        $this->assertNotEmpty($m, 'No hay botón de WhatsApp');
        $this->assertStringContainsString($pr->url_publica, rawurldecode(html_entity_decode($m[1])));
    }

    public function test_cada_entrega_queda_en_su_seccion_y_el_cliente_ve_la_mas_nueva_arriba(): void
    {
        $pr = $this->proyectoConEtapas();
        $this->actingAs($this->admin);
        // Ya había 2 fotos sueltas en la galería (antes de las secciones)
        $dbx = Dropbox::cliente();
        foreach (['vieja-1.jpg', 'vieja-2.jpg'] as $n) {
            $m = $dbx->subirContenido('JPG', \App\Support\ArchivosProyecto::carpetaGaleria($pr) . "/$n");
            \App\Support\ArchivosProyecto::registrarDropbox($pr, $m, 'galeria');
        }
        $this->assertSame(2, $pr->archivos()->whereNull('seccion_id')->count());

        // Primera tarea aprobada → sección "Primera entrega"; lo viejo pasa a "Entrega principal"
        $t1 = $this->tarea($this->admin, ['proyecto_id' => $pr->id, 'destino' => 'galeria', 'titulo' => 'Primera entrega', 'carpeta' => '/PruebaEquipo/Tareas/Uno']);
        $this->subirA($t1, 'a.jpg');
        $this->post("/admin/tareas/{$t1->id}/aprobar", ['ids' => $t1->archivos()->pluck('dropbox_id')->all(), 'destino' => 'galeria', 'seccion' => 'nueva', 'seccion_nombre' => 'Primera entrega'])->assertRedirect();
        $this->assertSame(['Primera entrega', 'Entrega principal'], $pr->secciones()->pluck('nombre')->all());
        $this->assertSame(0, $pr->archivos()->whereNull('seccion_id')->count());
        $gal = DropboxSimulado::base() . \App\Support\ArchivosProyecto::carpetaGaleria($pr->fresh());
        $this->assertFileExists("$gal/Entrega principal/vieja-1.jpg");
        $this->assertFileExists("$gal/Primera entrega/a.jpg");

        // Fotos adicionales: otra tarea, otra sección, va hasta arriba
        $t2 = $this->tarea($this->admin, ['proyecto_id' => $pr->id, 'destino' => 'galeria', 'titulo' => 'Fotos adicionales', 'carpeta' => '/PruebaEquipo/Tareas/Dos']);
        $this->subirA($t2, 'b.jpg');
        $this->post("/admin/tareas/{$t2->id}/aprobar", ['ids' => $t2->archivos()->pluck('dropbox_id')->all(), 'destino' => 'galeria'])->assertRedirect();
        $this->assertSame(['Fotos adicionales', 'Primera entrega', 'Entrega principal'], $pr->secciones()->pluck('nombre')->all());

        auth()->logout();
        $html = $this->get($pr->fresh()->url_entrega)->assertOk()->getContent();
        $this->assertTrue(strpos($html, 'Fotos adicionales') < strpos($html, 'Primera entrega') && strpos($html, 'Primera entrega') < strpos($html, 'Entrega principal'));
        $this->assertStringContainsString('Descargar esta sección', $html);

        // Renombrar, reordenar y mover entre secciones desde el proyecto
        $this->actingAs($this->admin);
        $adic = $pr->secciones()->where('nombre', 'Fotos adicionales')->first();
        $this->patch("/admin/proyectos/{$pr->id}/secciones/{$adic->id}", ['nombre' => 'Extras de la sesión'])->assertRedirect();
        $this->assertFileExists("$gal/Extras de la sesión/b.jpg");
        $this->patch("/admin/proyectos/{$pr->id}/secciones/{$adic->id}", ['mover' => 'abajo'])->assertRedirect();
        $this->assertSame(['Primera entrega', 'Extras de la sesión', 'Entrega principal'], $pr->secciones()->pluck('nombre')->all());
        $foto = $pr->archivos()->where('nombre', 'vieja-2.jpg')->first();
        $this->patch("/admin/proyectos/{$pr->id}/archivos/{$foto->id}/seccion", ['seccion_id' => $adic->id])->assertRedirect();
        $this->assertSame($adic->id, $foto->fresh()->seccion_id);
        $this->assertFileExists("$gal/Extras de la sesión/vieja-2.jpg");
        // Ocultar al cliente conserva la sección
        $this->patch("/admin/proyectos/{$pr->id}/archivos/{$foto->id}", ['visible' => 0])->assertRedirect();
        $this->assertFileExists(DropboxSimulado::base() . \App\Support\ArchivosProyecto::carpetaOculta($pr->fresh()) . '/Extras de la sesión/vieja-2.jpg');
        $this->get("/admin/proyectos/{$pr->id}")->assertOk()->assertSee('el cliente la ve primero');
    }
}
