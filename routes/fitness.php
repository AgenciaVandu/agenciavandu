<?php

use App\Http\Controllers\Admin\Fitness;
use Illuminate\Support\Facades\Route;

/*
| Panel del gimnasio (giro Fitness). Se carga dentro del grupo /admin (prefijo "admin.", sesión del equipo).
| Cada sección la protege su permiso (App\Support\Permisos::RUTAS) y solo existe con el módulo "fitness".
*/

// Agenda: semana, clases sueltas y horario fijo
Route::get('clases', [Fitness\ClasesController::class, 'index'])->name('clases.index');
Route::get('clases/nueva', [Fitness\ClasesController::class, 'create'])->name('clases.create');
Route::post('clases', [Fitness\ClasesController::class, 'store'])->name('clases.store');
Route::post('clases/ejemplos', [Fitness\AjustesController::class, 'cargarEjemplos'])->name('clases.ejemplos');
Route::delete('clases/ejemplos', [Fitness\AjustesController::class, 'quitarEjemplos'])->name('clases.ejemplos.quitar');
Route::get('clases/horario-fijo', [Fitness\SeriesController::class, 'index'])->name('clases.series');
Route::put('clases/horario-fijo/{serie}', [Fitness\SeriesController::class, 'update'])->name('clases.series.update');
Route::delete('clases/horario-fijo/{serie}', [Fitness\SeriesController::class, 'destroy'])->name('clases.series.destroy');
Route::get('clases/{clase}', [Fitness\ClasesController::class, 'show'])->name('clases.show');
Route::get('clases/{clase}/editar', [Fitness\ClasesController::class, 'edit'])->name('clases.edit');
Route::put('clases/{clase}', [Fitness\ClasesController::class, 'update'])->name('clases.update');
Route::post('clases/{clase}/cancelar', [Fitness\ClasesController::class, 'cancelar'])->name('clases.cancelar');
Route::delete('clases/{clase}', [Fitness\ClasesController::class, 'destroy'])->name('clases.destroy');

// Catálogos de la agenda
Route::get('tipos-de-clase', [Fitness\ClaseTiposController::class, 'index'])->name('clase-tipos');
Route::post('tipos-de-clase', [Fitness\ClaseTiposController::class, 'store'])->name('clase-tipos.store');
Route::put('tipos-de-clase/{tipo}', [Fitness\ClaseTiposController::class, 'update'])->name('clase-tipos.update');
Route::delete('tipos-de-clase/{tipo}', [Fitness\ClaseTiposController::class, 'destroy'])->name('clase-tipos.destroy');
Route::get('ejercicios', [Fitness\EjerciciosController::class, 'index'])->name('ejercicios');
Route::post('ejercicios', [Fitness\EjerciciosController::class, 'store'])->name('ejercicios.store');
Route::put('ejercicios/{ejercicio}', [Fitness\EjerciciosController::class, 'update'])->name('ejercicios.update');
Route::delete('ejercicios/{ejercicio}', [Fitness\EjerciciosController::class, 'destroy'])->name('ejercicios.destroy');

// Reservaciones y pase de lista
Route::post('clases/{clase}/reservaciones', [Fitness\ReservacionesController::class, 'store'])->name('reservaciones.store');
Route::post('reservaciones/{reservacion}/cancelar', [Fitness\ReservacionesController::class, 'cancelar'])->name('reservaciones.cancelar');
Route::patch('reservaciones/{reservacion}/asistencia', [Fitness\ReservacionesController::class, 'asistencia'])->name('reservaciones.asistencia');

// Entrada con QR
Route::get('escanear', [Fitness\EscanearController::class, 'show'])->name('escanear');
Route::post('escanear', [Fitness\EscanearController::class, 'checkin'])->middleware('throttle:120,1')->name('escanear.checkin');
Route::post('escanear/inscribir', [Fitness\EscanearController::class, 'inscribir'])->name('escanear.inscribir');

// Paquetes y membresías
Route::get('paquetes', [Fitness\PaquetesController::class, 'index'])->name('paquetes');
Route::post('paquetes', [Fitness\PaquetesController::class, 'store'])->name('paquetes.store');
Route::put('paquetes/{paquete}', [Fitness\PaquetesController::class, 'update'])->name('paquetes.update');
Route::delete('paquetes/{paquete}', [Fitness\PaquetesController::class, 'destroy'])->name('paquetes.destroy');
Route::post('atletas/{cliente}/membresias', [Fitness\MembresiasController::class, 'store'])->name('membresias.store');
Route::put('membresias/{membresia}', [Fitness\MembresiasController::class, 'update'])->name('membresias.update');
Route::post('membresias/{membresia}/cancelar', [Fitness\MembresiasController::class, 'cancelar'])->name('membresias.cancelar');

// Atleta: foto, acceso a su portal, credencial y baja
Route::get('atletas/{atleta}/foto', [Fitness\AtletasController::class, 'foto'])->whereNumber('atleta')->name('atletas.foto');
Route::post('atletas/{atleta}/foto', [Fitness\AtletasController::class, 'subirFoto'])->name('atletas.foto.subir');
Route::delete('atletas/{atleta}/foto', [Fitness\AtletasController::class, 'quitarFoto'])->name('atletas.foto.quitar');
Route::post('atletas/{atleta}/acceso', [Fitness\AtletasController::class, 'acceso'])->name('atletas.acceso');
Route::post('atletas/{atleta}/qr', [Fitness\AtletasController::class, 'nuevoQr'])->name('atletas.qr');
Route::patch('atletas/{atleta}/activo', [Fitness\AtletasController::class, 'activo'])->name('atletas.activo');
Route::get('atletas/{atleta}/credencial', [Fitness\AtletasController::class, 'credencial'])->name('atletas.credencial');

// Seguimiento: interesados, pruebas, renovaciones y vencidos (las "ventas" del gimnasio)
Route::get('seguimiento', [Fitness\SeguimientoController::class, 'index'])->name('seguimiento');
Route::post('seguimiento', [Fitness\SeguimientoController::class, 'store'])->name('seguimiento.store');
Route::post('seguimiento/{atleta}/descartar', [Fitness\SeguimientoController::class, 'descartar'])->name('seguimiento.descartar');
Route::post('seguimiento/{atleta}/recuperar', [Fitness\SeguimientoController::class, 'recuperar'])->name('seguimiento.recuperar');
Route::post('seguimiento/{atleta}/tarea', [Fitness\SeguimientoController::class, 'tarea'])->name('seguimiento.tarea');

// Reportes y reglas
Route::get('gimnasio/reportes', [Fitness\ReportesController::class, 'index'])->name('fitness-reportes');
Route::get('gimnasio/reportes/horarios', [Fitness\ReportesController::class, 'horarios'])->name('fitness-reportes.horarios');
Route::get('gimnasio/reglas', [Fitness\AjustesController::class, 'show'])->name('fitness-ajustes');
Route::post('gimnasio/reglas', [Fitness\AjustesController::class, 'update'])->name('fitness-ajustes.guardar');
