<?php

namespace App\Support\Facturas;

use App\Models\Integracion;
use App\Models\Presupuesto;
use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Genera (timbra) la factura con Facturapi. Dos formas:
 *  - Nerby (recomendada): con la clave de usuario de Nerby en el .env, cada negocio tiene su organización en Facturapi
 *    y sube su sello digital (CSD) desde el panel; ver Organizacion.
 *  - Propia: el negocio pega la clave secreta de su cuenta de Facturapi.
 * Nerby manda cliente y conceptos y guarda el PDF y el XML. Las claves se guardan cifradas y nunca se muestran.
 */
class Timbrado
{
    public const API = 'https://www.facturapi.io/v2';

    public const FORMAS_SAT = ['01' => 'Efectivo', '03' => 'Transferencia', '04' => 'Tarjeta de crédito', '28' => 'Tarjeta de débito', '02' => 'Cheque', '99' => 'Por definir'];

    /** Forma de pago del SAT según cómo se cobró */
    public static function formaDe(?string $forma): string
    {
        return match ((string) $forma) { 'efectivo' => '01', 'tarjeta', 'tarjeta_credito' => '04', 'tarjeta_debito' => '28', 'transferencia', 'spei', 'deposito' => '03', default => '99' };
    }

    public static function ajustes(): array
    {
        $i = rescue(fn () => Integracion::de('facturapi'), null, false);
        return ($i?->datos ?? []) + ['key' => null, 'clave_prod' => '01010101', 'clave_unidad' => 'H87', 'clave_servicio' => 'E48', 'serie' => '',
            'org_id' => null, 'live_key' => null, 'test_key' => null, 'pruebas' => false, 'certificado' => null, 'emisor' => null];
    }

    /** La clave con la que se timbra: la de su organización en Nerby (real o de pruebas) o la que pegó el negocio */
    public static function clave(): ?string
    {
        $a = self::ajustes();
        if ($a['org_id']) return ($a['pruebas'] ? $a['test_key'] : $a['live_key']) ?: null;
        return $a['key'] ?: null;
    }

    /** Se puede timbrar: con su organización necesita el sello digital (en pruebas basta con los datos fiscales) */
    public static function activo(): bool
    {
        $a = self::ajustes();
        if ($a['org_id']) return filled(self::clave()) && ($a['pruebas'] || ! empty($a['certificado']['tiene']));
        return filled($a['key']);
    }

    public static function enPruebas(): bool
    {
        $a = self::ajustes();
        return $a['org_id'] ? (bool) $a['pruebas'] : str_starts_with((string) $a['key'], 'sk_test_');
    }

    /** Facturas timbradas por Nerby en el mes (para cobrar los timbres). Cuenta las canceladas: el timbre ya se usó */
    public static function timbradasEsteMes(\App\Models\Cuenta $c): int
    {
        $desde = now(config('vandu.zona_horaria'))->startOfMonth()->utc();
        $ids = fn ($t) => \Illuminate\Support\Facades\DB::table($t)->where('cuenta_id', $c->id)->where('origen', 'nerby')->whereNotNull('facturapi_id')->where('created_at', '>=', $desde)->distinct()->pluck('facturapi_id');
        return $ids('venta_facturas')->merge($ids('presupuesto_facturas'))->unique()->count();
    }

    public static function guardarAjustes(array $d): void
    {
        $i = Integracion::de('facturapi') ?? new Integracion(['proveedor' => 'facturapi']);
        $actual = $i->datos ?? [];
        foreach (['org_id', 'live_key', 'test_key', 'pruebas', 'certificado', 'emisor'] as $k) if (array_key_exists($k, $d)) $actual[$k] = $d[$k];
        // La clave solo se cambia si escriben una nueva (el campo llega vacío para no mostrarla)
        if (! empty($d['key'])) $actual['key'] = trim($d['key']);
        if (! empty($d['quitar'])) unset($actual['key']);
        foreach (['clave_prod', 'clave_unidad', 'clave_servicio', 'serie'] as $k) if (array_key_exists($k, $d)) $actual[$k] = trim((string) $d[$k]);
        $i->datos = $actual;
        $i->cuenta = $i->cuenta ?? 'Facturapi';
        $i->save();
    }

    /** Prueba la clave: pide una factura a Facturapi */
    public static function probar(): array
    {
        try {
            $r = self::http()->get(self::API . '/invoices', ['limit' => 1]);
            return $r->successful() ? [true, 'La conexión con Facturapi funciona.'] : [false, 'Facturapi respondió: ' . ($r->json('message') ?: $r->status())];
        } catch (\Throwable $e) {
            return [false, 'No se pudo conectar con Facturapi: ' . Str::limit($e->getMessage(), 120)];
        }
    }

    public static function http()
    {
        return Http::withToken((string) self::clave())->acceptJson()->timeout(40);
    }

    /** Arma la factura, la timbra y guarda su PDF y XML en el documento */
    public static function timbrar(Documento $doc, string $formaSat, string $metodo, ?string $uso = null): void
    {
        if (! self::activo()) throw new TimbradoError('Activa la facturación en Configuración › Facturación.');
        if ($doc->uuid()) throw new TimbradoError('Ya tiene factura.');
        if (! $doc->fiscalesCompletos()) throw new TimbradoError('Faltan datos fiscales del cliente (RFC, razón social, régimen y código postal).');
        $f = $doc->fiscales();
        $aj = self::ajustes();

        $payload = array_filter([
            'customer' => array_filter([
                'legal_name' => mb_strtoupper(trim($f['razon_social'])), 'tax_id' => mb_strtoupper(trim($f['rfc'])),
                'tax_system' => (string) $f['regimen'], 'email' => $f['email'] ?: null, 'address' => ['zip' => (string) $f['cp']],
            ]),
            'items' => self::conceptos($doc, $aj),
            'payment_form' => $metodo === 'PPD' ? '99' : $formaSat,
            'payment_method' => $metodo,
            'use' => $uso ?: $f['uso'] ?: 'G03',
            'series' => $aj['serie'] ?: null,
            'external_id' => $doc->clave(),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $r = self::http()->post(self::API . '/invoices', $payload);
        } catch (\Throwable $e) {
            throw new TimbradoError('Facturapi no respondió. Intenta de nuevo en un momento.');
        }
        if (! $r->successful()) throw new TimbradoError((string) ($r->json('message') ?: 'Facturapi rechazó la factura (' . $r->status() . ').'));
        $id = (string) $r->json('id');
        $nombre = 'Factura ' . trim(($r->json('series') ?? '') . ($r->json('folio_number') ?? $doc->folio()));
        foreach (['pdf' => 'application/pdf', 'xml' => 'application/xml'] as $ext => $mime) {
            $a = self::http()->withHeaders(['Accept' => '*/*'])->get(self::API . "/invoices/{$id}/{$ext}");
            if (! $a->successful()) throw new TimbradoError("La factura se generó (folio fiscal {$r->json('uuid')}), pero no se pudo descargar su " . strtoupper($ext) . '. Descárgala desde Facturapi.');
            Archivos::guardar($doc, "{$nombre}.{$ext}", $a->body(), $mime, 'nerby', $id);
        }
    }

    public const MOTIVOS = ['02' => 'Tiene errores (se hará otra)', '03' => 'La venta no se realizó'];

    /**
     * Cancela ante el SAT la factura que timbró Nerby. Si el SAT la cancela, el documento vuelve a "Por facturar";
     * si el cliente debe aceptarla, queda pendiente hasta que se revise otra vez.
     * @return string 'cancelada' | 'pendiente'
     */
    public static function cancelar(Documento $doc, string $motivo): string
    {
        $id = $doc->facturapiId();
        if (! $id) throw new TimbradoError('Solo se pueden cancelar aquí las facturas generadas en Nerby. Las demás cancélalas donde las hiciste.');
        if (! array_key_exists($motivo, self::MOTIVOS)) throw new TimbradoError('Elige el motivo de la cancelación.');
        if (! filled(self::clave())) throw new TimbradoError('La facturación no está activa.');
        try {
            $r = self::http()->delete(self::API . "/invoices/$id?" . http_build_query(['motive' => $motivo]));
        } catch (\Throwable) {
            throw new TimbradoError('Facturapi no respondió. Intenta de nuevo en un momento.');
        }
        if (! $r->successful()) throw new TimbradoError((string) ($r->json('message') ?: 'No se pudo cancelar (' . $r->status() . ').'));
        return self::marcarCancelacion($doc, $r->json());
    }

    /** Pregunta otra vez si el cliente ya aceptó la cancelación */
    public static function revisarCancelacion(Documento $doc): string
    {
        $id = $doc->facturapiId();
        if (! $id) throw new TimbradoError('Esta factura no se generó en Nerby.');
        $r = self::http()->get(self::API . "/invoices/$id");
        if (! $r->successful()) throw new TimbradoError((string) ($r->json('message') ?: 'No se pudo revisar (' . $r->status() . ').'));
        return self::marcarCancelacion($doc, $r->json());
    }

    private static function marcarCancelacion(Documento $doc, ?array $inv): string
    {
        $estado = ($inv['status'] ?? null) === 'canceled' ? 'cancelada'
            : (in_array($inv['cancellation_status'] ?? null, ['pending', 'verifying'], true) ? 'pendiente'
            : (($inv['cancellation_status'] ?? null) === 'rejected' ? 'rechazada' : 'pendiente'));
        foreach ($doc->archivos() as $f) {
            if ($estado === 'cancelada') $f->update(['tipo' => 'cancelada', 'cancelacion' => 'cancelada', 'cancelada_at' => now()]);
            else $f->update(['cancelacion' => $estado === 'rechazada' ? null : 'pendiente']);
        }
        return $estado;
    }

    /** Conceptos para Facturapi: precio sin IVA, con el IVA del documento */
    private static function conceptos(Documento $doc, array $aj): array
    {
        $m = $doc->m;
        $items = [];
        if ($m instanceof Presupuesto) {
            $tasa = $m->modo_iva === 'sin_iva' ? null : round($m->iva_porcentaje / 100, 4);
            foreach ($m->conceptos as $c) {
                $items[] = self::item($c->resumen ?: 'Servicio', (float) $c->cantidad, (float) $c->precio, $c->producto_id ? $aj['clave_unidad'] : $aj['clave_servicio'], $aj['clave_prod'], $tasa);
            }
        } elseif ($m instanceof Venta) {
            $m->loadMissing('conceptos');
            $tasa = $m->con_iva ? round($m->iva_porcentaje / 100, 4) : null;
            $base = max(0.01, (float) $m->subtotal);
            foreach ($m->conceptos as $c) {
                $it = self::item($c->descripcion ?: 'Producto', (float) $c->cantidad, (float) $c->precio, $aj['clave_unidad'], $aj['clave_prod'], $tasa);
                // El descuento de la venta se reparte en proporción al importe de cada concepto
                if ($m->descuento > 0) $it['discount'] = round($m->descuento * ((float) $c->importe / $base), 2);
                $items[] = $it;
            }
            if ($m->envio_costo > 0) $items[] = self::item('Envío', 1, (float) $m->envio_costo, $aj['clave_servicio'], '78102203', $tasa);
        }
        if (! $items) throw new TimbradoError('No tiene conceptos para facturar.');
        return $items;
    }

    private static function item(string $desc, float $cant, float $precio, string $unidad, string $clave, ?float $tasa): array
    {
        return ['quantity' => $cant, 'product' => [
            'description' => Str::limit(trim($desc), 1000, ''), 'product_key' => $clave, 'unit_key' => $unidad,
            'price' => round($precio, 6), 'tax_included' => false, 'taxes' => $tasa !== null ? [['type' => 'IVA', 'rate' => $tasa]] : [],
        ]];
    }
}
