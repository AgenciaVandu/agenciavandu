<?php

namespace App\Support\Validacion;

use Illuminate\Validation\Validator;

/**
 * Validador de Laravel con los parches de seguridad que Laravel 10 ya no recibe.
 * CVE-2026-48019: la regla "email" aceptaba saltos de línea dentro de la dirección
 * ("foo\r\nBcc: ..."@dominio), lo que permite meter encabezados en los correos que mandamos.
 */
class Validador extends Validator
{
    public function validateEmail($attribute, $value, $parameters)
    {
        if (preg_match('/[\r\n]/', (string) (is_scalar($value) ? $value : '')) > 0) {
            return false;
        }
        return parent::validateEmail($attribute, $value, $parameters);
    }
}
