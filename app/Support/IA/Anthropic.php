<?php

namespace App\Support\IA;

use App\Models\Cuenta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Llamadas a la API de Anthropic para leer documentos (tickets, constancias, listas de precios).
 * Siempre con una herramienta forzada: el modelo regresa datos con forma fija, no texto libre.
 * Cada lectura se anota (tokens incluidos) y cuenta contra el tope mensual del negocio.
 */
class Anthropic
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

    /** Un PDF o una imagen como bloque de contenido */
    public static function archivo(string $bytes, string $mime): array
    {
        return str_contains($mime, 'pdf')
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode($bytes)]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)]];
    }

    /**
     * @param array $contenido bloques del mensaje (archivo + instrucciones)
     * @return array{ok: bool, entrada?: array, motivo?: string, mensaje?: string}
     */
    public static function herramienta(Cuenta $c, ?User $u, string $tipo, array $contenido, array $herramienta, int $maxTokens = 1024, int $timeout = 60): array
    {
        if (! self::activo()) return ['ok' => false, 'motivo' => 'sin_ia', 'mensaje' => 'La lectura automática todavía no está activa.'];
        if (self::usadasEsteMes($c) >= self::tope($c)) {
            return ['ok' => false, 'motivo' => 'tope', 'mensaje' => 'Ya se usaron las ' . self::tope($c) . ' lecturas con IA de este mes.'];
        }
        $registro = ['cuenta_id' => $c->id, 'user_id' => $u?->id, 'tipo' => $tipo, 'ok' => false, 'created_at' => now()];
        try {
            $r = Http::timeout($timeout)->withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.modelo_tickets'),
                'max_tokens' => $maxTokens,
                'tools' => [$herramienta],
                'tool_choice' => ['type' => 'tool', 'name' => $herramienta['name']],
                'messages' => [['role' => 'user', 'content' => $contenido]],
            ]);
            $registro['tokens_entrada'] = (int) $r->json('usage.input_tokens', 0);
            $registro['tokens_salida'] = (int) $r->json('usage.output_tokens', 0);
            $entrada = collect($r->json('content', []))->firstWhere('type', 'tool_use')['input'] ?? null;
            if (! $r->successful() || ! is_array($entrada)) {
                report(new \RuntimeException("Lectura con IA ($tipo): " . $r->status() . ' ' . mb_substr($r->body(), 0, 300)));
                DB::table('gasto_lecturas')->insert($registro);
                return ['ok' => false, 'motivo' => 'error', 'mensaje' => 'No pudimos leer el documento ahora.'];
            }
        } catch (Throwable $e) {
            report($e);
            DB::table('gasto_lecturas')->insert($registro);
            return ['ok' => false, 'motivo' => 'error', 'mensaje' => 'No pudimos leer el documento ahora.'];
        }
        $registro['ok'] = true;
        DB::table('gasto_lecturas')->insert($registro);
        return ['ok' => true, 'entrada' => $entrada];
    }
}
