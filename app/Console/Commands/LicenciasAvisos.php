<?php

namespace App\Console\Commands;

use App\Mail\CorreoVandu;
use App\Models\Cuenta;
use App\Models\Licencia;
use App\Models\User;
use App\Support\Licencias\Estado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos de vencimiento de licencia (una vez al día): al dueño de cada negocio 7, 3 y 1 días antes, el día que vence,
 * al entrar en gracia, al pasar a solo lectura y al suspenderse. Cada aviso sale una sola vez por fecha de vencimiento.
 * El bloqueo no depende de este comando: el panel calcula el estado al vuelo.
 */
class LicenciasAvisos extends Command
{
    protected $signature = 'vandu:licencias';
    protected $description = 'Avisa por correo a los negocios cuyo plan está por vencer o ya venció';

    public function handle(): int
    {
        $pago = Estado::datosPago();
        foreach (Licencia::with('plan', 'cuenta')->whereIn('tipo', ['suscripcion', 'perpetua'])->whereNotNull('vence_el')->get() as $l) {
            $c = $l->cuenta;
            if (! $c || ! $c->activa) continue;
            Estado::olvidar();
            $e = Estado::de($c);
            $etapa = match ($e->clave) {
                'por_vencer' => $e->dias >= 4 ? 'v7' : ($e->dias >= 2 ? 'v3' : ($e->dias === 1 ? 'v1' : 'v0')),
                'gracia', 'lectura', 'suspendida' => $e->clave,
                'mantenimiento' => 'mant',
                default => null,
            };
            if (! $etapa) continue;
            $clave = $l->vence_el->toDateString();
            $avisos = $l->avisos ?? [];
            if (in_array($etapa, $avisos[$clave] ?? [], true)) continue;

            $para = User::sinCuenta()->where('cuenta_id', $c->id)->where('activo', true)->whereHas('rol', fn ($q) => $q->where('todo', true))->get();
            $frase = Estado::frase($e);
            $cuerpo = $frase . "\n\n" . match ($etapa) {
                'v7', 'v3', 'v1', 'v0' => 'Renuévalo para que tu equipo siga trabajando sin interrupciones.',
                'gracia' => 'Todavía puedes trabajar normal unos días más; renueva para evitar que el panel quede en solo lectura.',
                'lectura' => 'Tu equipo puede ver y descargar todo, pero no registrar ventas, cotizaciones ni gastos hasta que se renueve.',
                'suspendida' => 'Tu información está guardada y completa. En cuanto se registre el pago, tu equipo vuelve a entrar con sus mismos usuarios.',
                default => '',
            } . ($l->tipo === 'suscripcion' ? "\n\nRenovación " . ($l->periodo === 'anual' ? 'anual' : 'mensual') . ': $' . number_format($l->precio, 2) . '.' : '')
              . (! empty($pago['clabe']) ? "\nTransferencia a la CLABE {$pago['clabe']}" . (! empty($pago['banco']) ? " ({$pago['banco']})" : '') . (! empty($pago['titular']) ? " a nombre de {$pago['titular']}" : '') . '.' : '')
              . (! empty($pago['whatsapp']) ? "\nManda tu comprobante por WhatsApp al {$pago['whatsapp']}." : '');
            $asunto = match ($etapa) {
                'v7', 'v3', 'v1' => "Tu plan de {$c->nombre} vence pronto",
                'v0' => "Tu plan de {$c->nombre} vence hoy",
                'gracia' => "Tu plan de {$c->nombre} venció",
                'lectura' => "{$c->nombre}: el panel quedó en solo lectura",
                'suspendida' => "{$c->nombre}: acceso al panel suspendido",
                'mant' => "{$c->nombre}: renueva tu mantenimiento",
            };
            $url = ($c->url_base ?: rtrim(config('app.url'), '/')) . '/admin';
            $enviados = 0;
            foreach ($para as $u) {
                try {
                    Mail::to($u->email, $u->name)->send(new CorreoVandu(asunto: $asunto, titulo: $asunto, cuerpo: "Hola {$u->primer_nombre},\n\n" . $cuerpo,
                        boton: $etapa === 'suspendida' ? null : 'Abrir el panel', url: $etapa === 'suspendida' ? null : $url));
                    $enviados++;
                } catch (\Throwable $ex) {
                    report($ex);
                }
            }
            $avisos = [$clave => array_values(array_unique(array_merge($avisos[$clave] ?? [], [$etapa])))];   // solo se guarda el vencimiento actual
            $l->forceFill(['avisos' => $avisos])->saveQuietly();
            $this->line("{$c->nombre}: aviso '$etapa' a $enviados " . ($enviados === 1 ? 'persona' : 'personas'));
        }
        return self::SUCCESS;
    }
}
