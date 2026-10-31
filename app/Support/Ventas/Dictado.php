<?php

namespace App\Support\Ventas;

use App\Models\Cliente;
use App\Models\Cuenta;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\IA\Anthropic;
use Illuminate\Support\Str;

/**
 * Venta dictada: "le vendí a Juan 20 paletas de mango, pagó con transferencia".
 * La IA solo entiende el texto y elige productos de la lista del catálogo; Nerby busca al cliente,
 * valida cada producto y precio, y regresa la venta armada para que la persona la revise y la cobre.
 * Nunca guarda nada. Lo que no está en el catálogo queda como concepto libre (sin inventario).
 */
class Dictado
{
    public const MAX_CATALOGO = 400;

    public static function activo(): bool { return Anthropic::activo(); }

    /** @return array{ok: bool, mensaje?: string, venta?: array} */
    public static function armar(string $texto, Cuenta $c, ?User $u): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        $catalogo = self::catalogo($texto);
        $r = Anthropic::herramienta($c, $u, 'dictado', [['type' => 'text', 'text' => self::instrucciones($texto, $catalogo)]], self::herramienta(), 1200, 40);
        if (! $r['ok']) {
            return ['ok' => false, 'mensaje' => match ($r['motivo']) {
                'sin_ia' => 'Dictar ventas todavía no está activo en este panel.',
                'tope'   => $r['mensaje'] . ' Captura la venta tocando los productos.',
                default  => 'No pudimos entender la venta ahora. Intenta otra vez o captúrala tocando los productos.',
            }];
        }
        $e = $r['entrada'];
        if (($e['es_venta'] ?? true) === false || empty($e['productos'])) {
            return ['ok' => false, 'mensaje' => 'No encontramos qué se vendió. Di, por ejemplo: "dos paletas de mango a Juan, pagó en efectivo".'];
        }
        return ['ok' => true, 'venta' => self::limpiar($e, $catalogo)];
    }

    /** Los productos que la IA puede elegir: todo el catálogo activo, o lo que se parece a lo dicho si es muy grande */
    public static function catalogo(string $texto)
    {
        $q = Producto::activos()->orderBy('nombre');
        if ((clone $q)->count() > self::MAX_CATALOGO) {
            $palabras = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($texto)))->filter(fn ($p) => mb_strlen($p) >= 4)->unique()->take(25);
            $q->where(function ($w) use ($palabras) {
                foreach ($palabras as $p) {
                    $raiz = mb_strlen($p) > 5 ? mb_substr($p, 0, -1) : $p;   // "paletas" también encuentra "paleta"
                    $w->orWhereRaw('LOWER(nombre) LIKE ?', ['%' . $raiz . '%']);
                }
            });
        }
        return $q->limit(self::MAX_CATALOGO)->get(['id', 'nombre', 'precio', 'unidad', 'controla_existencia', 'existencia', 'activo']);
    }

    /** Valida y completa lo que entendió la IA con los datos reales del negocio */
    public static function limpiar(array $e, $catalogo): array
    {
        $num = fn ($v) => is_numeric($v) && $v >= 0 ? round((float) $v, 2) : null;
        $porId = $catalogo->keyBy('id');
        $lineas = [];
        foreach (array_slice((array) $e['productos'], 0, 40) as $p) {
            $cant = $num($p['cantidad'] ?? null) ?: 1;
            $precio = $num($p['precio_unitario'] ?? null);
            if ($precio === null && ($imp = $num($p['importe'] ?? null)) !== null) $precio = round($imp / $cant, 2);
            $dicho = self::texto($p['dicho'] ?? null, 250) ?? 'Producto';
            $prod = isset($p['producto_id']) ? $porId->get((int) $p['producto_id']) : null;
            if ($prod) {
                $lineas[] = ['id' => $prod->id, 'nombre' => $prod->nombre, 'cantidad' => $cant, 'precio' => $precio ?? (float) $prod->precio,
                    'precio_catalogo' => (float) $prod->precio, 'precio_dicho' => $precio !== null && abs($precio - (float) $prod->precio) > 0.009,
                    'controla' => (bool) $prod->controla_existencia, 'existencia' => (float) $prod->existencia, 'dicho' => $dicho];
            } else {
                $lineas[] = ['id' => null, 'libre' => true, 'nombre' => Str::ucfirst($dicho), 'cantidad' => $cant, 'precio' => $precio, 'sin_precio' => $precio === null,
                    'controla' => false, 'existencia' => 0, 'dicho' => $dicho, 'sugerencias' => self::parecidos($dicho)];
            }
        }

        $pago = in_array($e['pago'] ?? null, ['contado', 'parcial', 'credito'], true) ? $e['pago'] : 'contado';
        return [
            'lineas' => $lineas,
            'cliente' => self::cliente($e['cliente'] ?? null),
            'pago' => $pago,
            'forma' => array_key_exists($e['forma'] ?? '', Venta::FORMAS) ? $e['forma'] : null,
            'abono' => $pago === 'parcial' ? $num($e['abono'] ?? null) : null,
            'dias' => is_numeric($e['dias_credito'] ?? null) && $e['dias_credito'] >= 1 && $e['dias_credito'] <= 365 ? (int) $e['dias_credito'] : null,
            'entrega' => in_array($e['entrega'] ?? null, ['ahora', 'recoge', 'envio'], true) ? $e['entrega'] : 'ahora',
            'direccion' => self::texto($e['direccion'] ?? null, 300),
            'descuento' => $num($e['descuento'] ?? null),
            'notas' => self::texto($e['notas'] ?? null, 500),
        ];
    }

    /** Hasta 3 productos del catálogo que se parecen a lo dicho (para cambiar el concepto libre con un toque) */
    public static function parecidos(string $dicho): array
    {
        $palabras = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($dicho)))->filter(fn ($p) => mb_strlen($p) >= 3)->values();
        if ($palabras->isEmpty()) return [];
        return Producto::activos()->where(function ($w) use ($palabras) {
            foreach ($palabras as $p) $w->orWhereRaw('LOWER(nombre) LIKE ?', ['%' . (mb_strlen($p) > 5 ? mb_substr($p, 0, -1) : $p) . '%']);
        })->limit(30)->get(['id', 'nombre', 'precio', 'controla_existencia', 'existencia'])
            ->sortByDesc(fn ($x) => $palabras->filter(fn ($p) => str_contains(mb_strtolower($x->nombre), mb_strlen($p) > 5 ? mb_substr($p, 0, -1) : $p))->count())
            ->take(3)->map(fn ($x) => ['id' => $x->id, 'nombre' => $x->nombre, 'precio' => (float) $x->precio, 'controla' => (bool) $x->controla_existencia, 'existencia' => (float) $x->existencia])
            ->values()->all();
    }

    /**
     * Busca al cliente que se mencionó: por teléfono, o por nombre y empresa.
     * @return array{tipo: string, cliente?: array, candidatos?: array, nombre?: string, telefono?: ?string}
     *   tipo: ninguno (mostrador) | existe | varios (elegir) | nuevo
     */
    public static function cliente($c): array
    {
        $nombre = self::texto($c['nombre'] ?? null, 160);
        $empresa = self::texto($c['empresa'] ?? null, 160);
        $tel = preg_replace('/\D/', '', (string) ($c['telefono'] ?? ''));
        if (! $nombre && ! $empresa && strlen($tel) < 10) return ['tipo' => 'ninguno'];

        $ficha = fn (Cliente $x) => ['id' => $x->id, 'nombre' => $x->empresa ?: $x->nombre, 'detalle' => trim(($x->empresa ? $x->nombre : '') . ' ' . ($x->telefono ?? '')), 'telefono' => $x->telefono];

        if (strlen($tel) >= 10) {
            $ya = Cliente::whereRaw("REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+52', '') like ?", ['%' . substr($tel, -10)])->first();
            if ($ya) return ['tipo' => 'existe', 'cliente' => $ficha($ya)];
        }

        $encontrados = collect();
        foreach (array_filter([$empresa, $nombre]) as $buscar) {
            $palabras = collect(preg_split('/\s+/u', mb_strtolower($buscar)))->filter(fn ($p) => mb_strlen($p) >= 2 && ! in_array($p, ['de', 'del', 'la', 'el', 'los', 'las', 'don', 'doña', 'sr', 'sra'], true));
            if ($palabras->isEmpty()) continue;
            $q = Cliente::query();
            foreach ($palabras as $p) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $p) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$like])->orWhereRaw('LOWER(empresa) LIKE ?', [$like]));
            }
            $encontrados = $encontrados->merge($q->limit(6)->get());
        }
        $encontrados = $encontrados->unique('id')->values();

        if ($encontrados->count() === 1) return ['tipo' => 'existe', 'cliente' => $ficha($encontrados[0])];
        if ($encontrados->count() > 1) {
            // Si uno se llama exactamente así, es ese
            $exacto = $encontrados->filter(fn ($x) => in_array(mb_strtolower((string) $nombre), [mb_strtolower((string) $x->nombre), mb_strtolower((string) $x->empresa)], true)
                || ($empresa && mb_strtolower($empresa) === mb_strtolower((string) $x->empresa)));
            if ($exacto->count() === 1) return ['tipo' => 'existe', 'cliente' => $ficha($exacto->first())];
            return ['tipo' => 'varios', 'candidatos' => $encontrados->take(5)->map($ficha)->all(), 'nombre' => $nombre ?: $empresa, 'telefono' => strlen($tel) >= 10 ? $tel : null];
        }
        return ['tipo' => 'nuevo', 'nombre' => $nombre ?: $empresa, 'telefono' => strlen($tel) >= 10 ? $tel : null];
    }

    private static function texto($v, int $max): ?string
    {
        $v = is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v)) : '';
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function instrucciones(string $texto, $catalogo): string
    {
        $lista = $catalogo->map(fn ($p) => $p->id . ' | ' . $p->nombre . ' | $' . number_format((float) $p->precio, 2, '.', '') . ' por ' . ($p->unidad ?: 'pieza'))->implode("\n");
        $hoy = now(config('vandu.zona_horaria'))->locale('es')->isoFormat('dddd D [de] MMMM');
        return <<<TXT
Eres el asistente de ventas de un negocio en México. Hoy es {$hoy}. Alguien del negocio dictó (o pegó) una venta; puede tener errores de dictado, números en letra y modismos. Regístrala con la herramienta.

Catálogo del negocio (id | nombre | precio antes de IVA):
{$lista}

Reglas:
- productos: uno por cada cosa vendida. "dicho" = cómo lo dijo la persona. producto_id = el id del catálogo SOLO si es claramente ese producto (sinónimos, plural o diminutivo valen: "paletitas de mango" = "Paleta de mango"). Si hay dos parecidos y no se sabe cuál, o no está en el catálogo, producto_id = null. Nunca inventes un id.
- cantidad: número ("una docena" = 12, "media docena" = 6, "un par" = 2). Si no se dice, 1.
- precio_unitario: solo si se dijo el precio de cada uno ("a 20 pesos", "a 20 c/u"). importe: solo si se dijo el total de ese renglón ("30 de mango por 600"). Si no se dijo precio, ambos null: se usa el del catálogo. No calcules ni inventes precios.
- cliente: nombre, empresa y teléfono SOLO si se mencionan. Si no se menciona a nadie (venta de mostrador), null. No pongas al vendedor como cliente.
- pago: "contado" si pagó todo o no se dice; "parcial" si dejó un anticipo o abono (abono = cuánto dio); "credito" si no pagó nada ("me lo paga después", "a crédito", "se lo fié"). dias_credito si dice en cuántos días paga.
- forma: efectivo, transferencia, tarjeta, etc., solo si se dice.
- entrega: "ahora" si se lo llevó o no se dice; "recoge" si pasa por él después; "envio" si se lo llevan. direccion si se dice a dónde.
- descuento: en pesos, solo si se dice uno ("le hice 50 de descuento").
- notas: cualquier otro detalle útil para la venta (por ejemplo "sin chile", "entregar a las 5"), corto.
- Si el texto no describe una venta, es_venta = false.

Lo que se dictó:
"""{$texto}"""
TXT;
    }

    private static function herramienta(): array
    {
        $formas = array_keys(Venta::FORMAS);
        return [
            'name' => 'armar_venta',
            'description' => 'Registra la venta que se dictó, con productos del catálogo y del cliente lo que se mencionó.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'es_venta' => ['type' => 'boolean'],
                    'cliente' => ['type' => ['object', 'null'], 'properties' => [
                        'nombre' => ['type' => ['string', 'null']], 'empresa' => ['type' => ['string', 'null']], 'telefono' => ['type' => ['string', 'null']],
                    ]],
                    'productos' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'dicho' => ['type' => 'string'], 'producto_id' => ['type' => ['integer', 'null']],
                        'cantidad' => ['type' => 'number'], 'precio_unitario' => ['type' => ['number', 'null']], 'importe' => ['type' => ['number', 'null']],
                    ], 'required' => ['dicho', 'producto_id', 'cantidad']]],
                    'pago' => ['type' => 'string', 'enum' => ['contado', 'parcial', 'credito']],
                    'abono' => ['type' => ['number', 'null']],
                    'dias_credito' => ['type' => ['integer', 'null']],
                    'forma' => ['type' => ['string', 'null'], 'enum' => [...$formas, null]],
                    'entrega' => ['type' => 'string', 'enum' => ['ahora', 'recoge', 'envio']],
                    'direccion' => ['type' => ['string', 'null']],
                    'descuento' => ['type' => ['number', 'null']],
                    'notas' => ['type' => ['string', 'null']],
                ],
                'required' => ['es_venta', 'productos', 'pago', 'entrega'],
            ],
        ];
    }
}
