<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

/** El inicio de sesión busca al usuario en todas las cuentas; después el panel trabaja dentro de la suya */
class ProveedorUsuarios extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope('cuenta');
    }
}
