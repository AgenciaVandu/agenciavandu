<?php

use App\Http\Controllers\ContactanosController;
use App\Mail\ContactanosMailable;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CotizarController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get ('/', [PageController::class, 'index'])->name("home");

Route::get('/servicios', [Pagecontroller::class, 'servicios'])->name('servicios');

// intencion amplia

Route::get('/servicios/diseno-web-para-inmobiliarias', [Pagecontroller::class, 'inmobiliarias'])->name('inmobiliarias');

Route::get('/marketing-digital', [PageController::class, 'marketing'])->name('marketing');
Route::get('/productos-digitales', [PageController::class, 'productos'])->name('productos');

Route::get('/multimedia', [PageController::class, 'multimedia'])->name('multimedia');
Route::get('/video-y-fotografia', [PageController::class, 'audiovisuales'])->name('audiovisuales');

Route::get('/sitio-en-construccion', [PageController::class, 'construccion'])->name('construccion');
Route::get('/alianzas-de-diseno', [PageController::class, 'alianzas'])->name('alianzas');

Route::get('/cotizar', [ContactanosController::class, 'index'])->name('cotizar');
Route::post('/mensaje-enviado', [CotizarController::class, 'store'])->name('cotizar.store');

Route::get('/admin/cotizaciones', [CotizarController::class, 'index'])
    ->middleware('admin.vandu') 
    ->name('cotizar.index');