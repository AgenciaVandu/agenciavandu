<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\ProyectoPago;
use Illuminate\Support\Str;

/**
 * Arma lo que necesita un correo según desde dónde se envía (cliente, cotización o proyecto):
 * plantillas con las variables ya sustituidas, destinatario, enlace, resumen y datos bancarios.
 */
class Correos
{
    public const CONTEXTOS = ['cliente', 'presupuesto', 'proyecto'];

    /** @return array{tipo: string, cliente: ?Cliente, presupuesto: ?Presupuesto, proyecto: ?Proyecto} */
    public static function contexto(string $tipo, int $id): array
    {
        $ctx = ['tipo' => $tipo, 'cliente' => null, 'presupuesto' => null, 'proyecto' => null];
        if ($tipo === 'cliente') {
            $ctx['cliente'] = Cliente::findOrFail($id);
        } elseif ($tipo === 'presupuesto') {
            $ctx['presupuesto'] = Presupuesto::with(['conceptos', 'cliente', 'proyecto'])->findOrFail($id);
            $ctx['cliente'] = $ctx['presupuesto']->cliente;
        } else {
            $ctx['proyecto'] = Proyecto::with(['cliente', 'presupuesto.conceptos', 'pagos', 'etapas', 'archivos'])->findOrFail($id);
            $ctx['cliente'] = $ctx['proyecto']->cliente;
            $ctx['presupuesto'] = $ctx['proyecto']->presupuesto;
        }
        return $ctx;
    }

    public static function modelo(array $ctx): Cliente|Presupuesto|Proyecto
    {
        return $ctx[$ctx['tipo']];
    }

    /**
     * Plantillas disponibles para el contexto, listas para el editor.
     * Los recordatorios de pago salen uno por cada pago pendiente.
     */
    public static function plantillas(array $ctx): array
    {
        $lista = [];
        foreach (PlantillasCorreo::activas() as $clave => $pl) {
            if (! in_array($ctx['tipo'], $pl['para'], true)) {
                continue;
            }
            // La entrega digital solo aparece cuando hay algo visible en la galería
            if ($clave === 'entrega_digital' && ! $ctx['proyecto']?->archivos->where('grupo', 'galeria')->where('visible', true)->count()) {
                continue;
            }
            if ($clave === 'recordatorio_pago') {
                $pendientes = $ctx['proyecto']?->pagos->whereNull('pagado_el')->values() ?? collect();
                foreach ($pendientes as $pago) {
                    $lista["recordatorio_pago@{$pago->id}"] = self::armar($ctx, $clave, $pl, $pago)
                        + ['etiqueta' => $pendientes->count() > 1 ? "Recordatorio: {$pago->concepto}" : $pl['nombre']];
                }
                continue;
            }
            $lista[$clave] = self::armar($ctx, $clave, $pl) + ['etiqueta' => $pl['nombre']];
        }
        return $lista;
    }

    private static function armar(array $ctx, string $clave, array $pl, ?ProyectoPago $pago = null): array
    {
        $vars = self::variables($ctx, $pago);
        $r = fn (string $t) => strtr($t, $vars);
        $url = self::enlace($ctx, $clave);

        return [
            'clave'   => $clave,
            'pago_id' => $pago?->id,
            'icono'   => $pl['icono'] ?? 'bi-envelope',
            'asunto'  => $r($pl['asunto']),
            'titulo'  => $r($pl['titulo'] ?? ''),
            'cuerpo'  => $r($pl['cuerpo']),
            'boton'   => $url && ! empty($pl['boton']) ? $pl['boton'] : null,
            'boton_por_defecto' => $url ? ($pl['boton'] ?? 'Ver detalles') : null,
            'pdf'     => ! empty($pl['pdf']) && $ctx['presupuesto'],
            'resumen' => ! empty($pl['resumen']),
            'banco'   => ! empty($pl['banco']),
            'miniaturas' => ! empty($pl['miniaturas']),
            'adjuntos' => ! empty($pl['adjuntos']),
            'codigo'   => ! empty($pl['codigo']) && $ctx['presupuesto'] && Aceptacion::puedeResponder($ctx['presupuesto']),
            'para'    => self::destinatario($ctx, $clave),
        ];
    }

    /** Variables {x} que se reemplazan en asunto y cuerpo */
    public static function variables(array $ctx, ?ProyectoPago $pago = null): array
    {
        $c = $ctx['cliente'];
        $p = $ctx['presupuesto'];
        $pr = $ctx['proyecto'];
        $fecha = fn ($d) => $d ? $d->locale('es')->isoFormat('D [de] MMMM') : '';

        $limite = '';
        if ($pago?->vence_el) {
            $limite = ', con fecha límite el ' . $fecha($pago->vence_el);
        } elseif ($pago?->antes_de && $pr) {
            $etapa = $pr->etapas->firstWhere('clave', $pago->antes_de);
            $limite = $etapa ? ', necesario para iniciar ' . Str::lower($etapa->nombre) : '';
        }

        return [
            '{nombre}'       => self::primerNombre($c?->nombre ?? $p?->cliente_nombre ?? ''),
            '{empresa}'      => $c?->empresa ?? $p?->cliente_empresa ?? '',
            '{folio}'        => $p?->folio ?? '',
            '{concepto}'     => $p ? (Str::limit($p->conceptos->first()?->resumen ?? $p->titulo, 60))
                : ($pr?->nombre ?? (($u = $c?->presupuestos()->with('conceptos')->first()) ? Str::limit($u->conceptos->first()?->resumen ?? $u->titulo, 60) : 'nuestros servicios')),
            '{monto}'        => $p ? $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) : '',
            '{vigencia}'     => $p ? $fecha($p->vigencia_local) : '',
            '{proyecto}'     => $pr?->nombre ?? '',
            '{pago}'         => $pago ? Str::lower($pago->concepto) : '',
            '{monto_pago}'   => $pago?->monto_texto ?? '',
            '{fecha_limite}' => $limite,
            '{entregables}'  => $pr?->entregables_texto ?? '',
            '{siguiente}'    => $pr ? Str::lcfirst($pr->siguiente_paso ?? 'te mantendremos al tanto') : '',
            '{firma}'        => config('vandu.emisor.nombre'),
            '{codigo}'       => '{codigo}', // se genera al enviar
        ];
    }

    public static function primerNombre(string $nombre): string
    {
        return Str::of($nombre)->trim()->before(' ')->value() ?: $nombre;
    }

    public static function destinatario(array $ctx, string $clave): string
    {
        $c = $ctx['cliente'];
        $pl = PlantillasCorreo::una(Str::before($clave, '@')) ?? [];
        if (($clave === 'recordatorio_pago' || ! empty($pl['para_factura'])) && $c?->email_factura) {
            return $c->email_factura;
        }
        return (string) ($c?->email ?? '');
    }

    /** Enlace del botón: la vista del proyecto o la de la cotización */
    public static function enlace(array $ctx, ?string $clave = null): ?string
    {
        $pl = $clave ? (PlantillasCorreo::una(Str::before($clave, '@')) ?? []) : [];
        if ($ctx['proyecto'] && ($pl['enlace'] ?? null) === 'entrega') return $ctx['proyecto']->url_entrega;
        if ($ctx['proyecto']) return $ctx['proyecto']->url_publica;
        if ($ctx['presupuesto']) return $ctx['presupuesto']->url_publica;
        $ultima = $ctx['cliente']?->presupuestos()->first();
        return $ultima?->url_publica;
    }

    /** Filas del recuadro de resumen */
    public static function resumen(array $ctx, ?int $pagoId = null): array
    {
        $p = $ctx['presupuesto'];
        $pr = $ctx['proyecto'];
        $fecha = fn ($d) => $d->locale('es')->isoFormat('D [de] MMMM [de] YYYY');

        if ($ctx['tipo'] === 'proyecto' && $pr) {
            $pago = $pagoId ? $pr->pagos->firstWhere('id', $pagoId) : null;
            if ($pago) {
                return array_filter([
                    'Proyecto'     => $pr->nombre,
                    'Concepto'     => $pago->concepto,
                    'Fecha límite' => $pago->vence_el ? $fecha($pago->vence_el) : null,
                    'Monto'        => $pago->monto_texto,
                ]);
            }
            return array_filter([
                'Proyecto'  => $pr->nombre,
                'Servicio'  => $pr->tipo_nombre,
                'Avance'    => $pr->progreso . '%',
                'Siguiente' => $pr->siguiente_paso,
            ]);
        }
        if ($p) {
            return [
                'Folio'    => $p->folio,
                'Concepto' => Str::limit($p->conceptos->first()?->resumen ?? $p->titulo, 60),
                'Vigencia' => 'Hasta el ' . $fecha($p->vigencia_local),
                'Importe'  => $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal),
            ];
        }
        return [];
    }

    /** Hasta 6 fotos de la galería, en cuadro, para el correo */
    public static function miniaturas(array $ctx, int $max = 6): array
    {
        $pr = $ctx['proyecto'];
        if (! $pr) return [];
        $fotos = $pr->archivos->where('grupo', 'galeria')->where('visible', true)->filter->es_imagen->values();
        $n = $fotos->count() >= $max ? $max : ($fotos->count() >= 3 ? 3 : $fotos->count());
        return $fotos->take($n)->map(fn ($a) => [
            'src'  => route('proyecto.archivo', [$pr->token, $a]) . '?v=correo',
            'alt'  => $a->nombre,
        ])->all();
    }

    public static function banco(?Presupuesto $p = null): array
    {
        return array_filter([
            'Banco'        => $p?->banco ?: config('vandu.pago.banco'),
            'CLABE'        => $p?->clabe ?: config('vandu.pago.clabe'),
            'Beneficiario' => $p?->beneficiario ?: config('vandu.pago.beneficiario'),
        ]);
    }

    /** Logo en base64 para la vista previa (al enviar va incrustado como imagen del correo) */
    public static function logoDataUri(): string
    {
        return 'data:image/png;base64,' . base64_encode(file_get_contents(resource_path('img/logo-vandu-correo.png')));
    }
}
