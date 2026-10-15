<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Aviso de privacidad, barra de cookies y Consent Mode del sitio */
class PrivacidadTest extends TestCase
{
    public function test_el_aviso_de_privacidad_tiene_lo_que_pide_la_ley(): void
    {
        $this->get('/aviso-de-privacidad')->assertOk()
            ->assertSee(config('vandu.privacidad.responsable'))
            ->assertSee(config('vandu.privacidad.domicilio'))
            ->assertSee(config('vandu.privacidad.correo'))
            ->assertSee('derechos ARCO')
            ->assertSee('Finalidades adicionales')
            ->assertSee('Cookies y tecnologías de rastreo');
    }

    public function test_esta_en_el_sitemap(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('https://agenciavandu.com/aviso-de-privacidad', false);
    }

    public function test_todas_las_paginas_traen_barra_de_cookies_consent_mode_y_enlace_al_aviso(): void
    {
        foreach (['/', '/servicios', '/video-y-fotografia', '/cotizar'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="ck-barra"', false)
                ->assertSee("gtag('consent', 'default'", false)
                ->assertSee('Preferencias de cookies')
                ->assertSee(route('privacidad'), false);
        }
    }

    public function test_los_formularios_avisan_antes_de_pedir_datos(): void
    {
        $this->get('/')->assertSee('Al continuar aceptas nuestro', false);
        $this->get('/cotizar')->assertSee('Al enviar aceptas nuestro', false);
    }
}
