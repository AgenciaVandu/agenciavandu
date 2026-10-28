<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** El panel de la cuenta principal se llama como la plataforma (Nerby), no Vandu */
class NombreNerbyTest extends TestCase
{
    use RefreshDatabase;

    public function test_titulos_y_app_dicen_nerby(): void
    {
        Cuentas::activar(Cuentas::principalId());
        $this->actingAs(User::factory()->create());
        $html = $this->get(route('admin.resumen'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<title>Inicio · Nerby</title>#', $html);
        $this->assertStringContainsString('apple-mobile-web-app-title" content="Nerby"', $html);
        $this->assertSame('Nerby', \App\Support\IconoApp::nombre(\App\Models\Cuenta::find(Cuentas::principalId())));
    }
}
