<?php

use App\Http\Controllers\Acceso\ControlIdController;
use Illuminate\Support\Facades\Route;

/*
| Torniquetes Control iD (iDFace) en modo online: el equipo pregunta aquí si deja pasar.
| Sin sesión ni CSRF; el token de la dirección identifica al equipo y a su gimnasio.
*/
Route::any('api/controlid/{token}/{evento}.fcgi', ControlIdController::class)
    ->where(['token' => '[a-z0-9]{32}', 'evento' => '[a-z_]+'])->name('controlid');
