<?php

namespace App\Support\Dropbox;

class DropboxError extends \RuntimeException
{
    public function __construct(string $mensaje, public string $resumen = '')
    {
        parent::__construct($mensaje);
        $this->resumen = $resumen ?: $mensaje;
    }
}
