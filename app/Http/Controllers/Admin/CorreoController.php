<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Correo;
use App\Support\Correos;
use App\Support\PresupuestoPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CorreoController extends Controller
{
    /** HTML del correo tal como lo va a recibir el cliente */
    public function vistaPrevia(Request $request)
    {
        [$ctx, $datos] = $this->validar($request, false);

        return response($this->armar($ctx, $datos, true)->render())
            ->header('X-Robots-Tag', 'noindex');
    }

    public function enviar(Request $request)
    {
        [$ctx, $datos] = $this->validar($request, true);
        $para = $this->correos($datos['para']);
        $cc = $this->correos($datos['cc'] ?? '');
        $correo = $this->armar($ctx, $datos);

        $registro = new Correo([
            'cliente_id'     => $ctx['cliente']?->id,
            'presupuesto_id' => $ctx['presupuesto']?->id,
            'proyecto_id'    => $ctx['proyecto']?->id,
            'plantilla'      => Str::before($datos['plantilla'], '@'),
            'para'           => implode(', ', $para),
            'cc'             => $cc ? implode(', ', $cc) : null,
            'asunto'         => $datos['asunto'],
            'cuerpo'         => $datos['cuerpo'],
            'adjuntos'       => array_column($correo->archivos, 'nombre') ?: null,
        ]);

        try {
            Mail::to($para)->cc($cc)->send($correo);
            $registro->estado = 'enviado';
            $registro->save();
        } catch (\Throwable $e) {
            report($e);
            $registro->estado = 'fallido';
            $registro->error = Str::limit($e->getMessage(), 900);
            $registro->save();

            return back()->withErrors(['correo' => 'No se pudo enviar el correo: ' . Str::limit($e->getMessage(), 160) . ' Revisa la configuración de correo del servidor.']);
        }

        return back()->with('ok', 'Correo enviado a ' . implode(', ', $para) . '.');
    }

    private function armar(array $ctx, array $d, bool $vistaPrevia = false): CorreoVandu
    {
        $pagoId = Str::contains($d['plantilla'], '@') ? (int) Str::after($d['plantilla'], '@') : null;
        $archivos = [];
        if (! empty($d['adjuntar_pdf']) && $ctx['presupuesto']) {
            $p = $ctx['presupuesto'];
            $archivos[] = [
                'data'   => $vistaPrevia ? '' : PresupuestoPdf::generar($p)->output(),
                'nombre' => $p->nombre_archivo,
                'mime'   => 'application/pdf',
            ];
        }

        $url = Correos::enlace($ctx);
        $correo = new CorreoVandu(
            asunto: $d['asunto'],
            titulo: (string) ($d['titulo'] ?? ''),
            cuerpo: $d['cuerpo'],
            boton: ! empty($d['incluir_boton']) && $url ? ($d['boton'] ?: 'Ver detalles') : null,
            url: $url,
            resumen: ! empty($d['incluir_resumen']) ? Correos::resumen($ctx, $pagoId) : [],
            banco: ! empty($d['incluir_banco']) ? Correos::banco($ctx['presupuesto']) : [],
            archivos: $archivos,
            vistaPrevia: $vistaPrevia,
        );
        return $correo;
    }

    /** @return array{0: array, 1: array} */
    private function validar(Request $request, bool $enviar): array
    {
        $d = $request->validate([
            'contexto_tipo'   => ['required', Rule::in(Correos::CONTEXTOS)],
            'contexto_id'     => 'required|integer',
            'plantilla'       => 'required|string|max:60',
            'para'            => [$enviar ? 'required' : 'nullable', 'string', 'max:500'],
            'cc'              => 'nullable|string|max:500',
            'asunto'          => [$enviar ? 'required' : 'nullable', 'string', 'max:200'],
            'titulo'          => 'nullable|string|max:120',
            'cuerpo'          => [$enviar ? 'required' : 'nullable', 'string', 'max:10000'],
            'boton'           => 'nullable|string|max:60',
            'incluir_boton'   => 'nullable|boolean',
            'incluir_resumen' => 'nullable|boolean',
            'incluir_banco'   => 'nullable|boolean',
            'adjuntar_pdf'    => 'nullable|boolean',
        ], [
            'para.required'   => 'Escribe a quién va el correo.',
            'asunto.required' => 'El correo necesita un asunto.',
            'cuerpo.required' => 'El correo necesita un mensaje.',
        ]);
        $d['asunto'] = (string) ($d['asunto'] ?? '');
        $d['cuerpo'] = (string) ($d['cuerpo'] ?? '');
        $d['boton'] = $d['boton'] ?? null;

        return [Correos::contexto($d['contexto_tipo'], (int) $d['contexto_id']), $d];
    }

    /** "a@x.com, b@y.com" → lista validada */
    private function correos(string $texto): array
    {
        $lista = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $texto))));
        foreach ($lista as $c) {
            if (! filter_var($c, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['para' => "“{$c}” no es un correo válido."]);
            }
        }
        return $lista;
    }
}
