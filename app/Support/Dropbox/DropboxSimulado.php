<?php

namespace App\Support\Dropbox;

use Illuminate\Support\Str;

/**
 * Dropbox de mentira sobre una carpeta local (storage/app/dropbox-simulado).
 * Solo para desarrollo y pruebas: VANDU_DROPBOX_SIMULADO=true. Nunca en producción.
 */
class DropboxSimulado extends Dropbox
{
    public static function base(): string
    {
        $b = storage_path('app/dropbox-simulado');
        if (! is_dir($b)) mkdir($b, 0775, true);
        return $b;
    }

    private function indice(): array
    {
        $f = self::base() . '/.indice.json';
        return is_file($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
    }

    private function guardarIndice(array $i): void
    {
        file_put_contents(self::base() . '/.indice.json', json_encode($i, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /** Ruta "/Vandu/x.jpg" a partir de una ruta o un id */
    public function ruta(string $rutaOId): string
    {
        if (str_starts_with($rutaOId, 'id:')) {
            return $this->indice()[$rutaOId] ?? throw new DropboxError('not_found', 'path/not_found/');
        }
        return '/' . ltrim($rutaOId, '/');
    }

    private function fisica(string $ruta): string
    {
        return self::base() . $ruta;
    }

    private function idDe(string $ruta): string
    {
        $i = $this->indice();
        $id = array_search($ruta, $i, true);
        if ($id === false) { $id = 'id:' . Str::random(16); $i[$id] = $ruta; $this->guardarIndice($i); }
        return $id;
    }

    private function meta(string $ruta): array
    {
        $f = $this->fisica($ruta);
        if (is_dir($f)) return ['.tag' => 'folder', 'name' => basename($ruta), 'path_display' => $ruta, 'path_lower' => mb_strtolower($ruta), 'id' => $this->idDe($ruta)];
        if (! is_file($f)) throw new DropboxError('not_found', 'path/not_found/');
        return ['.tag' => 'file', 'name' => basename($ruta), 'path_display' => $ruta, 'path_lower' => mb_strtolower($ruta), 'id' => $this->idDe($ruta), 'size' => filesize($f), 'server_modified' => gmdate('Y-m-d\\TH:i:s\\Z', filemtime($f))];
    }

    /** El navegador "sube" a esta ruta en pruebas (lo usa la prueba de Playwright) */
    public function registrarSubida(string $ruta): array
    {
        return $this->meta($ruta);
    }

    public function token(): string { return 'simulado'; }
    public function tokenNavegador(): array { return ['token' => 'simulado', 'expira' => time() + 3600, 'api' => url('/_dropbox-simulado/api') . '/']; }
    public function desconectar(): void {}

    public function metadata(string $rutaOId): array { return $this->meta($this->ruta($rutaOId)); }

    public function crearCarpeta(string $ruta): void
    {
        $f = $this->fisica($ruta);
        if (! is_dir($f)) mkdir($f, 0775, true);
    }

    public function listar(string $ruta, bool $recursivo = false): array
    {
        $f = $this->fisica($ruta === '/' ? '' : $ruta);
        if (! is_dir($f)) return [];
        $out = [];
        foreach (scandir($f) as $n) {
            if ($n[0] === '.') continue;
            $r = rtrim($ruta, '/') . '/' . $n;
            $out[] = $this->meta($r);
            if ($recursivo && is_dir($this->fisica($r))) $out = array_merge($out, $this->listar($r, true));
        }
        return $out;
    }

    public function buscar(string $texto, string $ruta = ''): array
    {
        $t = mb_strtolower($texto);
        return array_values(array_filter($this->listar($ruta ?: '/', true), fn ($e) => str_contains(mb_strtolower($e['name']), $t)));
    }

    private function destinoLibre(string $destino): string
    {
        $i = 1; $d = $destino;
        while (file_exists($this->fisica($d))) {
            $d = dirname($destino) . '/' . pathinfo($destino, PATHINFO_FILENAME) . " ($i)" . (pathinfo($destino, PATHINFO_EXTENSION) ? '.' . pathinfo($destino, PATHINFO_EXTENSION) : '');
            $i++;
        }
        return $d;
    }

    public function subirArchivo(string $rutaLocal, string $destino): array
    {
        return $this->subirContenido(file_get_contents($rutaLocal), $destino);
    }

    private function sesionArchivo(string $sesion): string
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{12}$/', $sesion), 422);
        $dir = self::base() . '/.sesiones';
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        return "$dir/$sesion";
    }

    public function sesionIniciar(string $parte): string
    {
        $id = Str::random(12);
        file_put_contents($this->sesionArchivo($id), $parte);
        return $id;
    }

    public function sesionAgregar(string $sesion, int $offset, string $parte): void
    {
        $f = $this->sesionArchivo($sesion);
        if (! is_file($f) || filesize($f) !== $offset) throw new DropboxError('incorrect_offset', 'incorrect_offset/');
        file_put_contents($f, $parte, FILE_APPEND);
    }

    public function sesionTerminar(string $sesion, int $offset, string $parte, string $destino): array
    {
        $this->sesionAgregar($sesion, $offset, $parte);
        $f = $this->sesionArchivo($sesion);
        $m = $this->subirArchivo($f, $destino);
        unlink($f);
        return $m;
    }

    public function subirContenido(string $contenido, string $destino): array
    {
        $d = $this->destinoLibre($destino);
        if (! is_dir(dirname($this->fisica($d)))) mkdir(dirname($this->fisica($d)), 0775, true);
        file_put_contents($this->fisica($d), $contenido);
        return $this->meta($d);
    }

    public function mover(string $desde, string $hacia): array
    {
        $origen = $this->ruta($desde);
        $id = $this->idDe($origen);
        $d = $this->destinoLibre($hacia);
        if (! is_dir(dirname($this->fisica($d)))) mkdir(dirname($this->fisica($d)), 0775, true);
        rename($this->fisica($origen), $this->fisica($d));
        $i = $this->indice(); $i[$id] = $d; $this->guardarIndice($i);
        return $this->meta($d);
    }

    public function borrar(string $rutaOId): void
    {
        try { $r = $this->ruta($rutaOId); } catch (DropboxError) { return; }
        $f = $this->fisica($r);
        if (is_file($f)) unlink($f);
    }

    public function enlaceTemporal(string $rutaOId): string
    {
        return route('dropbox.simulado', ['id' => $this->metadata($rutaOId)['id']]);
    }

    public function miniatura(string $rutaOId, string $tamano = 'w640h480'): string
    {
        $f = $this->fisica($this->ruta($rutaOId));
        $img = @imagecreatefromstring((string) file_get_contents($f));
        if (! $img) throw new DropboxError('unsupported_extension', 'unsupported_extension');
        preg_match('/w(\d+)h(\d+)/', $tamano, $m);
        $w = imagesx($img); $h = imagesy($img);
        $esc = min(1, (int) $m[1] / $w, (int) $m[2] / $h);
        $lienzo = imagecreatetruecolor(max(1, (int) ($w * $esc)), max(1, (int) ($h * $esc)));
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, imagesx($lienzo), imagesy($lienzo), $w, $h);
        ob_start(); imagejpeg($lienzo, null, 80); return (string) ob_get_clean();
    }

    public function enlaceCarpeta(string $ruta): string
    {
        return route('dropbox.simulado', ['id' => $this->idDe($ruta), 'zip' => 1]);
    }

    /** Sirve el archivo (o la carpeta en ZIP) para la ruta de pruebas */
    public function responder(string $id, bool $zip)
    {
        $r = $this->ruta($id);
        $f = $this->fisica($r);
        if ($zip && is_dir($f)) {
            $tmp = tempnam(sys_get_temp_dir(), 'dbx');
            $z = new \ZipArchive(); $z->open($tmp, \ZipArchive::OVERWRITE);
            foreach (scandir($f) as $n) if ($n[0] !== '.' && is_file("$f/$n")) $z->addFile("$f/$n", $n);
            $z->close();
            return response()->download($tmp, basename($r) . '.zip')->deleteFileAfterSend();
        }
        abort_unless(is_file($f), 404);
        return response()->file($f);
    }
}
