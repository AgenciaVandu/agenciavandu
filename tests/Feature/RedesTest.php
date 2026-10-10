<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\RedesPost;
use App\Models\User;
use App\Support\Redes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Módulo de redes: calendario, editor, revisión del cliente con código y aprobación */
class RedesTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $c;
    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vandu.dropbox.simulado' => false, 'vandu.dropbox.app_key' => null]);
        \App\Support\Dropbox\Dropbox::usar(null);
        Storage::fake('local');
        Mail::fake();
        $this->u = User::factory()->create(['name' => 'Alvar']);
        $this->c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel X', 'email' => 'ana@hotel.mx', 'telefono' => '9991234567']);
    }

    private function nuevoPost(array $extra = []): RedesPost
    {
        return RedesPost::create($extra + ['cliente_id' => $this->c->id, 'redes' => ['instagram', 'facebook'], 'formato' => 'post',
            'fecha' => now()->addDays(2), 'texto' => 'Fin de semana en #Mérida', 'estado' => 'borrador']);
    }

    public function test_la_agencia_arma_el_calendario_y_edita_un_post(): void
    {
        $this->actingAs($this->u);
        $this->post('/admin/redes', ['cliente_id' => $this->c->id])->assertRedirect(route('admin.redes.cliente', $this->c));
        $this->assertNotNull($this->c->fresh()->redes_token);

        $r = $this->post('/admin/redes/clientes/' . $this->c->id . '/posts', ['fecha' => now(config('vandu.zona_horaria'))->addDay()->format('Y-m-d')]);
        $p = RedesPost::sole();
        $r->assertRedirect(route('admin.redes.post', $p));

        $this->postJson('/admin/redes/posts/' . $p->id . '/medios', ['archivos' => [
            UploadedFile::fake()->image('a.jpg', 1080, 1350), UploadedFile::fake()->image('b.jpg', 1080, 1350),
        ]])->assertOk()->assertJsonCount(2, 'medios')->assertJsonPath('medios.0.ancho', 1080);

        $this->putJson('/admin/redes/posts/' . $p->id, ['titulo' => 'Promo', 'redes' => ['instagram', 'tiktok', 'linkedin'], 'formato' => 'carrusel',
            'fecha' => now(config('vandu.zona_horaria'))->addDay()->format('Y-m-d\T18:30'), 'texto' => 'Hola #Mérida', 'estado' => 'borrador'])
            ->assertOk()->assertJsonPath('post.formato', 'carrusel')->assertJsonPath('post.redes.2', 'linkedin');

        $this->get('/admin/redes/clientes/' . $this->c->id)->assertOk()->assertSee('Promo');
        $this->get('/admin/redes/clientes/' . $this->c->id . '?vista=feed')->assertOk()->assertSee('publicaciones');
        $this->get('/admin/redes/clientes/' . $this->c->id . '?vista=lista')->assertOk()->assertSee('Promo');
        $this->get('/admin/redes/posts/' . $p->id)->assertOk()->assertSee('Dónde se publica');
        $this->get('/admin/redes')->assertOk()->assertSee('Hotel X');
    }

    public function test_el_cliente_solo_ve_lo_enviado_y_aprueba_con_codigo(): void
    {
        $borrador = $this->nuevoPost(['titulo' => 'Secreto', 'fecha' => now()->addMonths(2)]); // otro mes: no se envía
        $p = $this->nuevoPost(['titulo' => 'Para revisar']);
        $this->actingAs($this->u)->post('/admin/redes/clientes/' . $this->c->id . '/revision', ['mes' => $p->fecha_local->format('Y-m'), 'canal' => 'correo'])->assertSessionHasNoErrors();
        $codigo = null;
        Mail::assertSent(CorreoVandu::class, function ($m) use (&$codigo) { $codigo = $m->codigo['formateado'] ?? null; return $m->hasTo('ana@hotel.mx'); });
        $this->assertSame('revision', $p->fresh()->estado);
        $this->assertSame('borrador', $borrador->fresh()->estado);
        $borrador->update(['fecha' => $p->fecha]); // aunque esté en el mismo mes, un borrador no se muestra
        auth()->logout();

        $url = Redes::urlCliente($this->c, $p->fecha_local->format('Y-m'));
        $this->get($url)->assertOk()->assertSee('Para revisar')->assertDontSee('Secreto');

        $tok = $this->c->fresh()->redes_token;
        $this->postJson("/redes/$tok/posts/{$p->id}", ['accion' => 'aprobar'])->assertStatus(401)->assertJson(['verificar' => true]);
        $this->postJson("/redes/$tok/verificar", ['nombre' => 'Ana', 'codigo' => '000000'])->assertStatus(422);
        $this->postJson("/redes/$tok/verificar", ['nombre' => 'Ana', 'codigo' => $codigo])->assertOk();

        $this->postJson("/redes/$tok/posts/{$p->id}", ['accion' => 'cambios'])->assertStatus(422);
        $this->postJson("/redes/$tok/posts/{$p->id}", ['accion' => 'cambios', 'texto' => 'Cambia la foto por la de la alberca'])->assertOk()->assertJsonPath('post.estado', 'cambios');
        $this->postJson("/redes/$tok/posts/{$p->id}", ['accion' => 'aprobar'])->assertOk()->assertJsonPath('post.estado', 'aprobado');
        $this->postJson("/redes/$tok/posts/{$p->id}", ['accion' => 'cambios', 'texto' => 'Otra cosa'])->assertStatus(409);
        $this->postJson("/redes/$tok/posts/{$p->id}", ['accion' => 'comentario', 'texto' => '¡Me encantó!'])->assertOk();
        $this->postJson("/redes/$tok/posts/{$borrador->id}", ['accion' => 'aprobar'])->assertNotFound();

        $p->refresh();
        $this->assertSame('Ana', $p->aprobado_por);
        $this->assertSame(['revision', 'cambios', 'aprobado', 'comentario'], $p->comentarios->pluck('tipo')->all());
        $this->actingAs($this->u)->get('/admin/redes/posts/' . $p->id)->assertSee('Cambia la foto por la de la alberca');
    }

    public function test_aprobar_todo_el_mes_y_mover_en_el_feed(): void
    {
        $a = $this->nuevoPost(['estado' => 'revision', 'fecha' => now()->startOfMonth()->addDays(3)]);
        $b = $this->nuevoPost(['estado' => 'revision', 'fecha' => now()->startOfMonth()->addDays(5)]);
        $fa = $a->fecha;
        $this->actingAs($this->u)->postJson('/admin/redes/clientes/' . $this->c->id . '/mover', ['a' => $a->id, 'b' => $b->id])->assertOk();
        $this->assertEquals($fa, $b->fresh()->fecha);

        auth()->logout();
        $tok = Redes::token($this->c);
        $k = Redes::generarCodigo($this->c, 'manual');
        $this->postJson("/redes/$tok/verificar", ['nombre' => 'Ana', 'codigo' => $k['codigo']])->assertOk();
        $this->postJson("/redes/$tok/aprobar-todo", ['mes' => now(config('vandu.zona_horaria'))->format('Y-m')])->assertOk()->assertJson(['aprobados' => 2]);
        $this->assertSame(2, RedesPost::where('estado', 'aprobado')->count());
    }

    public function test_el_codigo_vence(): void
    {
        $k = Redes::generarCodigo($this->c, 'manual');
        $this->travel(25)->hours();
        $tok = Redes::token($this->c);
        $this->postJson("/redes/$tok/verificar", ['nombre' => 'Ana', 'codigo' => $k['codigo']])->assertStatus(422);
    }

    public function test_quitar_al_cliente_del_servicio_de_redes(): void
    {
        $p = $this->nuevoPost(['estado' => 'revision']);
        $this->actingAs($this->u)->postJson('/admin/redes/posts/' . $p->id . '/medios', ['archivos' => [UploadedFile::fake()->image('a.jpg', 800, 800)]])->assertOk();
        $ruta = $p->medios()->first()->ruta;
        $tok = Redes::token($this->c);

        $this->delete('/admin/redes/clientes/' . $this->c->id)->assertRedirect(route('admin.redes'));

        $this->assertSame(0, RedesPost::count());
        $this->assertNull($this->c->fresh()->redes_token);
        $this->assertNotNull(Cliente::find($this->c->id)); // el cliente sigue
        Storage::disk('local')->assertMissing($ruta);
        auth()->logout();
        $this->get('/redes/' . $tok)->assertNotFound();
    }

    public function test_cliente_de_prueba_se_crea_y_se_borra(): void
    {
        $this->artisan('vandu:demo-redes')->expectsOutputToContain('Cliente de prueba creado')->assertSuccessful();
        $demo = Cliente::where('email', 'demo-redes@agenciavandu.com')->sole();
        $this->assertSame(13, RedesPost::where('cliente_id', $demo->id)->count());
        $this->assertGreaterThan(0, \App\Models\RedesMedio::count());
        $this->get(Redes::urlCliente($demo))->assertOk()->assertSee('Café Itzá');

        $this->artisan('vandu:demo-redes')->assertSuccessful(); // volver a correrlo no duplica
        $this->assertSame(1, Cliente::where('email', 'demo-redes@agenciavandu.com')->count());

        $this->artisan('vandu:demo-redes --borrar')->expectsOutputToContain('borrado')->assertSuccessful();
        $this->assertSame(0, Cliente::where('email', 'demo-redes@agenciavandu.com')->count());
        $this->assertSame(0, RedesPost::count());
    }
}
