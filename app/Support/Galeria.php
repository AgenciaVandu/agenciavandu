<?php

namespace App\Support;

use App\Models\GaleriaSeccion;
use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Support\Dropbox\Dropbox;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Secciones de la galería de un proyecto. En Dropbox cada sección es una subcarpeta
 * de "Galería" (lo publicado) y de "No publicado" (lo oculto): Galería/Fotos adicionales/foto.jpg
 */
class Galeria
{
    public const PRINCIPAL = 'Entrega principal';

    /** Carpeta de Dropbox de una sección (o de la raíz si no hay sección) */
    public static function carpeta(Proyecto $p, ?GaleriaSeccion $s, bool $visible = true): string
    {
        return ($visible ? ArchivosProyecto::carpetaGaleria($p) : ArchivosProyecto::carpetaOculta($p)) . ($s ? '/' . $s->carpeta : '');
    }

    /** La sección donde cae lo nuevo si no se elige otra: la más reciente */
    public static function porDefecto(Proyecto $p): ?GaleriaSeccion
    {
        return $p->secciones()->first();
    }

    /**
     * Antes de la primera sección nueva: lo que ya había en la galería se agrupa en "Entrega principal"
     * (en Dropbox se mueve a su subcarpeta) para que nada quede suelto.
     */
    public static function asegurar(Proyecto $p): ?GaleriaSeccion
    {
        $sueltos = $p->archivos()->where('grupo', 'galeria')->whereNull('seccion_id')->get();
        if ($sueltos->isEmpty()) return null;
        $s = $p->secciones()->where('nombre', self::PRINCIPAL)->first() ?? GaleriaSeccion::create([
            'proyecto_id' => $p->id, 'nombre' => self::PRINCIPAL, 'carpeta' => self::PRINCIPAL, 'orden' => 0,
        ]);
        if ($s->wasRecentlyCreated) {
            $s->forceFill(['created_at' => $sueltos->min('created_at'), 'updated_at' => $sueltos->min('created_at')])->saveQuietly();
        }
        foreach ($sueltos as $a) self::mover($a, $s);
        return $s;
    }

    /** Sección nueva (arriba de todas) */
    public static function nueva(Proyecto $p, string $nombre): GaleriaSeccion
    {
        self::asegurar($p);
        $nombre = trim($nombre) ?: 'Entrega';
        $usadas = $p->secciones()->pluck('carpeta')->map(fn ($c) => mb_strtolower($c))->all();
        $base = \Illuminate\Support\Str::limit(Dropbox::nombreSeguro($nombre), 80, '');
        $carpeta = $base; $i = 2;
        while (in_array(mb_strtolower($carpeta), $usadas, true)) {
            $carpeta = "$base ($i)"; $i++;
        }
        return GaleriaSeccion::create([
            'proyecto_id' => $p->id, 'nombre' => \Illuminate\Support\Str::limit($nombre, 120, ''), 'carpeta' => $carpeta,
            'orden' => (int) $p->secciones()->max('orden') + 1,
        ]);
    }

    /** Cambia un archivo de sección (en Dropbox lo mueve de subcarpeta) */
    public static function mover(ProyectoArchivo $a, ?GaleriaSeccion $s): void
    {
        if ($a->origen === 'dropbox' && Dropbox::conectado()) {
            $destino = self::carpeta($a->proyecto, $s, (bool) $a->visible);
            Dropbox::cliente()->crearCarpeta($destino);
            $meta = Dropbox::cliente()->mover($a->dropbox_id, $destino . '/' . basename($a->ruta));
            $a->ruta = $meta['path_display'] ?? $a->ruta;
        }
        $a->seccion_id = $s?->id;
        $a->save();
        if ($s) $s->forceFill(['zip_url' => null])->saveQuietly();
    }

    public static function renombrar(GaleriaSeccion $s, string $nombre): void
    {
        $nombre = trim($nombre);
        if ($nombre === '' || $nombre === $s->nombre) return;
        $p = $s->proyecto;
        $nueva = \Illuminate\Support\Str::limit(Dropbox::nombreSeguro($nombre), 80, '');
        if ($nueva !== $s->carpeta && $p->secciones()->where('id', '!=', $s->id)->whereRaw('lower(carpeta) = ?', [mb_strtolower($nueva)])->exists()) {
            $nueva .= ' (' . $s->id . ')';
        }
        if ($nueva !== $s->carpeta && Dropbox::conectado() && $s->archivos()->where('origen', 'dropbox')->exists()) {
            $dbx = Dropbox::cliente();
            foreach ([true, false] as $visible) {
                $desde = self::carpeta($p, $s, $visible);
                $hacia = dirname($desde) . '/' . $nueva;
                try { $dbx->metadata($desde); } catch (Throwable) { continue; } // no hay nada en esa carpeta
                $dbx->mover($desde, $hacia);
                foreach ($s->archivos()->get() as $a) {
                    if (str_starts_with(mb_strtolower($a->ruta), mb_strtolower($desde . '/'))) {
                        $a->forceFill(['ruta' => $hacia . mb_substr($a->ruta, mb_strlen($desde))])->saveQuietly();
                    }
                }
            }
        }
        $s->update(['nombre' => \Illuminate\Support\Str::limit($nombre, 120, ''), 'carpeta' => $nueva, 'zip_url' => null]);
    }

    /** Subir o bajar una sección en el orden que ve el cliente */
    public static function reordenar(GaleriaSeccion $s, int $dir): void
    {
        $lista = $s->proyecto->secciones()->get()->values(); // de la más nueva a la más vieja
        $i = $lista->search(fn ($x) => $x->id === $s->id);
        $j = $i - $dir; // "subir" = ir hacia el principio de la lista
        if ($i === false || $j < 0 || $j >= $lista->count()) return;
        $otra = $lista[$j];
        [$a, $b] = [$s->orden, $otra->orden];
        if ($a === $b) { $b = $a + ($dir > 0 ? 1 : -1); }
        $s->update(['orden' => $b]);
        $otra->update(['orden' => $a]);
    }

    /**
     * Galería agrupada por sección, la más nueva primero.
     * @return Collection<int, array{seccion: ?GaleriaSeccion, nombre: string, archivos: Collection, fecha: mixed}>
     */
    public static function agrupada(Proyecto $p, bool $soloVisibles): Collection
    {
        $archivos = $p->archivos()->where('grupo', 'galeria')->when($soloVisibles, fn ($q) => $q->where('visible', true))->get();
        $grupos = $p->secciones()->get()->map(fn ($s) => [
            'seccion' => $s, 'nombre' => $s->nombre, 'archivos' => $archivos->where('seccion_id', $s->id)->values(), 'fecha' => $s->created_at,
        ]);
        $sueltos = $archivos->whereNull('seccion_id')->values();
        if ($sueltos->isNotEmpty()) {
            $grupos->push(['seccion' => null, 'nombre' => $grupos->isEmpty() ? 'Galería' : self::PRINCIPAL, 'archivos' => $sueltos, 'fecha' => $sueltos->min('created_at')]);
        }
        return $soloVisibles ? $grupos->filter(fn ($g) => $g['archivos']->isNotEmpty())->values() : $grupos->values();
    }
}
