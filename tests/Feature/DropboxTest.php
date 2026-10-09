<?php

namespace Tests\Feature;

use App\Models\Integracion;
use App\Support\Dropbox\Dropbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Cliente de Dropbox contra respuestas simuladas de la API (no toca la red) */
class DropboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vandu.dropbox.app_key' => 'llave', 'vandu.dropbox.app_secret' => 'secreto', 'vandu.dropbox.simulado' => false]);
        Dropbox::usar(null);
        Cache::flush();
        Integracion::create(['proveedor' => 'dropbox', 'datos' => ['refresh_token' => 'R1', 'access_token' => 'viejo', 'expira' => time() - 10]]);
    }

    public function test_renueva_el_token_y_lo_guarda_cifrado(): void
    {
        Http::fake(['api.dropboxapi.com/oauth2/token' => Http::response(['access_token' => 'NUEVO', 'expires_in' => 14400])]);

        $this->assertSame('NUEVO', Dropbox::cliente()->token());
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'R1' && $r['client_id'] === 'llave');
        $this->assertSame('NUEVO', Integracion::de('dropbox')->datos['access_token']);
        $this->assertStringNotContainsString('NUEVO', \DB::table('integraciones')->value('datos'), 'El token debe guardarse cifrado');
    }

    public function test_sube_en_partes_los_archivos_grandes_con_argumento_ascii(): void
    {
        Http::fake([
            'api.dropboxapi.com/oauth2/token'                   => Http::response(['access_token' => 'T', 'expires_in' => 14400]),
            'content.dropboxapi.com/2/files/upload_session/start'  => Http::response(['session_id' => 'S1']),
            'content.dropboxapi.com/2/files/upload_session/append_v2' => Http::response(null),
            'content.dropboxapi.com/2/files/upload_session/finish' => Http::response(['id' => 'id:abc', 'name' => 'Sesión.mp4', 'path_display' => '/Vandu/Sesión.mp4', 'size' => 20971520]),
        ]);
        $tmp = tempnam(sys_get_temp_dir(), 'v');
        file_put_contents($tmp, str_repeat('x', 20 * 1024 * 1024)); // 20 MB → inicio + 1 append + fin

        $meta = Dropbox::cliente()->subirArchivo($tmp, '/Vandu/Sesión.mp4');
        unlink($tmp);

        $this->assertSame('id:abc', $meta['id']);
        $partes = collect(Http::recorded())->map(fn ($x) => $x[0])->filter(fn ($r) => str_contains($r->url(), 'upload_session'));
        $this->assertCount(3, $partes);
        $fin = $partes->last();
        $arg = $fin->header('Dropbox-API-Arg')[0];
        $this->assertTrue(mb_check_encoding($arg, 'ASCII'), 'El encabezado debe ir en ASCII');
        $this->assertStringContainsString('Sesi' . chr(92) . 'u00f3n', $arg);
        $this->assertSame(16 * 1024 * 1024, json_decode($arg, true)['cursor']['offset']);
    }

    public function test_enlace_temporal_se_reutiliza_y_la_carpeta_existente_no_falla(): void
    {
        Http::fake([
            'api.dropboxapi.com/oauth2/token'           => Http::response(['access_token' => 'T', 'expires_in' => 14400]),
            'api.dropboxapi.com/2/files/get_temporary_link' => Http::response(['link' => 'https://dl.dropboxusercontent.com/x']),
            'api.dropboxapi.com/2/files/create_folder_v2'   => Http::response(['error_summary' => 'path/conflict/folder/..'], 409),
            'api.dropboxapi.com/2/sharing/create_shared_link_with_settings' => Http::response(['error_summary' => 'shared_link_already_exists/..'], 409),
            'api.dropboxapi.com/2/sharing/list_shared_links' => Http::response(['links' => [['url' => 'https://www.dropbox.com/scl/fo/abc/xyz?rlkey=k&dl=0']]]),
        ]);
        $d = Dropbox::cliente();
        $this->assertSame('https://dl.dropboxusercontent.com/x', $d->enlaceTemporal('id:1'));
        $d->enlaceTemporal('id:1');
        $this->assertCount(1, collect(Http::recorded())->filter(fn ($x) => str_contains($x[0]->url(), 'get_temporary_link')));

        $d->crearCarpeta('/Vandu/Proyectos'); // no lanza error
        $this->assertSame('https://www.dropbox.com/scl/fo/abc/xyz?rlkey=k&dl=1', $d->enlaceCarpeta('/Vandu/Proyectos/X/Galería'));
    }

    public function test_nombres_de_carpeta_seguros(): void
    {
        $this->assertSame('Gas Imperial - Video- 2026', Dropbox::nombreSeguro('Gas Imperial / Video: 2026'));
        $this->assertSame('Cliente', Dropbox::nombreSeguro('  Cliente.  '));
    }
}
