<?php

namespace App\Support\Licencias;

use App\Models\Cuenta;
use App\Models\Licencia;
use App\Support\Cuentas;
use Illuminate\Support\Carbon;

/**
 * En qué punto está la licencia de un negocio. Se calcula al vuelo con la fecha de hoy:
 *  - sin:         sin licencia asignada (o la cuenta principal): sin límites ni vencimiento.
 *  - corriente:   pagado; faltan más de 7 días.
 *  - por_vencer:  vence en 7 días o menos (aviso al dueño).
 *  - gracia:      ya venció, pero todo sigue funcionando N días (aviso fuerte).
 *  - lectura:     solo lectura: pueden ver y descargar, no crear ni cambiar nada.
 *  - suspendida:  el equipo ya no entra al panel (los datos se conservan; sus clientes siguen viendo sus enlaces).
 *  - perpetua / mantenimiento: licencia de por vida; si el mantenimiento venció solo se avisa.
 *  - cortesia:    sin cobro ni vencimiento.
 */
class Estado
{
    public const TEXTOS = [
        'sin'           => ['texto' => 'Sin licencia',         'clase' => 'borrador'],
        'corriente'     => ['texto' => 'Al corriente',         'clase' => 'aceptada'],
        'por_vencer'    => ['texto' => 'Por vencer',           'clase' => 'negociacion'],
        'gracia'        => ['texto' => 'Vencida · en gracia',  'clase' => 'negociacion'],
        'lectura'       => ['texto' => 'Solo lectura',         'clase' => 'rechazada'],
        'suspendida'    => ['texto' => 'Suspendida por pago',  'clase' => 'rechazada'],
        'perpetua'      => ['texto' => 'De por vida',          'clase' => 'aceptada'],
        'mantenimiento' => ['texto' => 'Mantenimiento vencido', 'clase' => 'negociacion'],
        'cortesia'      => ['texto' => 'Cortesía',             'clase' => 'enviada'],
    ];

    private static array $cache = [];

    public static function olvidar(): void
    {
        self::$cache = [];
    }

    /**
     * @return object{clave: string, texto: string, clase: string, licencia: ?Licencia, vence: ?Carbon, dias: ?int, lecturaDesde: ?Carbon, suspendeEl: ?Carbon}
     *   dias: días que faltan para vencer (negativo = días desde que venció)
     */
    public static function de(?Cuenta $c): object
    {
        if (! $c) return self::armar('sin');
        return self::$cache[$c->id] ??= self::calcular($c);
    }

    private static function calcular(Cuenta $c): object
    {
        if ($c->id === Cuentas::principalId()) return self::armar('sin');
        $l = Licencia::with('plan')->where('cuenta_id', $c->id)->first();
        if (! $l) return self::armar('sin');
        $hoy = now(config('vandu.zona_horaria'))->startOfDay();
        $vence = $l->vence_el ? Carbon::parse($l->vence_el->toDateString(), config('vandu.zona_horaria'))->startOfDay() : null;
        $dias = $vence ? (int) $hoy->diffInDays($vence, false) : null;

        if ($l->tipo === 'cortesia') return self::armar('cortesia', $l, $vence, $dias);
        if ($l->tipo === 'perpetua') return self::armar($vence && $dias < 0 ? 'mantenimiento' : 'perpetua', $l, $vence, $dias);
        if (! $vence) return self::armar('corriente', $l);

        $lecturaDesde = $vence->copy()->addDays($l->dias_gracia + 1);
        $suspendeEl = $lecturaDesde->copy()->addDays($l->dias_lectura);
        $clave = match (true) {
            $dias > 7 => 'corriente',
            $dias >= 0 => 'por_vencer',
            $hoy->lt($lecturaDesde) => 'gracia',
            $hoy->lt($suspendeEl) => 'lectura',
            default => 'suspendida',
        };
        return self::armar($clave, $l, $vence, $dias, $lecturaDesde, $suspendeEl);
    }

    private static function armar(string $clave, ?Licencia $l = null, ?Carbon $vence = null, ?int $dias = null, ?Carbon $lecturaDesde = null, ?Carbon $suspendeEl = null): object
    {
        return (object) ['clave' => $clave, 'texto' => self::TEXTOS[$clave]['texto'], 'clase' => self::TEXTOS[$clave]['clase'], 'licencia' => $l,
            'vence' => $vence, 'dias' => $dias, 'lecturaDesde' => $lecturaDesde, 'suspendeEl' => $suspendeEl];
    }

    /** Frase corta para el aviso del panel y los correos */
    public static function frase(object $e): ?string
    {
        $f = fn (?Carbon $d) => $d ? $d->locale('es')->isoFormat('D [de] MMMM') : '';
        return match ($e->clave) {
            'por_vencer' => $e->dias === 0 ? 'Tu plan vence hoy.' : 'Tu plan vence ' . ($e->dias === 1 ? 'mañana' : "en {$e->dias} días") . ' (' . $f($e->vence) . ').',
            'gracia'     => 'Tu plan venció el ' . $f($e->vence) . '. Todo sigue funcionando hasta el ' . $f($e->lecturaDesde?->copy()->subDay()) . '; después el panel queda en solo lectura.',
            'lectura'    => 'Tu plan venció el ' . $f($e->vence) . '. El panel está en solo lectura: puedes ver y descargar todo, pero no registrar ni cambiar nada. El ' . $f($e->suspendeEl) . ' se suspende el acceso.',
            'suspendida' => 'El acceso al panel está suspendido porque el plan venció el ' . $f($e->vence) . '. Tu información está guardada y completa.',
            'mantenimiento' => 'El mantenimiento anual de tu licencia venció el ' . $f($e->vence) . '. Tu panel sigue funcionando; renuévalo para seguir recibiendo soporte y mejoras.',
            default => null,
        };
    }

    /** Datos para pagar (los captura la plataforma en Planes y precios) */
    public static function datosPago(): array
    {
        return (array) (Cuenta::find(Cuentas::principalId())?->ajuste('licencias.pago') ?? []);
    }
}
