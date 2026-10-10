<?php

namespace Tests\Feature;

use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Correo;
use App\Models\User;
use App\Support\Correos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Plantilla "Enviar factura" con PDF y XML adjuntos */
class CorreoFacturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_la_factura_con_sus_archivos_al_correo_de_facturacion(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create());
        $c = Cliente::create(['nombre' => 'Ana López', 'email' => 'ana@hotel.mx', 'email_factura' => 'conta@hotel.mx']);

        $pl = Correos::plantillas(Correos::contexto('cliente', $c->id))['factura'];
        $this->assertSame('conta@hotel.mx', $pl['para']);
        $this->assertTrue($pl['adjuntos']);
        $this->assertStringContainsString('Hola Ana', $pl['cuerpo']);
        $this->assertStringNotContainsString('{', $pl['cuerpo']);

        $this->post('/admin/correos', [
            'contexto_tipo' => 'cliente', 'contexto_id' => $c->id, 'plantilla' => 'factura',
            'para' => 'conta@hotel.mx', 'asunto' => $pl['asunto'], 'cuerpo' => $pl['cuerpo'],
            'adjuntos' => [
                UploadedFile::fake()->createWithContent('A123.pdf', '%PDF-1.4 factura'),
                UploadedFile::fake()->createWithContent('A123.xml', '<?xml version="1.0"?><cfdi:Comprobante/>'),
            ],
        ])->assertSessionHasNoErrors();

        Mail::assertSent(CorreoVandu::class, fn ($m) => count($m->archivos) === 2 && $m->archivos[1]['nombre'] === 'A123.xml');
        $this->assertSame(['A123.pdf', 'A123.xml'], Correo::sole()->adjuntos);
    }

    public function test_rechaza_archivos_no_permitidos(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create());
        $c = Cliente::create(['nombre' => 'Ana', 'email' => 'ana@hotel.mx']);
        $this->post('/admin/correos', [
            'contexto_tipo' => 'cliente', 'contexto_id' => $c->id, 'plantilla' => 'factura', 'para' => 'ana@hotel.mx',
            'asunto' => 'Factura', 'cuerpo' => 'Hola', 'adjuntos' => [UploadedFile::fake()->create('virus.exe', 10)],
        ])->assertSessionHasErrors('adjuntos.0');
        Mail::assertNothingSent();
    }
}
