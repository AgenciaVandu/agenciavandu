<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\RedesPerfil;
use App\Models\RedesPost;
use App\Support\Redes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Crea (o borra) un cliente de prueba con contenido de redes para ver cómo funciona el módulo.
 * Las imágenes se generan aquí mismo y se guardan en el servidor (no en tu Dropbox).
 */
class DemoRedes extends Command
{
    protected $signature = 'vandu:demo-redes {--borrar : Borra el cliente de prueba y todo su contenido}';

    protected $description = 'Crea un cliente de prueba con calendario de redes, posts e imágenes de ejemplo';

    private const EMAIL = 'demo-redes@agenciavandu.com';

    public function handle(): int
    {
        $existente = Cliente::where('email', self::EMAIL)->first();

        if ($this->option('borrar')) {
            if (! $existente) { $this->info('No hay cliente de prueba.'); return self::SUCCESS; }
            Storage::disk('local')->deleteDirectory("redes/{$existente->id}");
            $existente->delete(); // borra en cascada posts, archivos, comentarios, perfiles y códigos
            $this->info('Cliente de prueba borrado.');
            return self::SUCCESS;
        }

        if ($existente) {
            Storage::disk('local')->deleteDirectory("redes/{$existente->id}");
            $existente->delete();
        }

        $c = Cliente::create([
            'nombre' => 'Mariana Canché', 'empresa' => 'Café Itzá (prueba)', 'email' => self::EMAIL, 'telefono' => '9990000000',
            'notas' => 'Cliente de prueba para el módulo de redes. Se borra con: php artisan vandu:demo-redes --borrar',
        ]);

        $perfiles = [
            'instagram' => ['usuario' => 'cafeitza', 'nombre' => 'Café Itzá', 'bio' => "Café de especialidad yucateco ☕\nTostado en casa · Mérida centro\nAbierto todos los días 7–21 h", 'enlace' => 'cafeitza.mx', 'seguidores' => 8420, 'seguidos' => 412],
            'facebook'  => ['usuario' => 'cafeitza', 'nombre' => 'Café Itzá', 'seguidores' => 5100],
            'tiktok'    => ['usuario' => 'cafeitza', 'nombre' => 'Café Itzá', 'seguidores' => 2300],
            'linkedin'  => ['usuario' => 'cafe-itza', 'nombre' => 'Café Itzá · Tostadores', 'seguidores' => 640],
        ];
        $avatar = $this->imagen("redes/{$c->id}/perfil/avatar.jpg", 400, 400, [[60, 34, 20], [120, 72, 40]], 'CI', '', 'avatar');
        foreach ($perfiles as $red => $d) {
            RedesPerfil::create(array_merge($d, ['cliente_id' => $c->id, 'red' => $red, 'avatar' => $avatar, 'avatar_origen' => 'local']));
        }

        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $cafe = [[92, 52, 30], [160, 100, 60]];
        $verde = [[22, 78, 60], [64, 140, 100]];
        $crema = [[196, 150, 100], [236, 206, 160]];
        $noche = [[20, 24, 36], [70, 60, 90]];
        $sol = [[214, 120, 40], [244, 186, 80]];
        $rosa = [[170, 60, 90], [230, 130, 140]];

        $posts = [
            // [días desde hoy, hora, título, redes, formato, estado, colores, texto, imágenes]
            [-34, '09:00', 'Bienvenidos', ['instagram', 'facebook'], 'post', 'publicado', $cafe, "Abrimos nuestras puertas ☕ Café de especialidad de productores de Chiapas y Veracruz, tostado aquí mismo en Mérida.\n\n#CaféDeEspecialidad #Mérida", ['Bienvenidos']],
            [-27, '08:30', 'Método V60', ['instagram'], 'post', 'publicado', $crema, 'Sábado de métodos: prueba nuestro V60 con grano de Chiapas, notas a chocolate y cereza 🍒 #V60 #CoffeeLovers', ['Método V60']],
            [-20, '18:00', 'Tostado en casa', ['instagram', 'facebook', 'tiktok'], 'reel', 'publicado', $noche, 'Así tostamos tu café cada semana 🔥 #Tostado #CaféMexicano', ['Tostado en casa']],
            [-13, '10:00', 'Nuevo horario', ['instagram', 'facebook'], 'post', 'publicado', $verde, 'Ahora abrimos desde las 7 am 🌅 ¡Te esperamos para el primer café del día!', ['Nuevo horario']],
            [-6, '13:00', 'Postres de temporada', ['instagram', 'facebook'], 'carrusel', 'publicado', $rosa, "Llegaron los postres de otoño 🍂 Desliza para verlos todos.\n\n1. Pay de calabaza\n2. Pan de muerto relleno\n3. Brownie de mole", ['Pay de calabaza', 'Pan de muerto', 'Brownie de mole']],
            [1, '09:00', 'Café de olla', ['instagram', 'facebook'], 'post', 'aprobado', $cafe, 'Regresa el café de olla ☕🍊 Canela, piloncillo y un toque de naranja. Solo por temporada. #CaféDeOlla', ['Café de olla']],
            [3, '17:30', 'Taller de barismo', ['instagram', 'facebook', 'linkedin'], 'post', 'revision', $noche, "Taller de barismo para principiantes 👩‍🍳 Sábado 10 am · cupo limitado a 8 personas.\n\nAprende a calibrar tu molino, extraer un buen espresso y texturizar leche.\n\nInscríbete por DM. #Barismo #TallerDeCafé", ['Taller de barismo']],
            [5, '12:00', 'Productores', ['instagram', 'facebook', 'linkedin'], 'carrusel', 'revision', $verde, 'Conoce a quienes cultivan tu café 🌱 Don Ramiro y su familia, en la sierra de Chiapas, llevan tres generaciones cuidando cada planta. #ComercioJusto', ['Don Ramiro', 'La finca', 'La cosecha']],
            [7, '19:00', 'Detrás de la barra', ['instagram', 'tiktok'], 'reel', 'revision', $sol, 'Un día detrás de la barra en 30 segundos ⏱️ #BaristaLife', ['Detrás de la barra']],
            [9, '08:00', 'Encuesta de bebidas', ['instagram'], 'historia', 'revision', $crema, '¿Frío o caliente? Vota en nuestra historia 👇', ['¿Frío o caliente?']],
            [11, '13:30', 'Promo 2x1', ['instagram', 'facebook'], 'post', 'cambios', $sol, 'Martes de 2x1 en lattes ☕☕ De 4 a 7 pm. #2x1 #Mérida', ['2x1 en lattes']],
            [14, '10:00', 'Empleo', ['linkedin', 'facebook'], 'post', 'revision', $cafe, 'Estamos buscando barista con experiencia para nuestro equipo en Mérida centro. Ofrecemos capacitación continua, prestaciones y buen ambiente. Envía tu CV a empleo@cafeitza.mx', ['Únete al equipo']],
            [18, '18:00', 'Noche de cata', ['instagram', 'facebook'], 'post', 'borrador', $noche, 'Noche de cata de cafés mexicanos 🇲🇽 (borrador: falta confirmar fecha)', ['Noche de cata']],
        ];

        $n = 0;
        foreach ($posts as [$dias, $hora, $titulo, $redes, $formato, $estado, $colores, $texto, $imagenes]) {
            $fecha = $hoy->copy()->addDays($dias)->setTimeFromTimeString($hora)->setTimezone('UTC');
            $p = RedesPost::create(['cliente_id' => $c->id, 'titulo' => $titulo, 'redes' => $redes, 'formato' => $formato, 'fecha' => $fecha, 'texto' => $texto, 'estado' => $estado,
                'aprobado_at' => in_array($estado, ['aprobado', 'publicado'], true) ? now() : null, 'aprobado_por' => in_array($estado, ['aprobado', 'publicado'], true) ? 'Mariana Canché' : null]);
            [$w, $h] = match (true) {
                in_array($formato, ['reel', 'historia'], true) => [1080, 1920],
                $redes === ['linkedin', 'facebook'] => [1200, 900],
                default => [1080, 1350],
            };
            foreach ($imagenes as $i => $txt) {
                $ruta = $this->imagen("redes/{$c->id}/{$p->id}/img-" . ($i + 1) . '.jpg', $w, $h, $colores, $txt, count($imagenes) > 1 ? ($i + 1) . '/' . count($imagenes) : 'Café Itzá');
                $p->medios()->create(['orden' => $i, 'tipo' => 'imagen', 'origen' => 'local', 'ruta' => $ruta, 'nombre' => basename($ruta), 'ancho' => $w, 'alto' => $h, 'bytes' => Storage::disk('local')->size($ruta)]);
            }
            if ($estado !== 'borrador') Redes::comentar($p, 'revision', 'agencia', 'Agencia Vandu', 'Enviado a revisión');
            if (in_array($estado, ['aprobado', 'publicado'], true)) Redes::comentar($p, 'aprobado', 'cliente', 'Mariana Canché', null);
            if ($estado === 'cambios') {
                Redes::comentar($p, 'cambios', 'cliente', 'Mariana Canché', '¿Podemos cambiar el horario a "de 5 a 8 pm"? Y que la foto tenga dos tazas, por favor 🙏');
                Redes::comentar($p, 'comentario', 'agencia', 'Agencia Vandu', '¡Claro! Lo ajustamos hoy y te lo mandamos de nuevo.');
            }
            if ($titulo === 'Productores') Redes::comentar($p, 'comentario', 'cliente', 'Mariana Canché', 'Me encanta esta serie 😍 ¿podemos hacer una cada mes?');
            $n++;
        }

        $k = Redes::generarCodigo($c, 'manual');
        $this->info("Cliente de prueba creado: {$c->empresa} con $n posts.");
        $this->line('Panel:  ' . route('admin.redes.cliente', $c));
        $this->line('Vista del cliente:  ' . Redes::urlCliente($c));
        $this->line("Código de verificación (24 h): {$k['formateado']}");
        $this->line('Para borrarlo: php artisan vandu:demo-redes --borrar');
        return self::SUCCESS;
    }

    /** Imagen de ejemplo: degradado, círculos suaves y un texto grande */
    private function imagen(string $ruta, int $w, int $h, array $colores, string $texto, string $pie, string $tipo = 'post'): string
    {
        $im = imagecreatetruecolor($w, $h);
        [$a, $b] = $colores;
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max(1, $h - 1);
            $col = imagecolorallocate($im, (int) ($a[0] + ($b[0] - $a[0]) * $t), (int) ($a[1] + ($b[1] - $a[1]) * $t), (int) ($a[2] + ($b[2] - $a[2]) * $t));
            imageline($im, 0, $y, $w, $y, $col);
        }
        mt_srand(crc32($ruta));
        for ($i = 0; $i < 6; $i++) {
            $r = mt_rand((int) ($w * .15), (int) ($w * .5));
            $col = imagecolorallocatealpha($im, 255, 255, 255, mt_rand(100, 118));
            imagefilledellipse($im, mt_rand(0, $w), mt_rand(0, $h), $r, $r, $col);
        }
        $fuente = resource_path('fonts/Geist-Bold.ttf');
        $blanco = imagecolorallocate($im, 255, 255, 255);
        if ($tipo === 'avatar') {
            $tam = (int) ($w * .34);
            $box = imagettfbbox($tam, 0, $fuente, $texto);
            imagettftext($im, $tam, 0, (int) (($w - ($box[2] - $box[0])) / 2), (int) (($h + $tam) / 2), $blanco, $fuente, $texto);
        } else {
            $tam = (int) ($w * .085);
            $lineas = explode("\n", wordwrap($texto, 14, "\n", true));
            $y = (int) ($h * .62);
            foreach ($lineas as $l) {
                imagettftext($im, $tam, 0, (int) ($w * .08), $y, $blanco, $fuente, $l);
                $y += (int) ($tam * 1.25);
            }
            imagettftext($im, (int) ($w * .03), 0, (int) ($w * .08), (int) ($h * .92), $blanco, resource_path('fonts/Geist-Medium.ttf'), $pie);
        }
        ob_start(); imagejpeg($im, null, 86); $jpg = ob_get_clean();
        imagedestroy($im);
        Storage::disk('local')->put($ruta, $jpg);
        return $ruta;
    }
}
