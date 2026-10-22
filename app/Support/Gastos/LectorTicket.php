<?php

namespace App\Support\Gastos;

use App\Models\Cuenta;
use App\Models\Gasto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lee la foto de un ticket con la API de Anthropic (modelo Haiku, con visión) y devuelve los datos para prellenar el gasto.
 * No guarda nada: la persona revisa y confirma. Lo que no se lee con seguridad regresa vacío y en "dudas".
 * Sin ANTHROPIC_API_KEY no lee (los gastos se capturan a mano o con XML). Cada negocio tiene un tope de lecturas al mes.
 */
class LectorTicket
{
    public const TOPE = 150;   // lecturas al mes por negocio, si la plataforma no pone otro

    public static function activo(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    public static function tope(Cuenta $c): int
    {
        return (int) $c->ajuste('gastos_tope', self::TOPE);
    }

    public static function usadasEsteMes(Cuenta $c): int
    {
        $inicio = now(config('vandu.zona_horaria'))->startOfMonth()->utc();
        return DB::table('gasto_lecturas')->where('cuenta_id', $c->id)->where('created_at', '>=', $inicio)->count();
    }

    /** Achica la foto (lado mayor 1600 px, JPEG) para mandar menos datos y gastar menos. null si no es una imagen */
    public static function preparar(string $bytes): ?string
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) return null;
        $w = imagesx($img); $h = imagesy($img);
        $f = min(1, 1600 / max($w, $h));
        if ($f < 1) {
            $nueva = imagescale($img, (int) round($w * $f), (int) round($h * $f), IMG_BICUBIC);
            imagedestroy($img);
            $img = $nueva;
        }
        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);
        return ob_get_clean() ?: null;
    }

    /**
     * @return array{ok: bool, motivo?: string, mensaje?: string, datos?: array, dudas?: array}
     */
    public static function leer(string $jpeg, Cuenta $c, ?User $u): array
    {
        if (! self::activo()) return ['ok' => false, 'motivo' => 'sin_ia', 'mensaje' => 'La lectura automática todavía no está activa. Captura los datos del ticket.'];
        if (self::usadasEsteMes($c) >= self::tope($c)) {
            return ['ok' => false, 'motivo' => 'tope', 'mensaje' => 'Ya se usaron las ' . self::tope($c) . ' lecturas de tickets de este mes. Captura los datos a mano.'];
        }

        $registro = ['cuenta_id' => $c->id, 'user_id' => $u?->id, 'ok' => false, 'created_at' => now()];
        try {
            $r = Http::timeout(45)->withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.modelo_tickets'),
                'max_tokens' => 800,
                'tools' => [self::herramienta()],
                'tool_choice' => ['type' => 'tool', 'name' => 'registrar_gasto'],
                'messages' => [['role' => 'user', 'content' => [
                    ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => base64_encode($jpeg)]],
                    ['type' => 'text', 'text' => self::instrucciones()],
                ]]],
            ]);
            $registro['tokens_entrada'] = (int) $r->json('usage.input_tokens', 0);
            $registro['tokens_salida'] = (int) $r->json('usage.output_tokens', 0);
            $entrada = collect($r->json('content', []))->firstWhere('type', 'tool_use')['input'] ?? null;
            if (! $r->successful() || ! is_array($entrada)) {
                report(new \RuntimeException('Lectura de ticket: ' . $r->status() . ' ' . mb_substr($r->body(), 0, 300)));
                DB::table('gasto_lecturas')->insert($registro);
                return ['ok' => false, 'motivo' => 'error', 'mensaje' => 'No pudimos leer el ticket ahora. Captura los datos a mano.'];
            }
        } catch (Throwable $e) {
            report($e);
            DB::table('gasto_lecturas')->insert($registro);
            return ['ok' => false, 'motivo' => 'error', 'mensaje' => 'No pudimos leer el ticket ahora. Captura los datos a mano.'];
        }

        $registro['ok'] = true;
        DB::table('gasto_lecturas')->insert($registro);
        if (($entrada['es_comprobante'] ?? true) === false) {
            return ['ok' => false, 'motivo' => 'no_es_ticket', 'mensaje' => 'La foto no parece un ticket o factura. Intenta otra vez con el ticket completo y bien iluminado.'];
        }
        return ['ok' => true] + self::limpiar($entrada);
    }

    /** Valida lo que regresó el modelo: números sin signo, fecha real y no futura, categoría conocida */
    public static function limpiar(array $e): array
    {
        $num = fn ($v) => is_numeric($v) && $v >= 0 ? round((float) $v, 2) : null;
        $dudas = array_values(array_intersect((array) ($e['dudas'] ?? []), ['fecha', 'total', 'iva', 'proveedor', 'concepto', 'rfc']));
        $fecha = null;
        if (is_string($e['fecha'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e['fecha'])) {
            try {
                $f = \Illuminate\Support\Carbon::createFromFormat('!Y-m-d', $e['fecha']);
                if ($f->lte(now()->addDay()) && $f->year >= 2000) $fecha = $f->toDateString();
            } catch (Throwable) {}
        }
        if (! $fecha) $dudas[] = 'fecha';
        $total = $num($e['total'] ?? null);
        if ($total === null) $dudas[] = 'total';
        $iva = $num($e['iva'] ?? null);
        if ($iva !== null && $total !== null && $iva >= $total) $iva = null;
        $rfc = is_string($e['rfc'] ?? null) ? mb_strtoupper(preg_replace('/[^A-Za-z0-9&Ñ]/u', '', $e['rfc'])) : null;
        return [
            'datos' => [
                'fecha'      => $fecha,
                'proveedor'  => self::texto($e['proveedor'] ?? null, 160),
                'rfc'        => $rfc && preg_match('/^[A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc) ? $rfc : null,
                'concepto'   => self::texto($e['concepto'] ?? null, 160),
                'categoria'  => array_key_exists($e['categoria'] ?? '', Gasto::CATEGORIAS) ? $e['categoria'] : 'otros',
                'total'      => $total,
                'iva'        => $iva,
                'forma_pago' => array_key_exists($e['forma_pago'] ?? '', Gasto::FORMAS) ? $e['forma_pago'] : null,
            ],
            'dudas' => array_values(array_unique($dudas)),
        ];
    }

    private static function texto($v, int $max): ?string
    {
        $v = is_string($v) ? trim(preg_replace('/\s+/', ' ', $v)) : '';
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function instrucciones(): string
    {
        return <<<'TXT'
Lee este comprobante de compra de un negocio en México (ticket de tienda, gasolinera, restaurante, terminal de pago, recibo o factura impresa; puede estar arrugado, borroso o escrito a mano) y regístralo con la herramienta.
Reglas:
- total: lo que se pagó en pesos (TOTAL, IMPORTE, A PAGAR, TOTAL MXN). Nunca el efectivo recibido ni el cambio. Si hay propina pagada, inclúyela.
- iva: solo si aparece impreso (IVA, I.V.A., IVA 16%). Si no aparece, null; no lo calcules.
- fecha: en formato AAAA-MM-DD. En México las fechas suelen venir como día/mes/año.
- proveedor: el nombre comercial del negocio que vende (por ejemplo OXXO, Home Depot, Pemex, el nombre del restaurante).
- concepto: qué se compró, en español y en pocas palabras (máximo 80 caracteres), por ejemplo "Gasolina magna 32 L" o "Comida con cliente (4 personas)".
- categoria: la que mejor describa la compra.
- No inventes: si un dato no se lee con seguridad, déjalo en null y ponlo en dudas.
- Si la imagen no es un comprobante de compra, es_comprobante = false.
TXT;
    }

    private static function herramienta(): array
    {
        return [
            'name' => 'registrar_gasto',
            'description' => 'Registra los datos leídos de un ticket o comprobante de compra.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'es_comprobante' => ['type' => 'boolean'],
                    'proveedor'  => ['type' => ['string', 'null']],
                    'rfc'        => ['type' => ['string', 'null'], 'description' => 'RFC del emisor si aparece'],
                    'fecha'      => ['type' => ['string', 'null'], 'description' => 'AAAA-MM-DD'],
                    'concepto'   => ['type' => ['string', 'null']],
                    'categoria'  => ['type' => 'string', 'enum' => array_keys(Gasto::CATEGORIAS)],
                    'total'      => ['type' => ['number', 'null']],
                    'iva'        => ['type' => ['number', 'null']],
                    'forma_pago' => ['type' => ['string', 'null'], 'enum' => [...array_keys(Gasto::FORMAS), null]],
                    'dudas'      => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['fecha', 'total', 'iva', 'proveedor', 'concepto', 'rfc']]],
                ],
                'required' => ['es_comprobante', 'categoria', 'total', 'fecha', 'dudas'],
            ],
        ];
    }
}
