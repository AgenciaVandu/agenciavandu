<?php

namespace App\Support;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraArchivo;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxError;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** Guarda y entrega los archivos de una orden de compra: en Dropbox si está conectado, si no en el servidor */
class ArchivosOrden
{
    /** Hasta 50 MB por archivo (catálogos y ponchados pueden pesar) */
    public const MAX_KB = 51200;

    public const EXTENSIONES = 'pdf,xml,jpg,jpeg,png,webp,heic,gif,svg,ai,eps,psd,cdr,dst,pes,exp,jef,emb,zip,rar,7z,doc,docx,xls,xlsx,csv,txt,mp4,mov';

    public static function reglas(array $tipos): array
    {
        return [
            'tipo'       => 'required|in:' . implode(',', $tipos),
            'archivos'   => 'required|array|min:1|max:10',
            'archivos.*' => ['file', 'max:' . self::MAX_KB, function ($attr, $v, $fail) {
                $ext = strtolower($v instanceof UploadedFile ? $v->getClientOriginalExtension() : '');
                if (! in_array($ext, explode(',', self::EXTENSIONES), true)) {
                    $fail('Ese tipo de archivo no se acepta. Usa PDF, XML, imágenes, ponchados (DST, PES…), diseño (AI, PSD, CDR) o ZIP.');
                }
            }],
        ];
    }

    public static function mensajes(): array
    {
        return [
            'archivos.required'     => 'Elige al menos un archivo.',
            'archivos.max'          => 'Sube máximo 10 archivos a la vez.',
            'archivos.*.max'        => 'Cada archivo puede pesar hasta 50 MB.',
            'archivos.*.uploaded'   => 'El servidor no aceptó el archivo; puede ser muy pesado. Intenta con uno más ligero o en ZIP.',
            'tipo.required'         => 'Elige qué tipo de archivo es.',
        ];
    }

    /** @return OrdenCompraArchivo[] */
    public static function guardar(OrdenCompra $o, Request $request, string $autor, ?int $userId = null): array
    {
        $guardados = [];
        foreach ((array) $request->file('archivos') as $archivo) {
            if ($archivo instanceof UploadedFile && $archivo->isValid()) {
                $guardados[] = static::guardarUno($o, $archivo, $request->input('tipo'), $autor, $userId);
            }
        }
        return $guardados;
    }

    public static function guardarUno(OrdenCompra $o, UploadedFile $archivo, string $tipo, string $autor, ?int $userId = null): OrdenCompraArchivo
    {
        $nombre = $archivo->getClientOriginalName() ?: 'archivo';
        $origen = 'local';
        $dropboxId = null;

        if (Dropbox::conectado()) {
            $dbx = Dropbox::cliente();
            $carpeta = $o->carpeta_dropbox . '/' . ($tipo === 'factura' ? 'Facturas' : ($autor === 'agencia' ? 'Enviados' : 'Del proveedor'));
            try {
                $dbx->crearCarpeta($carpeta);
                $meta = $dbx->subirArchivo($archivo->getRealPath(), $carpeta . '/' . ArchivosProyecto::nombreArchivo($nombre));
            } catch (DropboxError $e) {
                throw ValidationException::withMessages(['archivos' => 'No se pudo guardar en Dropbox: ' . $e->getMessage()]);
            }
            [$ruta, $origen, $dropboxId] = [$meta['path_display'], 'dropbox', $meta['id'] ?? null];
        } else {
            $ext = strtolower($archivo->getClientOriginalExtension() ?: 'bin');
            $ruta = $archivo->storeAs("proveedores/{$o->proveedor_id}/ordenes/{$o->id}", Str::uuid() . '.' . $ext, 'local');
        }

        return $o->archivos()->create([
            'tipo'       => $tipo,
            'autor'      => $autor,
            'user_id'    => $userId,
            'nombre'     => $nombre,
            'ruta'       => $ruta,
            'origen'     => $origen,
            'dropbox_id' => $dropboxId,
            'mime'       => $archivo->getMimeType() ?: ArchivosProyecto::mimeDe($nombre),
            'peso'       => (int) $archivo->getSize(),
        ]);
    }

    /** Abre o descarga el archivo */
    public static function respuesta(OrdenCompraArchivo $a, bool $descargar = false): Response
    {
        if ($a->origen === 'dropbox') {
            try {
                $url = Dropbox::cliente()->enlaceTemporal($a->dropbox_id ?: $a->ruta);
            } catch (DropboxError $e) {
                abort(404, $e->getMessage());
            }
            return redirect()->away($url);
        }
        $disco = Storage::disk('local');
        abort_unless($disco->exists($a->ruta), 404);
        // Solo PDF e imágenes se abren en el navegador; lo demás se descarga
        $enLinea = ! $descargar && (str_contains((string) $a->mime, 'pdf') || (str_starts_with((string) $a->mime, 'image/') && ! str_contains((string) $a->mime, 'svg')));
        $resp = response()->file($disco->path($a->ruta), [
            'Content-Type'           => $enLinea ? $a->mime : 'application/octet-stream',
            'Cache-Control'          => 'private, no-store',
            'X-Robots-Tag'           => 'noindex',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $resp->setContentDisposition($enLinea ? 'inline' : 'attachment', $a->nombre, Str::ascii($a->nombre) ?: 'archivo');
        return $resp;
    }
}
