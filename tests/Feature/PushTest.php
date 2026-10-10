<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Notificacion;
use App\Models\Presupuesto;
use App\Models\PushSuscripcion;
use App\Models\User;
use App\Support\Push\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Notificaciones push contra un servicio push simulado (no toca la red) */
class PushTest extends TestCase
{
    use RefreshDatabase;

    private User $u;

    protected function setUp(): void
    {
        parent::setUp();
        $this->u = User::factory()->create();
    }

    private function suscripcion(array $extra = []): PushSuscripcion
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($k)['ec'];
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc' . random_int(1, 99999);
        return PushSuscripcion::create($extra + [
            'user_id' => $this->u->id, 'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => WebPush::b64("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)),
            'auth' => WebPush::b64(random_bytes(16)), 'dispositivo' => 'iPhone · Safari',
        ]);
    }

    private function cotizacion(): Presupuesto
    {
        $c = Cliente::create(['nombre' => 'Ana López', 'empresa' => 'Hotel Xcanatún']);
        $p = Presupuesto::nuevaPara($c);
        $p->estado = 'enviada';
        $p->save();
        return $p;
    }

    public function test_las_claves_se_crean_una_vez_y_se_guardan(): void
    {
        $a = WebPush::claves();
        $this->assertSame($a, WebPush::claves());
        $this->assertSame(65, strlen(WebPush::deB64($a['publica'])));
    }

    public function test_avisa_cuando_el_cliente_abre_la_cotizacion_y_no_repite_enseguida(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $s = $this->suscripcion();
        $p = $this->cotizacion();

        $this->get('/cotizacion/' . $p->token . '?vista_previa=1')->assertOk();
        Http::assertNothingSent();

        $this->get('/cotizacion/' . $p->token)->assertOk();
        Http::assertSent(function (Request $r) use ($s) {
            return $r->url() === $s->endpoint
                && $r->header('Content-Encoding')[0] === 'aes128gcm'
                && str_starts_with($r->header('Authorization')[0], 'vapid t=')
                && strlen($r->body()) > 86;
        });
        $this->assertSame('Hotel Xcanatún abrió su cotización', Notificacion::first()->titulo);

        $this->get('/cotizacion/' . $p->token)->assertOk();
        Http::assertSentCount(1);
        $this->assertNotNull($s->fresh()->ultimo_envio_at);
    }

    public function test_respeta_las_preferencias_y_borra_suscripciones_vencidas(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 410)]);
        $sinVistas = $this->suscripcion(['eventos' => ['mensaje_sitio']]);
        $vieja = $this->suscripcion();

        $this->get('/cotizacion/' . $this->cotizacion()->token)->assertOk();

        Http::assertSentCount(1);
        $this->assertNotNull($sinVistas->fresh());
        $this->assertNull($vieja->fresh());
    }

    public function test_alta_preferencias_y_prueba_desde_el_panel(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $plantilla = $this->suscripcion();
        $datos = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/nuevo', 'keys' => ['p256dh' => $plantilla->p256dh, 'auth' => $plantilla->auth]];

        $r = $this->actingAs($this->u)->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Safari/604.1'])
            ->postJson('/admin/notificaciones/suscribir', $datos + ['bienvenida' => true])->assertOk()->json();
        $this->assertSame('iPhone · Safari', $r['dispositivo']);
        $this->assertCount(5, $r['eventos']);
        Http::assertSentCount(1); // bienvenida

        $this->patchJson('/admin/notificaciones/' . $r['id'], ['eventos' => ['resumen_diario']])->assertOk()->assertJson(['eventos' => ['resumen_diario']]);
        $this->postJson('/admin/notificaciones/suscribir', $datos)->assertOk()->assertJson(['eventos' => ['resumen_diario']]);
        $this->postJson('/admin/notificaciones/' . $r['id'] . '/prueba')->assertOk()->assertJson(['ok' => true]);

        $otro = User::factory()->create();
        $this->actingAs($otro)->postJson('/admin/notificaciones/' . $r['id'] . '/prueba')->assertNotFound();
        $this->get('/admin/notificaciones')->assertOk()->assertSee('Este dispositivo');
    }

    public function test_resumen_de_la_manana(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $this->suscripcion();
        $p = $this->cotizacion();
        $p->update(['vigente_hasta' => now()->addHours(20)]);

        $this->artisan('vandu:push-resumen')->expectsOutputToContain('Buenos días · 1 pendiente')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertStringContainsString('Hotel Xcanatún', Notificacion::latest('id')->first()->cuerpo);
    }
}
