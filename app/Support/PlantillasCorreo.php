<?php

namespace App\Support;

use App\Models\CorreoPlantilla;
use Throwable;

/**
 * Plantillas de correo: las de fábrica (config/vandu.php) con tus cambios encima, más las que crees.
 * Lo estructural de las de fábrica (PDF, datos bancarios, galería, a qué enlace lleva) no se edita.
 */
class PlantillasCorreo
{
    private static ?array $cache = null;

    /** Íconos para elegir en las plantillas propias */
    public const ICONOS = ['bi-envelope', 'bi-chat-heart', 'bi-calendar-check', 'bi-megaphone', 'bi-gift', 'bi-hand-thumbs-up', 'bi-telephone', 'bi-star'];

    /** Variables disponibles: qué significan y en qué correos tienen valor */
    public static function variables(): array
    {
        return [
            '{nombre}'       => ['texto' => 'Primer nombre del cliente', 'ejemplo' => 'Laura', 'para' => ['cliente', 'presupuesto', 'proyecto']],
            '{empresa}'      => ['texto' => 'Empresa del cliente', 'ejemplo' => 'Hotel Xcanatún', 'para' => ['cliente', 'presupuesto', 'proyecto']],
            '{firma}'        => ['texto' => 'Tu nombre', 'ejemplo' => config('vandu.emisor.nombre'), 'para' => ['cliente', 'presupuesto', 'proyecto']],
            '{folio}'        => ['texto' => 'Folio de la cotización', 'ejemplo' => 'CT-0020', 'para' => ['presupuesto', 'proyecto']],
            '{concepto}'     => ['texto' => 'Concepto principal', 'ejemplo' => 'Video promocional 60 s', 'para' => ['presupuesto', 'proyecto']],
            '{monto}'        => ['texto' => 'Importe de la cotización', 'ejemplo' => '$20,000.00 + IVA', 'para' => ['presupuesto', 'proyecto']],
            '{vigencia}'     => ['texto' => 'Vigente hasta', 'ejemplo' => '24 de octubre', 'para' => ['presupuesto', 'proyecto']],
            '{codigo}'       => ['texto' => 'Código para aceptar en línea (se genera al enviar, vale 24 h)', 'ejemplo' => '482 913', 'para' => ['presupuesto']],
            '{proyecto}'     => ['texto' => 'Nombre del proyecto', 'ejemplo' => 'Video corporativo', 'para' => ['proyecto']],
            '{siguiente}'    => ['texto' => 'Siguiente paso del proyecto', 'ejemplo' => 'grabación el 18 de octubre', 'para' => ['proyecto']],
            '{entregables}'  => ['texto' => 'Lo que se entrega', 'ejemplo' => '24 fotos y 2 videos', 'para' => ['proyecto']],
            '{pago}'         => ['texto' => 'Concepto del pago', 'ejemplo' => 'anticipo', 'para' => ['recordatorio']],
            '{monto_pago}'   => ['texto' => 'Monto del pago', 'ejemplo' => '$10,000.00', 'para' => ['recordatorio']],
            '{fecha_limite}' => ['texto' => 'Fecha límite (frase)', 'ejemplo' => ', con fecha límite el 30 de octubre', 'para' => ['recordatorio']],
        ];
    }

    /** Todas, con cambios aplicados. Incluye las desactivadas (marcadas con activa = false). */
    public static function todas(): array
    {
        if (self::$cache !== null) return self::$cache;

        $base = config('vandu.correo.plantillas', []);
        try {
            $guardadas = CorreoPlantilla::orderBy('id')->get()->keyBy('clave');
        } catch (Throwable $e) {
            $guardadas = collect(); // antes de migrar
        }

        $lista = [];
        foreach ($base as $clave => $pl) {
            $g = $guardadas->get($clave);
            $pl['fabrica'] = true;
            $pl['editada'] = (bool) $g;
            $pl['activa'] = $g ? $g->activa : true;
            if ($g) {
                foreach (['nombre', 'asunto', 'titulo', 'cuerpo'] as $campo) {
                    if ($g->$campo !== null) $pl[$campo] = $g->$campo;
                }
                if (array_key_exists('boton', $pl) || $g->boton !== null) $pl['boton'] = $g->boton;
            }
            $lista[$clave] = $pl;
        }
        foreach ($guardadas->where('propia', true) as $g) {
            $lista[$g->clave] = [
                'nombre' => $g->nombre, 'icono' => $g->icono ?: 'bi-envelope', 'para' => $g->para ?: ['cliente', 'presupuesto', 'proyecto'],
                'asunto' => (string) $g->asunto, 'titulo' => (string) $g->titulo, 'cuerpo' => (string) $g->cuerpo,
                'boton' => $g->boton, 'resumen' => $g->resumen,
                'fabrica' => false, 'editada' => false, 'activa' => $g->activa, 'id' => $g->id,
            ];
        }

        return self::$cache = $lista;
    }

    public static function activas(): array
    {
        return array_filter(self::todas(), fn ($pl) => $pl['activa']);
    }

    public static function una(string $clave): ?array
    {
        return self::todas()[$clave] ?? null;
    }

    public static function original(string $clave): ?array
    {
        return config("vandu.correo.plantillas.$clave");
    }

    public static function limpiar(): void
    {
        self::$cache = null;
    }

    /** Variables usadas en el texto que no tendrán valor según desde dónde se envíe */
    public static function variablesSinValor(array $pl, string $clave): array
    {
        $donde = $pl['para'] ?? [];
        if ($clave === 'recordatorio_pago') $donde[] = 'recordatorio';
        $texto = ($pl['asunto'] ?? '') . ' ' . ($pl['titulo'] ?? '') . ' ' . ($pl['cuerpo'] ?? '');
        preg_match_all('/\{[a-z_]+\}/', $texto, $m);
        $catalogo = self::variables();
        $faltan = [];
        foreach (array_unique($m[0]) as $v) {
            if (! isset($catalogo[$v])) { $faltan[$v] = 'no existe'; continue; }
            // Con que tenga valor en alguno de los lugares desde donde se envía basta para no avisar… salvo que en ninguno
            if (! array_intersect($catalogo[$v]['para'], $donde)) $faltan[$v] = 'queda vacía aquí';
        }
        return $faltan;
    }
}
