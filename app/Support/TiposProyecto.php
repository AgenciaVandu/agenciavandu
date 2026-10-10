<?php

namespace App\Support;

use App\Models\ProyectoTipo;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tipos de proyecto: los de fábrica (config/vandu.php) con los cambios de la agencia encima, más los que cree.
 * Se mezclan en config('vandu.proyectos') al arrancar, así todo el panel (cotizaciones, proyectos, finanzas) los usa.
 */
class TiposProyecto
{
    public const ICONOS = ['bi-kanban', 'bi-window-stack', 'bi-camera-reels', 'bi-printer', 'bi-grid-3x3-gap', 'bi-megaphone', 'bi-palette', 'bi-brush', 'bi-calendar-event', 'bi-mic', 'bi-bag', 'bi-lightbulb', 'bi-people', 'bi-graph-up-arrow', 'bi-box-seam', 'bi-stars'];

    /** Los de fábrica del giro de la cuenta activa */
    public static function fabrica(): array
    {
        return Cuentas::proyectosDeFabrica();
    }

    public static function aplicar(): void
    {
        $base = self::fabrica();
        try {
            $guardados = ProyectoTipo::orderBy('id')->get();
        } catch (Throwable $e) {
            return; // antes de migrar
        }
        $todos = $base;
        foreach ($guardados as $t) {
            if (isset($base[$t->clave])) {
                $todos[$t->clave] = array_merge($base[$t->clave], array_filter([
                    'nombre' => $t->nombre, 'icono' => $t->icono, 'etapas' => $t->etapas, 'pagos' => $t->pagos,
                ], fn ($v) => $v !== null), ['editado' => true]);
            } elseif ($t->propio) {
                $todos[$t->clave] = array_merge($t->opciones ?? [], [
                    'nombre' => $t->nombre, 'icono' => $t->icono ?: 'bi-kanban', 'etapas' => $t->etapas, 'pagos' => $t->pagos, 'propio' => true,
                ]);
            }
        }
        config(['vandu.proyectos' => $todos]);
    }

    /** Clave única a partir de un nombre ("Reporte del mes" → "reporte-del-mes") */
    public static function clave(string $nombre, array $usadas): string
    {
        $base = Str::slug($nombre) ?: 'etapa';
        $base = Str::limit($base, 34, '');
        $c = $base; $i = 2;
        while (in_array($c, $usadas, true)) $c = $base . '-' . $i++;
        return $c;
    }
}
