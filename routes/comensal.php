<?php

use App\Http\Controllers\Lealtad;
use Illuminate\Support\Facades\Route;

/*
| App del comensal (programa de lealtad de la red de restaurantes): /lealtad.
| El comensal es de la plataforma (guard "comensal"); /lealtad/{restaurante} es la puerta de cada restaurante (el QR del cartel).
*/

Route::prefix('lealtad')->name('lealtad.')->group(function () {
    Route::get('/', [Lealtad\PortalController::class, 'inicio'])->name('inicio');
    Route::get('manifest.webmanifest', [Lealtad\PortalController::class, 'manifiesto'])->name('manifiesto');
    Route::get('entrar', [Lealtad\AccesoController::class, 'entrar'])->name('entrar');
    Route::post('entrar', [Lealtad\AccesoController::class, 'login'])->middleware('throttle:20,1')->name('login');
    Route::get('registro', [Lealtad\AccesoController::class, 'registro'])->name('registro');
    Route::post('registro', [Lealtad\AccesoController::class, 'registrar'])->middleware('throttle:10,1')->name('registrar');
    Route::post('salir', [Lealtad\AccesoController::class, 'salir'])->name('salir');
    Route::match(['get', 'post'], 'olvide', [Lealtad\AccesoController::class, 'olvide'])->middleware('throttle:20,1')->name('olvide');
    Route::get('acceso/{token}', [Lealtad\AccesoController::class, 'acceso'])->where('token', '[A-Za-z0-9]{48}')->name('acceso');
    Route::post('acceso/{token}', [Lealtad\AccesoController::class, 'guardarAcceso'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1')->name('acceso.guardar');

    Route::middleware('comensal')->group(function () {
        Route::get('qr', [Lealtad\PortalController::class, 'qr'])->name('qr');
        Route::get('qr/codigo', [Lealtad\PortalController::class, 'codigo'])->middleware('throttle:60,1')->name('qr.codigo');
        Route::get('perfil', [Lealtad\PortalController::class, 'perfil'])->name('perfil');
        Route::post('perfil', [Lealtad\PortalController::class, 'guardarPerfil'])->name('perfil.guardar');
        Route::post('{slug}/baja', [Lealtad\PortalController::class, 'baja'])->where('slug', '[a-z0-9-]+')->name('baja');
    });

    Route::get('{slug}', [Lealtad\PortalController::class, 'restaurante'])->where('slug', '[a-z0-9-]+')->name('restaurante');
    Route::post('{slug}/unirme', [Lealtad\PortalController::class, 'unirme'])->where('slug', '[a-z0-9-]+')->name('unirme');
});
