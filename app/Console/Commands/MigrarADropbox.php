<?php

namespace App\Console\Commands;

use App\Models\ClienteConstancia;
use App\Models\ProyectoArchivo;
use App\Support\ArchivosProyecto;
use App\Support\Dropbox\Dropbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Pasa a Dropbox los archivos que todavía están en el servidor (se puede correr varias veces) */
class MigrarADropbox extends Command
{
    protected $signature = 'vandu:dropbox-migrar {--simular : Solo muestra qué se movería}';
    protected $description = 'Sube a Dropbox los archivos del panel que siguen en el servidor y libera el espacio';

    public function handle(): int
    {
        if (! Dropbox::conectado()) {
            $this->error('Dropbox no está conectado. Conéctalo primero en el panel (sección Dropbox).');
            return self::FAILURE;
        }
        $dbx = Dropbox::cliente();
        $disco = Storage::disk('local');
        $ok = 0; $fallas = 0;

        $archivos = ProyectoArchivo::with(['proyecto.cliente', 'proyecto.presupuesto', 'etapa'])->where('origen', 'local')->get();
        $this->info("Archivos de proyectos por migrar: {$archivos->count()}");
        foreach ($archivos as $a) {
            $local = $disco->path($a->ruta);
            if (! is_file($local)) { $this->warn("  · Falta en el servidor: {$a->nombre} (se omite)"); continue; }
            $carpeta = $a->grupo === 'galeria' && ! $a->visible
                ? ArchivosProyecto::carpetaOculta($a->proyecto)
                : ArchivosProyecto::carpetaDestino($a->proyecto, $a->grupo, $a->etapa);
            $this->line("  → {$a->nombre}  ⇒  $carpeta");
            if ($this->option('simular')) continue;
            try {
                $dbx->crearCarpeta($carpeta);
                $meta = $dbx->subirArchivo($local, $carpeta . '/' . ArchivosProyecto::nombreArchivo($a->nombre));
                $original = $a->ruta;
                $a->forceFill(['origen' => 'dropbox', 'dropbox_id' => $meta['id'], 'ruta' => $meta['path_display']])->saveQuietly();
                $disco->delete($original);
                $ok++;
            } catch (\Throwable $e) {
                $fallas++;
                $this->error("    No se pudo: " . $e->getMessage());
            }
        }

        $constancias = ClienteConstancia::with('cliente')->where('origen', 'local')->get();
        $this->info("Constancias por migrar: {$constancias->count()}");
        foreach ($constancias as $c) {
            $local = $disco->path($c->ruta);
            if (! is_file($local)) { $this->warn("  · Falta en el servidor: {$c->nombre} (se omite)"); continue; }
            $carpeta = Dropbox::raiz() . '/Clientes/' . Dropbox::nombreSeguro($c->cliente->empresa ?: $c->cliente->nombre) . '/Constancias';
            $this->line("  → {$c->nombre}  ⇒  $carpeta");
            if ($this->option('simular')) continue;
            try {
                $dbx->crearCarpeta($carpeta);
                $meta = $dbx->subirArchivo($local, $carpeta . '/' . ArchivosProyecto::nombreArchivo($c->nombre));
                $original = $c->ruta;
                $c->forceFill(['origen' => 'dropbox', 'dropbox_id' => $meta['id'], 'ruta' => $meta['path_display']])->saveQuietly();
                $disco->delete($original);
                $ok++;
            } catch (\Throwable $e) {
                $fallas++;
                $this->error("    No se pudo: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info($this->option('simular') ? 'Simulación terminada; no se movió nada.' : "Listo: $ok en Dropbox" . ($fallas ? ", $fallas con error (vuelve a correrlo)" : '') . '.');
        return $fallas ? self::FAILURE : self::SUCCESS;
    }
}
