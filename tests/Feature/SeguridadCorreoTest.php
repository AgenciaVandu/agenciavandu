<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Parches de seguridad sobre correos (CVE-2026-48019 y CVE-2026-45067) */
class SeguridadCorreoTest extends TestCase
{
    public function test_la_regla_email_rechaza_saltos_de_linea(): void
    {
        foreach (["\"foo\r\nBcc: victima@example.com\"@example.com", "a@b.com\nBcc: c@d.com", "a@b.com\r"] as $malo) {
            $this->assertTrue(validator(['x' => $malo], ['x' => 'email'])->fails(), json_encode($malo));
            $this->assertTrue(validator(['x' => $malo], ['x' => 'email:rfc,dns'])->fails(), json_encode($malo));
        }
        $this->assertTrue(validator(['x' => 'lupe@paletas.mx'], ['x' => 'required|email|max:160'])->passes());
        $this->assertTrue(validator(['x' => null], ['x' => 'nullable|email'])->passes());
        $this->assertTrue(validator(['x' => ['a']], ['x' => 'email'])->fails());
    }

    public function test_symfony_no_arma_direcciones_con_saltos_de_linea(): void
    {
        $this->expectException(\Symfony\Component\Mime\Exception\InvalidArgumentException::class);
        new \Symfony\Component\Mime\Address("\"foo\r\nBcc: victima@example.com\"@example.com");
    }
}
