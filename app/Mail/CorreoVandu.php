<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo con el diseño de Agencia Vandu, enviado desde el panel */
class CorreoVandu extends Mailable
{
    use Queueable;

    /**
     * @param array<string,string> $resumen   filas del recuadro (etiqueta => valor)
     * @param array<string,string> $banco     datos bancarios (etiqueta => valor)
     * @param array<int, array{data: string, nombre: string, mime: string}> $archivos
     */
    public function __construct(
        public string $asunto,
        public string $titulo,
        public string $cuerpo,
        public ?string $boton = null,
        public ?string $url = null,
        public array $resumen = [],
        public array $banco = [],
        public array $archivos = [],
        public array $miniaturas = [],
        public int $mas = 0,
        public bool $vistaPrevia = false,
        public ?array $codigo = null, // ['formateado' => '482 913', 'vigencia' => '10 de octubre a las 9:56 pm']
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('vandu.correo.desde'), config('vandu.correo.nombre')),
            replyTo: [new Address(config('vandu.correo.responder_a'), config('vandu.emisor.nombre'))],
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vandu',
            text: 'emails.vandu-texto',
            with: [
                'parrafos' => self::parrafos($this->cuerpo),
                'preheader' => \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', strip_tags($this->cuerpo)), 110),
            ],
        );
    }

    public function attachments(): array
    {
        return array_map(fn ($a) => Attachment::fromData(fn () => $a['data'], $a['nombre'])->withMime($a['mime']), $this->archivos);
    }

    /** Párrafos separados por una línea en blanco; los saltos simples se respetan */
    public static function parrafos(string $texto): array
    {
        $bloques = preg_split("/\R\s*\R/", trim(str_replace("\r", '', $texto)));
        return array_values(array_filter(array_map('trim', $bloques), fn ($b) => $b !== ''));
    }
}
