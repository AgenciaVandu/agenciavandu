<?php

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

Route::get('/', function () {
    return view('index');
})->name("home");

Route::get('/servicios', function () {
    return view('servicios');
})->name("servicios");
Route::get('/marketing-digital', function () {
    return view('marketing');
})->name("marketing");
Route::get('/multimedia', function () {
    return view('multimedia');
})->name("multimedia");

Route::get('/cotizar-servicios', function () {
    return view('contacto');
})->name("cotizar");