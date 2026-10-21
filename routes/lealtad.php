<?php

use App\Http\Controllers\Admin\Lealtad;
use Illuminate\Support\Facades\Route;

/*
| Panel del restaurante (giro Restaurante, módulo "lealtad"). Se carga dentro del grupo /admin (prefijo "admin.").
| Cada sección la protege su permiso (App\Support\Permisos::RUTAS): caja escanea; el gerente arma campañas y ve comensales.
*/

Route::get('lealtad', [Lealtad\CampanasController::class, 'index'])->name('lealtad.campanas');
Route::get('lealtad/campanas/nueva', [Lealtad\CampanasController::class, 'create'])->name('lealtad.campanas.create');
Route::post('lealtad/campanas', [Lealtad\CampanasController::class, 'store'])->name('lealtad.campanas.store');
Route::get('lealtad/campanas/{campana}/editar', [Lealtad\CampanasController::class, 'edit'])->name('lealtad.campanas.edit');
Route::put('lealtad/campanas/{campana}', [Lealtad\CampanasController::class, 'update'])->name('lealtad.campanas.update');
Route::patch('lealtad/campanas/{campana}/estado', [Lealtad\CampanasController::class, 'estado'])->name('lealtad.campanas.estado');
Route::delete('lealtad/campanas/{campana}', [Lealtad\CampanasController::class, 'destroy'])->name('lealtad.campanas.destroy');
Route::get('lealtad/cartel', [Lealtad\QrController::class, 'show'])->name('lealtad.qr');
Route::post('lealtad/ejemplos', [Lealtad\EjemplosController::class, 'cargar'])->name('lealtad.ejemplos');
Route::delete('lealtad/ejemplos', [Lealtad\EjemplosController::class, 'quitar'])->name('lealtad.ejemplos.quitar');

// Caja: escanear el QR del comensal
Route::get('lealtad/escanear', [Lealtad\EscanearController::class, 'show'])->name('lealtad.escanear');
Route::post('lealtad/escanear', [Lealtad\EscanearController::class, 'leer'])->middleware('throttle:120,1')->name('lealtad.escanear.leer');
Route::post('lealtad/escanear/unir', [Lealtad\EscanearController::class, 'unir'])->name('lealtad.escanear.unir');
Route::post('lealtad/escanear/visita', [Lealtad\EscanearController::class, 'visita'])->name('lealtad.escanear.visita');
Route::post('lealtad/escanear/canjear', [Lealtad\EscanearController::class, 'canjear'])->name('lealtad.escanear.canjear');

Route::get('comensales', [Lealtad\ComensalesController::class, 'index'])->name('comensales');
