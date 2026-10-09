<?php

use App\Http\Controllers\ContactanosController;
use App\Mail\ContactanosMailable;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CotizarController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\PresupuestoPublicoController;
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

// La antigua lista de prospectos ya no existe: manda al panel
Route::redirect('/admin/cotizaciones', '/admin');

/*
|--------------------------------------------------------------------------
| Panel de clientes y cotizaciones (presupuestos)
|--------------------------------------------------------------------------
*/
// Tipografía del panel y de la vista del cliente, servida desde resources/ (no depende de public/)
Route::get('/vandu-fuente.woff2', fn () => response()->file(resource_path('fonts/Geist-Variable.woff2'), [
    'Content-Type'  => 'font/woff2',
    'Cache-Control' => 'public, max-age=31536000, immutable',
]))->name('vandu.fuente');

// Inicio de sesión del panel
// El panel como app instalable (públicos: el navegador los pide antes de iniciar sesión)
Route::get('/admin/manifest.webmanifest', [App\Http\Controllers\AppController::class, 'manifiesto'])->name('app.manifiesto');
Route::get('/admin/sw.js', [App\Http\Controllers\AppController::class, 'serviceWorker'])->name('app.sw');
Route::get('/admin/app/icono/{archivo}', [App\Http\Controllers\AppController::class, 'icono'])->where('archivo', '[a-z0-9-]+\.png')->name('app.icono');
Route::get('/admin/sin-conexion', [App\Http\Controllers\AppController::class, 'sinConexion'])->name('app.sin-conexion');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [Admin\LoginController::class, 'show'])->name('login');
    Route::post('/admin/login', [Admin\LoginController::class, 'login'])->name('login.entrar');
});
Route::post('/admin/logout', [Admin\LoginController::class, 'logout'])->middleware('admin.vandu')->name('logout');

Route::prefix('admin')->name('admin.')->middleware('admin.vandu')->group(function () {
    Route::get('/', [Admin\ResumenController::class, 'index'])->name('resumen');

    Route::resource('clientes', Admin\ClienteController::class);
    Route::post('correos', [Admin\CorreoController::class, 'enviar'])->name('correos.enviar');
    Route::post('correos/vista-previa', [Admin\CorreoController::class, 'vistaPrevia'])->name('correos.vista-previa');
    Route::post('clientes/{cliente}/constancias', [Admin\ClienteController::class, 'subirConstancia'])->name('clientes.constancias.store');
    Route::get('clientes/{cliente}/constancias/{constancia}', [Admin\ClienteController::class, 'verConstancia'])->name('clientes.constancias.show');
    Route::delete('clientes/{cliente}/constancias/{constancia}', [Admin\ClienteController::class, 'borrarConstancia'])->name('clientes.constancias.destroy');

    Route::resource('presupuestos', Admin\PresupuestoController::class)
        ->except('show')
        ->parameters(['presupuestos' => 'presupuesto']);
    Route::post('presupuestos/{presupuesto}/duplicar', [Admin\PresupuestoController::class, 'duplicar'])->name('presupuestos.duplicar');
    Route::patch('presupuestos/{presupuesto}/rapido', [Admin\PresupuestoController::class, 'rapido'])->name('presupuestos.rapido');
    // Finanzas
    Route::get('finanzas', [Admin\FinanzasController::class, 'index'])->name('finanzas');
    Route::get('finanzas/exportar', [Admin\FinanzasController::class, 'exportar'])->name('finanzas.exportar');

    // Proyectos
    Route::get('proyectos', [Admin\ProyectoController::class, 'index'])->name('proyectos.index');
    Route::get('presupuestos/{presupuesto}/proyecto', [Admin\ProyectoController::class, 'crear'])->name('proyectos.create');
    Route::post('presupuestos/{presupuesto}/proyecto', [Admin\ProyectoController::class, 'store'])->name('proyectos.store');
    Route::get('proyectos/{proyecto}/fechas', [Admin\ProyectoController::class, 'fechas'])->name('proyectos.fechas');
    Route::put('proyectos/{proyecto}/fechas', [Admin\ProyectoController::class, 'guardarFechas'])->name('proyectos.fechas.guardar');
    Route::get('proyectos/{proyecto}', [Admin\ProyectoController::class, 'show'])->name('proyectos.show');
    Route::put('proyectos/{proyecto}', [Admin\ProyectoController::class, 'update'])->name('proyectos.update');
    Route::delete('proyectos/{proyecto}', [Admin\ProyectoController::class, 'destroy'])->name('proyectos.destroy');
    Route::patch('proyectos/{proyecto}/etapas/{etapa}', [Admin\ProyectoController::class, 'etapa'])->name('proyectos.etapa');
    Route::patch('proyectos/{proyecto}/pagos/{pago}', [Admin\ProyectoController::class, 'pago'])->name('proyectos.pago');
    // Dropbox
    Route::get('dropbox', [Admin\DropboxController::class, 'index'])->name('dropbox');
    Route::get('dropbox/conectar', [Admin\DropboxController::class, 'conectar'])->name('dropbox.conectar');
    Route::post('dropbox/desconectar', [Admin\DropboxController::class, 'desconectar'])->name('dropbox.desconectar');
    Route::get('dropbox/abrir', [Admin\DropboxController::class, 'abrir'])->name('dropbox.abrir');
    Route::get('dropbox/token', [Admin\DropboxController::class, 'token'])->name('dropbox.token');
    Route::get('dropbox/explorar', [Admin\DropboxController::class, 'explorar'])->name('dropbox.explorar');
    Route::get('proyectos/{proyecto}/dropbox/destino', [Admin\DropboxController::class, 'destino'])->name('proyectos.dropbox.destino');
    Route::post('proyectos/{proyecto}/dropbox/registrar', [Admin\DropboxController::class, 'registrar'])->name('proyectos.dropbox.registrar');
    Route::post('proyectos/{proyecto}/dropbox/importar', [Admin\DropboxController::class, 'importar'])->name('proyectos.dropbox.importar');
    Route::post('proyectos/{proyecto}/dropbox/sincronizar', [Admin\DropboxController::class, 'sincronizar'])->name('proyectos.dropbox.sincronizar');

    Route::post('proyectos/{proyecto}/archivos', [Admin\ProyectoController::class, 'subir'])->name('proyectos.subir');
    Route::patch('proyectos/{proyecto}/archivos/{archivo}', [Admin\ProyectoController::class, 'archivo'])->name('proyectos.archivo');
    Route::delete('proyectos/{proyecto}/archivos/{archivo}', [Admin\ProyectoController::class, 'borrarArchivo'])->name('proyectos.archivo.borrar');
    Route::get('proyectos/{proyecto}/archivos/{archivo}', [Admin\ProyectoController::class, 'verArchivo'])->name('proyectos.archivo.ver');

    Route::get('presupuestos/{presupuesto}/pdf', [Admin\PresupuestoController::class, 'pdf'])->name('presupuestos.pdf');
});

// Vista pública para el cliente (enlace con token, vigente hasta la fecha de la cotización)
Route::get('/cotizacion/{token}', [PresupuestoPublicoController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')->name('presupuesto.publico');
Route::get('/cotizacion/{token}/descargar', [PresupuestoPublicoController::class, 'descargar'])
    ->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:30,1')->name('presupuesto.descargar');

// Vista pública del proyecto para el cliente
Route::get('/proyecto/{token}', [\App\Http\Controllers\ProyectoPublicoController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')->name('proyecto.publico');
Route::get('/proyecto/{token}/entrega', [\App\Http\Controllers\ProyectoPublicoController::class, 'entrega'])
    ->where('token', '[A-Za-z0-9]{32}')->name('proyecto.entrega');
Route::get('/proyecto/{token}/archivos/{archivo}', [\App\Http\Controllers\ProyectoPublicoController::class, 'archivo'])
    ->where('token', '[A-Za-z0-9]{32}')->name('proyecto.archivo');
Route::get('/proyecto/{token}/galeria.zip', [\App\Http\Controllers\ProyectoPublicoController::class, 'zip'])
    ->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:10,1')->name('proyecto.zip');

// Dropbox simulado: solo para pruebas locales (VANDU_DROPBOX_SIMULADO=true)
if (config('vandu.dropbox.simulado') && app()->environment('local', 'testing')) {
    Route::post('/_dropbox-simulado/api/{endpoint}', [App\Http\Controllers\Admin\DropboxController::class, 'simuladoApi'])->where('endpoint', '.*')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    Route::get('/_dropbox-simulado/{id}', [App\Http\Controllers\Admin\DropboxController::class, 'simulado'])->where('id', 'id:[A-Za-z0-9]+')->name('dropbox.simulado');
}
