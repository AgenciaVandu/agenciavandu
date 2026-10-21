<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PlataformaController;
use App\Models\Clase;
use App\Models\ClaseTipo;
use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Membresia;
use App\Models\Paquete;
use App\Models\Producto;
use App\Models\Reservacion;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Agenda;
use App\Support\Fitness\Ajustes;
use App\Support\Fitness\Avisos;
use App\Support\Fitness\Membresias;
use App\Support\Fitness\Reservas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Giro Fitness: agenda con cupo, paquetes con precio, reservaciones con créditos (transaccionales),
 * QR en la entrada, portal del atleta con su propio acceso y aislamiento entre negocios.
 */
class FitnessTest extends TestCase
{
    use RefreshDatabase;

    private Cuenta $gym;
    private User $dueno;
    private ClaseTipo $engine;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-20 08:00:00', 'America/Merida'));   // martes
        [$this->gym, $this->dueno] = PlataformaController::crearCuenta(['nombre' => 'Axion Fit', 'giro' => 'fitness', 'dueno_nombre' => 'Laura Gym', 'dueno_email' => 'laura@axion.mx']);
        Cuentas::activar($this->gym);
        $this->engine = ClaseTipo::create(['nombre' => 'HYROX Engine', 'duracion' => 60, 'cupo' => 12, 'color' => '#E8590C']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function clase(string $cuando = '2026-10-21 07:00', int $cupo = 2, array $extra = []): Clase
    {
        $i = Carbon::parse($cuando, 'America/Merida');
        return Clase::create($extra + ['clase_tipo_id' => $this->engine->id, 'inicia_at' => $i->copy()->utc(), 'termina_at' => $i->copy()->addHour()->utc(), 'cupo' => $cupo]);
    }

    private function paquete(array $extra = []): Paquete
    {
        return Paquete::create($extra + ['nombre' => 'Paquete 8 clases', 'precio' => 1050, 'creditos' => 8, 'vigencia_dias' => 30]);
    }

    private function atleta(string $nombre = 'Sofía Medina', ?Paquete $p = null, array $extra = []): Cliente
    {
        $c = Cliente::create(['nombre' => $nombre, 'email' => strtolower(str_replace(' ', '', $nombre)) . '@mail.mx', 'telefono' => '9991112233']);
        if ($p !== false) Membresias::asignar($c, $p ?? $this->paquete(), $extra + ['pagado' => true]);
        return $c->fresh();
    }

    private function falla(callable $fn, string $texto): void
    {
        try {
            $fn();
            $this->fail("Se esperaba: $texto");
        } catch (ValidationException $e) {
            $this->assertStringContainsString($texto, collect($e->errors())->flatten()->first());
        }
    }

    public function test_el_giro_arranca_con_sus_roles_y_solo_ve_sus_secciones(): void
    {
        $this->assertTrue($this->gym->tiene('fitness'));
        $this->assertFalse($this->gym->tiene('cotizaciones'));
        $this->assertFalse($this->gym->tiene('proyectos'));
        $this->assertFalse($this->gym->tiene('proveedores'));
        $this->assertEqualsCanonicalizing(['Super admin', 'Gerente', 'Recepción', 'Entrenador'], Rol::pluck('nombre')->all());
        $this->assertSame('axion-fit', $this->gym->slug);

        $html = $this->actingAs($this->dueno)->get('/admin/clases')->assertOk()->getContent();
        foreach (['Agenda', 'Escanear', 'Atletas', 'Paquetes', 'Reportes', 'Reglas del gimnasio'] as $t) $this->assertStringContainsString($t, $html);
        $this->assertStringNotContainsString(route('admin.presupuestos.index'), $html);
        $this->assertStringNotContainsString(route('admin.proyectos.index'), $html);
        $this->assertStringNotContainsString(route('admin.proveedores.index'), $html);
        $this->get('/admin/presupuestos')->assertForbidden();
        $this->get('/admin/proveedores')->assertForbidden();

        // Resumen, finanzas y atletas propios del gimnasio
        $this->get('/admin')->assertOk()->assertSee('Clases hoy')->assertSee('Arranca tu gimnasio');
        $this->get('/admin/finanzas')->assertOk()->assertSee('paquetes de clases');
        $this->get('/admin/clientes')->assertOk()->assertSee('Atletas')->assertSee('Nuevo atleta');
    }

    public function test_otros_giros_no_ven_el_gimnasio(): void
    {
        Cuentas::activar(1);
        $admin = User::factory()->create();
        $this->actingAs($admin)->get('/admin/clases')->assertForbidden();
        $this->get('/admin/paquetes')->assertForbidden();
        $html = $this->get('/admin/clientes')->assertOk()->getContent();
        $this->assertStringNotContainsString('Nuevo atleta', $html);
        $this->assertStringNotContainsString(route('admin.escanear'), $html);
        $this->assertTrue(Cuentas::actual()->tiene('cotizaciones'));
    }

    public function test_paquetes_son_productos_que_el_catalogo_no_ve(): void
    {
        $this->actingAs($this->dueno)->post('/admin/paquetes', ['nombre' => 'Membresía ilimitada', 'precio' => 1650, 'ilimitado' => 1, 'vigencia_dias' => 30])->assertSessionHas('ok');
        $this->post('/admin/paquetes', ['nombre' => 'Clase de prueba', 'precio' => 150, 'creditos' => 1, 'vigencia_dias' => 7, 'es_prueba' => 1])->assertSessionHas('ok');
        $this->post('/admin/paquetes', ['nombre' => 'Sin clases', 'precio' => 100, 'vigencia_dias' => 30])->assertSessionHasErrors('creditos');

        $this->assertSame(2, Paquete::count());
        $this->assertNull(Paquete::where('nombre', 'Membresía ilimitada')->value('creditos'));
        $this->assertSame(0, Producto::count(), 'El catálogo de productos no incluye paquetes');
        $this->assertSame(['paquete'], \DB::table('productos')->distinct()->pluck('tipo')->all());
        $this->get('/admin/paquetes')->assertOk()->assertSee('Membresía ilimitada')->assertSee('Ilimitado')->assertSee('Prueba');
    }

    public function test_programar_clase_suelta_y_horario_fijo_con_cupo_del_gerente(): void
    {
        $this->actingAs($this->dueno)->post('/admin/clases', [
            'clase_tipo_id' => $this->engine->id, 'fecha' => '2026-10-22', 'hora' => '19:00', 'duracion' => 60, 'cupo' => 10,
            'entrenamiento' => json_encode([['nombre' => 'WOD', 'formato' => 'AMRAP 20', 'ejercicios' => [['nombre' => 'Wall Balls', 'cantidad' => '20']]]]),
        ])->assertRedirect();
        $c = Clase::firstOrFail();
        $this->assertSame([10, '19:00', 'WOD'], [$c->cupo, $c->inicio->format('H:i'), $c->bloques[0]['nombre']]);
        $this->assertSame('2026-10-23 01:00', $c->inicia_at->utc()->format('Y-m-d H:i'), 'Se guarda en UTC');

        // Horario fijo: lunes, miércoles y viernes a las 6:00, por 8 semanas
        $this->post('/admin/clases', ['clase_tipo_id' => $this->engine->id, 'fecha' => '2026-10-20', 'hora' => '06:00', 'duracion' => 60, 'cupo' => 16, 'repetir' => 1, 'dias' => [1, 3, 5]])
            ->assertRedirect(route('admin.clases.series'));
        $serie = \App\Models\ClaseSerie::firstOrFail();
        $this->assertSame(24, $serie->clases()->count());   // 3 por semana × 8
        $this->assertSame(0, Agenda::generar($serie), 'No duplica');
        $this->get('/admin/clases')->assertOk()->assertSee('HYROX Engine');
        $this->get('/admin/clases/horario-fijo')->assertOk()->assertSee('Lun, Mié, Vie');

        // El cupo no puede bajar de los que ya reservaron
        $a = $this->atleta();
        Reservas::reservar($c, $a);
        Reservas::reservar($c, $this->atleta('Diego Herrera'));
        $this->put("/admin/clases/{$c->id}", ['clase_tipo_id' => $this->engine->id, 'fecha' => '2026-10-22', 'hora' => '19:00', 'duracion' => 60, 'cupo' => 1])->assertSessionHasErrors('cupo');

        // Terminar el horario fijo quita las que no tienen reservaciones
        Reservas::reservar($serie->clases()->orderBy('inicia_at')->first(), $a);
        $this->delete("/admin/clases/horario-fijo/{$serie->id}")->assertSessionHas('ok', fn ($m) => str_contains($m, '1 clase'));
        $this->assertSame(1, $serie->clases()->count());
    }

    public function test_reservar_respeta_cupo_creditos_vigencia_y_horario(): void
    {
        $clase = $this->clase(cupo: 2);
        $a = $this->atleta('Sofía Medina', $this->paquete(['creditos' => 2]));
        $b = $this->atleta('Diego Herrera');
        $c = $this->atleta('Valeria Canul');

        $r = Reservas::reservar($clase, $a);
        $this->assertTrue($r->credito_usado);
        $this->assertSame(1, $a->membresia_actual->usados);
        $this->falla(fn () => Reservas::reservar($clase, $a), 'Ya tiene lugar');
        Reservas::reservar($clase, $b);
        $this->falla(fn () => Reservas::reservar($clase, $c), 'La clase está llena');

        // Choque de horario
        $otra = $this->clase('2026-10-21 07:30', 5);
        $this->falla(fn () => Reservas::reservar($otra, $a), 'Se cruza');

        // Se acaban los créditos
        Reservas::reservar($this->clase('2026-10-22 07:00', 5), $a);
        $this->falla(fn () => Reservas::reservar($this->clase('2026-10-23 07:00', 5), $a), 'Ya no le quedan clases');

        // Fuera de vigencia y sin paquete
        $this->falla(fn () => Reservas::reservar($this->clase('2026-12-01 07:00', 5), $b), 'venció');
        $sin = Cliente::create(['nombre' => 'Sin paquete']);
        $this->falla(fn () => Reservas::reservar($otra, $sin), 'No tiene un paquete vigente');

        // Tipos de clase permitidos y máximo al día
        $fuerza = ClaseTipo::create(['nombre' => 'Axion Fuerza', 'duracion' => 60, 'cupo' => 10]);
        $soloEngine = $this->atleta('Andrés Pool', $this->paquete(['tipos_clase' => [$this->engine->id], 'max_por_dia' => 1]));
        $this->falla(fn () => Reservas::reservar($this->clase('2026-10-24 09:00', 5, ['clase_tipo_id' => $fuerza->id]), $soloEngine), 'no incluye clases de Axion Fuerza');
        Reservas::reservar($this->clase('2026-10-25 07:00', 5), $soloEngine);
        $this->falla(fn () => Reservas::reservar($this->clase('2026-10-25 19:00', 5), $soloEngine), 'máximo de clases');

        // Ilimitada no descuenta
        $ili = $this->atleta('Mariana Uc', $this->paquete(['nombre' => 'Ilimitada', 'creditos' => null]));
        $r = Reservas::reservar($otra, $ili);
        $this->assertFalse($r->credito_usado);
        $this->assertSame(0, $ili->membresia_actual->usados);
    }

    public function test_cancelar_devuelve_el_credito_solo_a_tiempo(): void
    {
        Ajustes::guardar(['horas_cancelar' => 6]);
        $a = $this->atleta();
        $manana = Reservas::reservar($this->clase('2026-10-21 07:00', 5), $a);
        $pronto = Reservas::reservar($this->clase('2026-10-20 11:00', 5), $a);   // en 3 horas
        $this->assertSame(2, $a->membresia_actual->usados);

        $this->assertTrue(Reservas::cancelar($manana));
        $this->assertFalse(Reservas::cancelar($pronto));
        $this->assertSame(1, $a->fresh()->membresia_actual->usados);

        // Se puede volver a reservar el mismo lugar
        $otra = Reservas::reservar($manana->clase, $a);
        $this->assertSame($manana->id, $otra->id);
        $this->assertSame('reservada', $otra->estado);

        // Si el gimnasio cancela la clase, a todos se les devuelve
        $b = $this->atleta('Diego Herrera');
        Reservas::reservar($manana->clase, $b);
        $this->actingAs($this->dueno)->post("/admin/clases/{$manana->clase_id}/cancelar", ['motivo' => 'El coach se enfermó'])->assertSessionHas('ok', fn ($m) => str_contains($m, '2 atletas'));
        $this->assertSame([1, 0], [$a->fresh()->membresia_actual->usados, $b->fresh()->membresia_actual->usados]);
        $this->assertSame('cancelada', $manana->clase->fresh()->estado);
    }

    public function test_clase_de_prueba_una_vez_y_membresia_con_precio_en_finanzas(): void
    {
        $prueba = $this->paquete(['nombre' => 'Clase de prueba', 'creditos' => 1, 'vigencia_dias' => 7, 'precio' => 150, 'es_prueba' => true]);
        $c = Cliente::create(['nombre' => 'Rodrigo Kú', 'email' => 'Rodrigo@Mail.MX']);
        $this->assertSame('rodrigo@mail.mx', $c->email);

        $this->actingAs($this->dueno)->post("/admin/atletas/{$c->id}/membresias", ['producto_id' => $prueba->id, 'inicia_el' => '2026-10-20', 'pagado' => 1, 'metodo_pago' => 'efectivo'])
            ->assertRedirect(route('admin.clientes.show', [$c, 'ver' => 'atleta']));
        $this->post("/admin/atletas/{$c->id}/membresias", ['producto_id' => $prueba->id, 'inicia_el' => '2026-10-20'])->assertSessionHasErrors('producto_id');

        $ocho = $this->paquete();
        $this->post("/admin/atletas/{$c->id}/membresias", ['producto_id' => $ocho->id, 'inicia_el' => '2026-10-20', 'precio' => 900]);   // con descuento, sin pagar
        $m = Membresia::where('nombre', 'Paquete 8 clases')->firstOrFail();
        $this->assertSame([900.0, '2026-11-18', null], [$m->precio, $m->vence_el->toDateString(), $m->pagado_el]);

        $this->get('/admin/finanzas?periodo=mes')->assertOk()->assertSee('$1,050')->assertSee('$150')->assertSee('$900');   // vendido, cobrado, por cobrar
        $this->get("/admin/clientes/{$c->id}?ver=atleta")->assertOk()->assertSee('Paquete 8 clases')->assertSee('pendiente de pago')->assertSee('Credencial QR');

        // Marcar pagado
        $this->put("/admin/membresias/{$m->id}", ['precio' => 900, 'vence_el' => '2026-11-18', 'creditos' => 8, 'pagado' => 1, 'metodo_pago' => 'transferencia'])->assertSessionHas('ok');
        $this->assertSame('transferencia', $m->fresh()->metodo_pago);
        $this->get('/admin/finanzas?periodo=mes')->assertSee('$1,050');

        // Cancelada deja de contar
        $this->post("/admin/membresias/{$m->id}/cancelar")->assertSessionHas('ok');
        $this->assertSame(150.0, \App\Support\Fitness\Finanzas::datos('2026-10-01', '2026-10-31')['kpi']['vendido']);
    }

    public function test_entrada_con_qr_en_la_sucursal(): void
    {
        $norte = Sucursal::create(['nombre' => 'Norte']);
        $sur = Sucursal::create(['nombre' => 'Sur']);
        $recep = User::factory()->create(['rol_id' => Rol::where('nombre', 'Recepción')->value('id'), 'sucursal_id' => $norte->id]);
        $clase = $this->clase('2026-10-20 08:15', 3, ['sucursal_id' => $norte->id]);
        $a = $this->atleta();
        Reservas::reservar($clase, $a);

        $this->actingAs($recep)->get('/admin/escanear')->assertOk()->assertSee('HYROX Engine');
        $r = $this->postJson('/admin/escanear', ['codigo' => $a->qr])->assertOk()->json();
        $this->assertTrue($r['ok']);
        $this->assertSame([$a->nombre, 'HYROX Engine'], [$r['atleta']['nombre'], $r['clase']['nombre']]);
        $this->assertSame('asistio', $a->reservaciones()->first()->estado);
        $this->assertSame('repetido', $this->postJson('/admin/escanear', ['codigo' => $a->qr])->json('tipo'));

        // Sin reservación: se le ofrece la clase y se inscribe en la puerta
        $b = $this->atleta('Diego Herrera');
        $r = $this->postJson('/admin/escanear', ['codigo' => $b->qr])->json();
        $this->assertSame('sin_reserva', $r['tipo']);
        $this->assertSame($clase->id, $r['opciones'][0]['id']);
        $this->postJson('/admin/escanear/inscribir', ['cliente_id' => $b->id, 'clase_id' => $clase->id])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('asistio', $b->reservaciones()->first()->estado);
        $this->assertSame(1, $b->fresh()->membresia_actual->usados);

        // En otra sucursal no aparece esa clase; un código ajeno no sirve
        $recepSur = User::factory()->create(['rol_id' => Rol::where('nombre', 'Recepción')->value('id'), 'sucursal_id' => $sur->id]);
        $c = $this->atleta('Valeria Canul');
        Reservas::reservar($clase, $c);
        $this->actingAs($recepSur)->postJson('/admin/escanear', ['codigo' => $c->qr])->assertJsonPath('tipo', 'sin_reserva');
        $this->postJson('/admin/escanear', ['codigo' => 'FIT:' . str_repeat('x', 32)])->assertJsonPath('tipo', 'desconocido');

        // Credencial nueva: la anterior deja de servir
        $viejo = $c->qr;
        $this->actingAs($this->dueno)->post("/admin/atletas/{$c->id}/qr");
        $this->assertNotSame($viejo, $c->fresh()->qr);
        $this->actingAs($recep)->postJson('/admin/escanear', ['codigo' => $viejo])->assertJsonPath('tipo', 'desconocido');

        // El entrenador sin sucursal pasa lista desde la clase
        $coach = User::factory()->create(['rol_id' => Rol::where('nombre', 'Entrenador')->value('id')]);
        $clase->update(['coach_id' => $coach->id]);
        $this->actingAs($coach)->get("/admin/clases/{$clase->id}")->assertOk()->assertSee('Valeria Canul');
        $this->patch('/admin/reservaciones/' . $c->reservaciones()->first()->id . '/asistencia', ['estado' => 'falta'])->assertRedirect();
        $this->assertSame('falta', $c->reservaciones()->first()->estado);
        $this->get('/admin/paquetes')->assertForbidden();
        $this->get('/admin/clientes')->assertForbidden();
    }

    public function test_portal_del_atleta_con_su_propio_acceso(): void
    {
        $a = $this->atleta();
        $clase = $this->clase('2026-10-21 07:00', 5);

        // El gimnasio le manda su enlace para crear contraseña
        $this->actingAs($this->dueno)->post("/admin/atletas/{$a->id}/acceso")->assertSessionHas('acceso');
        $url = session('acceso')['url'];
        $token = basename(parse_url($url, PHP_URL_PATH));
        Auth::logout();
        $this->get("/atleta/acceso/$token")->assertOk()->assertSee('Crea tu contraseña');
        $this->post("/atleta/acceso/$token", ['password' => 'fuerza2026', 'password_confirmation' => 'fuerza2026'])->assertRedirect(route('atleta.inicio'));
        $this->assertTrue(Auth::guard('atleta')->check());
        $this->assertFalse(Auth::guard('web')->check(), 'No entra al panel');
        $this->get('/admin')->assertRedirect(route('login'));

        // Reservar y cancelar desde su portal
        $this->get('/atleta/inicio')->assertOk()->assertSee('Hola, Sofía')->assertSee('Paquete 8 clases');
        $this->get('/atleta/clases?dia=2026-10-21')->assertOk()->assertSee('HYROX Engine')->assertSee('Reservar');
        $this->post("/atleta/clases/{$clase->id}/reservar")->assertSessionHas('ok');
        $r = Reservacion::firstOrFail();
        $this->assertSame(['atleta', 1], [$r->origen, $a->fresh()->membresia_actual->usados]);
        $this->get('/atleta/credencial')->assertOk()->assertSee($a->fresh()->qr);
        $this->post("/atleta/reservaciones/{$r->id}/cancelar")->assertSessionHas('ok', fn ($m) => str_contains($m, 'regresamos'));
        $this->assertSame(0, $a->fresh()->membresia_actual->usados);

        // Salir y volver a entrar con su correo
        $this->post('/atleta/salir')->assertRedirect(route('atleta.entrar', 'axion-fit'));
        $this->get('/atleta/inicio')->assertRedirect();
        $this->post('/atleta/axion-fit', ['email' => $a->email, 'password' => 'mala'])->assertSessionHasErrors('email');
        $this->post('/atleta/axion-fit', ['email' => mb_strtoupper($a->email), 'password' => 'fuerza2026'])->assertSessionHasNoErrors()->assertRedirect(route('atleta.inicio'));
        $this->assertTrue(Auth::guard('atleta')->check());
        $this->get('/atleta/perfil')->assertOk()->assertSee('Avisos en este celular');
    }

    public function test_aislamiento_entre_gimnasios(): void
    {
        $a = $this->atleta();
        $a->forceFill(['password' => 'fuerza2026'])->save();
        $mia = $this->clase('2026-10-21 07:00', 5);

        [$otro, $duenoOtro] = PlataformaController::crearCuenta(['nombre' => 'Box Norte', 'giro' => 'fitness', 'dueno_nombre' => 'Pepe', 'dueno_email' => 'pepe@box.mx']);
        Cuentas::activar($otro);
        $tipo = ClaseTipo::create(['nombre' => 'WOD', 'duracion' => 60, 'cupo' => 10]);
        $ajena = Clase::create(['clase_tipo_id' => $tipo->id, 'inicia_at' => now()->addDay(), 'termina_at' => now()->addDay()->addHour(), 'cupo' => 10]);

        // El otro gimnasio no ve mis atletas ni mis clases
        $this->actingAs($duenoOtro)->get("/admin/clases/{$mia->id}")->assertNotFound();
        $this->get("/admin/clientes/{$a->id}")->assertNotFound();
        $this->postJson('/admin/escanear', ['codigo' => $a->qr])->assertJsonPath('tipo', 'desconocido');

        // Mi atleta no entra al portal del otro gimnasio ni reserva sus clases
        Auth::logout();
        $this->post('/atleta/box-norte', ['email' => $a->email, 'password' => 'fuerza2026'])->assertSessionHasErrors('email');
        $this->post('/atleta/axion-fit', ['email' => $a->email, 'password' => 'fuerza2026'])->assertSessionHasNoErrors()->assertRedirect(route('atleta.inicio'));
        $this->post("/atleta/clases/{$ajena->id}/reservar")->assertNotFound();
        $this->get('/atleta/clases?dia=2026-10-21')->assertOk()->assertDontSee('WOD');
    }

    public function test_avisos_programados_y_faltas(): void
    {
        Ajustes::guardar(['aviso_clase' => 60, 'aviso_vence' => 3]);
        $a = $this->atleta('Sofía Medina', $this->paquete(['creditos' => 2, 'vigencia_dias' => 3]));
        $r = Reservas::reservar($this->clase('2026-10-20 08:40', 5), $a);   // en 40 min
        Reservas::reservar($this->clase('2026-10-22 08:40', 5), $a);

        $n = Avisos::programados();
        $this->assertSame(['clase' => 1, 'credito' => 0, 'vence' => 1], $n);
        $this->assertNotNull($r->fresh()->aviso_at);
        $this->assertSame(['clase' => 0, 'credito' => 0, 'vence' => 0], Avisos::programados(), 'Cada aviso sale una vez');

        // Pasa la clase y nadie pasó lista: queda como falta
        Carbon::setTestNow(Carbon::parse('2026-10-20 11:00', 'America/Merida'));
        $this->assertSame(1, Reservas::cerrarFaltas());
        $this->assertSame('falta', $r->fresh()->estado);
        $this->artisan('vandu:fitness')->assertSuccessful();
    }

    public function test_paginas_del_gimnasio_y_ejemplo(): void
    {
        $this->actingAs($this->dueno)->post('/admin/clases/ejemplos')->assertRedirect(route('admin.resumen'));
        $this->assertTrue(\App\Support\Fitness\Ejemplo::hay());
        $this->assertSame(10, Cliente::where('ejemplo', true)->count());
        $this->assertGreaterThan(0, Reservacion::count());

        $clase = Clase::orderBy('inicia_at')->first();
        $atleta = Cliente::where('ejemplo', true)->first();
        foreach (['/admin', '/admin/clases', "/admin/clases/{$clase->id}", "/admin/clases/{$clase->id}/editar", '/admin/clases/nueva', '/admin/clases/horario-fijo',
                  '/admin/tipos-de-clase', '/admin/ejercicios', '/admin/paquetes', '/admin/clientes', '/admin/clientes?ver=por_vencer', "/admin/clientes/{$atleta->id}",
                  "/admin/atletas/{$atleta->id}/credencial", '/admin/escanear', '/admin/gimnasio/reportes?periodo=todo', '/admin/gimnasio/reglas', '/admin/finanzas?periodo=todo',
                  '/admin/sucursales'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/finanzas/exportar?periodo=todo')->assertOk();
        $this->get('/admin')->assertSee('gimnasio de ejemplo');

        $this->post('/admin/gimnasio/reglas', ['horas_cancelar' => 12, 'reservar_hasta' => 30, 'dias_adelante' => 7, 'checkin_antes' => 20, 'checkin_despues' => 10, 'aviso_clase' => 90, 'aviso_vence' => 2, 'max_por_dia' => 1, 'atleta_reserva' => 1])->assertSessionHas('ok');
        $this->assertSame(12, Ajustes::get('horas_cancelar'));

        $this->delete('/admin/clases/ejemplos')->assertRedirect();
        $this->assertFalse(\App\Support\Fitness\Ejemplo::hay());
        $this->assertSame(0, Cliente::where('ejemplo', true)->count());
    }
}
