<?php

use App\Http\Controllers\Atleta;
use Illuminate\Support\Facades\Route;

/*
| Portal del atleta (giro Fitness): /atleta en el dominio propio del gimnasio o /atleta/{nombre-corto} en la plataforma.
| Acceso propio (guard "atleta"): el atleta nunca entra al panel. Las páginas con sesión pasan por el middleware "atleta",
| que activa su gimnasio antes de buscar los registros de la URL.
*/

Route::prefix('atleta')->name('atleta.')->group(function () {
    Route::get('manifest.webmanifest', [Atleta\AppController::class, 'manifiesto'])->name('manifiesto');
    Route::get('sw.js', [Atleta\AppController::class, 'serviceWorker'])->name('sw');

    // Crear la contraseña con el enlace del gimnasio
    Route::get('acceso/{token}', [Atleta\AccesoController::class, 'acceso'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('acceso');
    Route::post('acceso/{token}', [Atleta\AccesoController::class, 'guardarAcceso'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1')->name('acceso.guardar');
    Route::post('salir', [Atleta\AccesoController::class, 'salir'])->name('salir');

    Route::middleware('atleta')->group(function () {
        Route::get('inicio', [Atleta\PortalController::class, 'inicio'])->name('inicio');
        Route::get('clases', [Atleta\PortalController::class, 'clases'])->name('clases');
        Route::get('clases/{clase}', [Atleta\PortalController::class, 'clase'])->whereNumber('clase')->name('clase');
        Route::post('clases/{clase}/reservar', [Atleta\PortalController::class, 'reservar'])->whereNumber('clase')->middleware('throttle:30,1')->name('reservar');
        Route::post('reservaciones/{reservacion}/cancelar', [Atleta\PortalController::class, 'cancelar'])->whereNumber('reservacion')->middleware('throttle:30,1')->name('cancelar');
        Route::get('credencial', [Atleta\PortalController::class, 'credencial'])->name('credencial');
        Route::get('historial', [Atleta\PortalController::class, 'historial'])->name('historial');
        Route::get('perfil', [Atleta\PortalController::class, 'perfil'])->name('perfil');
        Route::post('perfil', [Atleta\PortalController::class, 'guardarPerfil'])->name('perfil.guardar');
        Route::get('foto', [Atleta\PortalController::class, 'foto'])->name('foto');
        Route::post('avisos', [Atleta\PortalController::class, 'suscribir'])->name('avisos');
        Route::delete('avisos', [Atleta\PortalController::class, 'desuscribir'])->name('avisos.quitar');
    });

    // Entrar: en el dominio propio del gimnasio sin nombre corto; en la plataforma, /atleta/{negocio}
    Route::get('/', [Atleta\AccesoController::class, 'entrar'])->name('entrar.dominio');
    Route::post('/', [Atleta\AccesoController::class, 'login'])->middleware('throttle:20,1')->name('login.dominio');
    Route::match(['get', 'post'], 'olvide', [Atleta\AccesoController::class, 'olvide'])->middleware('throttle:20,1')->name('olvide.dominio');
    Route::get('{negocio}', [Atleta\AccesoController::class, 'entrar'])->where('negocio', '[a-z0-9-]+')->name('entrar');
    Route::post('{negocio}', [Atleta\AccesoController::class, 'login'])->where('negocio', '[a-z0-9-]+')->middleware('throttle:20,1')->name('login');
    Route::match(['get', 'post'], '{negocio}/olvide', [Atleta\AccesoController::class, 'olvide'])->where('negocio', '[a-z0-9-]+')->middleware('throttle:20,1')->name('olvide');
});
