<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index() {
        return view('index');
    }

    public function servicios () {
        return view ('servicios');
    }
    public function inmobiliarias () {
        return view ('inmobiliarias');
    }

    public function marketing() {
        return view('construccion');
    }
    public function productos() {
        return view('construccion');
    }
    public function audiovisuales() {
        return view('audiovisuales');
    }
    public function multimedia() {
        return view('construccion');
    }
    public function alianzas() {
        return view('construccion');
    }
    public function construccion() {
        return view('construccion');
    }

    // Solo páginas con contenido; las secciones en construcción quedan fuera.
    public function sitemap() {
        $paginas = [
            ['ruta' => 'home', 'prioridad' => '1.0', 'archivo' => 'index'],
            ['ruta' => 'servicios', 'prioridad' => '0.9', 'archivo' => 'servicios'],
            ['ruta' => 'inmobiliarias', 'prioridad' => '0.8', 'archivo' => 'inmobiliarias'],
            ['ruta' => 'audiovisuales', 'prioridad' => '0.9', 'archivo' => 'audiovisuales'],
            ['ruta' => 'cotizar', 'prioridad' => '0.7', 'archivo' => 'contacto'],
        ];

        $dominio = 'https://agenciavandu.com';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($paginas as $p) {
            $vista = resource_path('views/' . $p['archivo'] . '.blade.php');
            $fecha = date('Y-m-d', file_exists($vista) ? filemtime($vista) : time());
            $xml .= '  <url><loc>' . rtrim($dominio . route($p['ruta'], [], false), '/') . '</loc><lastmod>' . $fecha . '</lastmod><priority>' . $p['prioridad'] . "</priority></url>\n";
        }
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
