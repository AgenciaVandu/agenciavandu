<?php

namespace App\Support\Facturas;

use App\Mail\CorreoVandu;
use App\Models\Correo;
use App\Support\Correos;
use App\Support\PlantillasCorreo;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Envía la factura al cliente (correo con el PDF y el XML adjuntos, o WhatsApp con enlaces) y la marca como enviada */
class Enviar
{
    /** @return array{0: bool, 1: string} [si se envió, mensaje] */
    public static function correo(Documento $doc, ?string $para = null): array
    {
        $archivos = $doc->archivos();
        if ($archivos->isEmpty()) return [false, "{$doc->folio()}: aún no tiene factura."];
        $para = trim((string) ($para ?: $doc->correo()));
        if (! filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $para)) return [false, "{$doc->folio()}: el cliente no tiene correo."];

        $pl = PlantillasCorreo::una('factura') ?? config('vandu.correo.plantillas.factura');
        $vars = self::variables($doc);
        $t = fn (?string $x) => strtr((string) $x, $vars);
        $correo = new CorreoVandu(
            asunto: $t($pl['asunto'] ?? 'Factura · {concepto}'),
            titulo: $t($pl['titulo'] ?? 'Tu factura está lista'),
            cuerpo: $t($pl['cuerpo'] ?? ''),
            codigo: null, boton: null, url: null, resumen: [], banco: [],
            archivos: $archivos->map(fn ($f) => ['data' => (string) $f->contenido(), 'nombre' => $f->nombre, 'mime' => $f->mime ?: 'application/octet-stream'])->all(),
            miniaturas: [], mas: 0, vistaPrevia: false,
        );
        $registro = new Correo(['cliente_id' => $doc->cliente()?->id, 'presupuesto_id' => $doc->tipo() === 'presupuesto' ? $doc->m->id : null,
            'plantilla' => 'factura', 'para' => $para, 'asunto' => $correo->asunto, 'cuerpo' => $t($pl['cuerpo'] ?? ''), 'adjuntos' => $archivos->pluck('nombre')->all()]);
        try {
            Mail::to($para)->send($correo);
        } catch (\Throwable $e) {
            report($e);
            $registro->fill(['estado' => 'fallido', 'error' => Str::limit($e->getMessage(), 900)])->save();
            return [false, "{$doc->folio()}: no se pudo enviar (" . Str::limit($e->getMessage(), 80) . ').'];
        }
        $registro->fill(['estado' => 'enviado'])->save();
        self::marcar($doc, 'correo a ' . \App\Support\Aceptacion::correoOculto($para));
        return [true, "{$doc->folio()}: enviada a {$para}."];
    }

    /** Complemento de pago o nota de crédito por correo, con su PDF y XML */
    public static function cfdi(Documento $doc, $archivos, string $que): array
    {
        $para = trim((string) $doc->correo());
        if (! filter_var($para, FILTER_VALIDATE_EMAIL)) return [false, 'El cliente no tiene correo para facturas.'];
        $f = $archivos->first();
        $folio = trim(($f->serie ?? '') . '-' . ($f->folio ?? ''), '-');
        $nombre = Correos::primerNombre((string) ($doc->cliente()?->nombre ?: $doc->quien()));
        $cuerpo = "Hola {$nombre}, te enviamos tu " . mb_strtolower($que) . " {$folio}"
            . ($f->tipo === 'complemento' ? ' por tu pago de $' . number_format((float) $f->monto, 2) . " a la compra {$doc->folio()}." : " de la compra {$doc->folio()} por $" . number_format((float) $f->monto, 2) . '.')
            . "\n\nVa adjunto en PDF y XML. Gracias.";
        $correo = new CorreoVandu(asunto: "$que $folio", titulo: "Tu " . mb_strtolower($que), cuerpo: $cuerpo, codigo: null, boton: null, url: null, resumen: [], banco: [],
            archivos: $archivos->map(fn ($x) => ['data' => (string) $x->contenido(), 'nombre' => $x->nombre, 'mime' => $x->mime ?: 'application/octet-stream'])->all(),
            miniaturas: [], mas: 0, vistaPrevia: false);
        try {
            Mail::to($para)->send($correo);
        } catch (\Throwable $e) {
            report($e);
            return [false, 'No se pudo enviar (' . Str::limit($e->getMessage(), 80) . ').'];
        }
        Correo::create(['cliente_id' => $doc->cliente()?->id, 'plantilla' => 'factura', 'para' => $para, 'asunto' => "$que $folio", 'cuerpo' => $cuerpo,
            'adjuntos' => $archivos->pluck('nombre')->all(), 'estado' => 'enviado']);
        $doc->relacionArchivos()->whereIn('id', $archivos->pluck('id'))->update(['enviada_at' => now()]);
        return [true, "$que enviado a $para."];
    }

    /** Enlace de WhatsApp con los enlaces de descarga (WhatsApp no adjunta archivos desde un enlace) */
    public static function whatsapp(Documento $doc): ?string
    {
        $wa = $doc->whatsapp();
        $archivos = $doc->archivos();
        if (! $wa || $archivos->isEmpty()) return null;
        $nombre = Correos::primerNombre((string) ($doc->cliente()?->nombre ?: $doc->quien()));
        $msg = "Hola {$nombre}, te comparto tu factura de {$doc->folio()}:";
        foreach ($archivos as $f) $msg .= "\n• " . ($f->ext === 'xml' ? 'XML' : ($f->ext === 'pdf' ? 'PDF' : $f->nombre)) . ': ' . $doc->enlaceArchivo($f);
        $msg .= "\n\nGracias por tu confianza.";
        self::marcar($doc, 'WhatsApp');
        return 'https://wa.me/' . $wa . '?text=' . rawurlencode($msg);
    }

    private static function marcar(Documento $doc, string $canal): void
    {
        $doc->relacionArchivos()->whereIn('id', $doc->archivos()->pluck('id'))->update(['enviada_at' => now()]);
        if ($doc->tipo() === 'presupuesto') {
            \App\Support\Aceptacion::registrar($doc->m, 'factura', 'agencia', null, 'Factura enviada por ' . $canal, ['canal' => Str::before($canal, ' ')], false);
        }
    }

    private static function variables(Documento $doc): array
    {
        $m = $doc->m;
        $concepto = $m instanceof \App\Models\Presupuesto
            ? Str::limit($m->conceptos->first()?->resumen ?: $m->titulo, 60)
            : 'tu compra ' . $m->folio;
        return [
            '{nombre}' => Correos::primerNombre((string) ($doc->cliente()?->nombre ?: $doc->quien())), '{empresa}' => (string) $doc->cliente()?->empresa,
            '{folio}' => $doc->folio(), '{concepto}' => $concepto, '{monto}' => '$' . number_format($doc->total(), 2),
            '{firma}' => (string) config('vandu.emisor.nombre'), '{codigo}' => '', '{vigencia}' => '', '{proyecto}' => '', '{pago}' => '', '{monto_pago}' => '',
            '{fecha_limite}' => '', '{entregables}' => '', '{solicitudes}' => '', '{siguiente}' => '',
        ];
    }
}
