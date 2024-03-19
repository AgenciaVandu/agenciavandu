<?php

use App\Http\Controllers\ContactanosController;
use App\Mail\ContactanosMailable;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

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

Route::get('/marketing-digital', [PageController::class, 'marketing'])->name('marketing');
Route::get('servicios-de-diseno-impresos', [PageController::class, 'impresos'])->name('impresos');

Route::get('/multimedia', [PageController::class, 'multimedia'])->name('multimedia');
Route::get('/fotografia-empresarial', [PageController::class, 'foto'])->name('foto');

Route::get('/sitio-en-construccion', [PageController::class, 'construccion'])->name('construccion');
Route::get('/alianzas-de-diseno', [PageController::class, 'alianzas'])->name('alianzas');

Route::get('/cotizar', [ContactanosController::class, 'index'])->name('cotizar');
Route::post('/mensaje-enviado', [ContactanosController::class, 'store'])->name('cotizar.store');