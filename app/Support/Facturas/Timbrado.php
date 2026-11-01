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
        $globales = \Illuminate\Support\Facades\DB::table('facturas_globales')->where('cuenta_id', $c->id)->whereNotNull('facturapi_id')->where('created_at', '>=', $desde)->count();
        return $ids('venta_facturas')->merge($ids('presupuesto_facturas'))->unique()->count() + $globales;
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

    /** El cliente como lo pide Facturapi */
    public static function cliente(array $f): array
    {
        return array_filter([
            'legal_name' => mb_strtoupper(trim($f['razon_social'])), 'tax_id' => mb_strtoupper(trim($f['rfc'])),
            'tax_system' => (string) $f['regimen'], 'email' => $f['email'] ?: null, 'address' => ['zip' => (string) $f['cp']],
        ]);
    }

    /**
     * Timbra un CFDI: revisa timbres, pone serie y folio de Nerby, lo manda y descarga su PDF y XML.
     * El folio solo avanza si Facturapi lo aceptó; el timbre se gasta al quedar timbrado.
     * @return array{json: array, serie: string, folio: int, pdf: string, xml: string}
     */
    public static function emitir(array $payload, string $tipoSerie, ?int $serieId, string $referencia): array
    {
        if (! self::activo()) throw new TimbradoError('Activa la facturación en Configuración › Facturación.');
        Timbres::verificar();
        return \Illuminate\Support\Facades\DB::transaction(function () use ($payload, $tipoSerie, $serieId, $referencia) {
            $s = \App\Models\FacturaSerie::whereKey(Series::elegir($tipoSerie, $serieId)->id)->lockForUpdate()->first();
            $payload['series'] = $s->serie;
            $payload['folio_number'] = $s->siguiente;
            try {
                $r = self::http()->post(self::API . '/invoices', $payload);
            } catch (\Throwable $e) {
                throw new TimbradoError('Facturapi no respondió. Intenta de nuevo en un momento.');
            }
            if (! $r->successful()) throw new TimbradoError((string) ($r->json('message') ?: 'Facturapi rechazó el comprobante (' . $r->status() . ').'));
            $folio = $s->siguiente;
            $s->increment('siguiente');
            $id = (string) $r->json('id');
            Timbres::gastar("{$s->serie}-{$folio} · $referencia");
            $archivos = [];
            foreach (['pdf', 'xml'] as $ext) {
                $a = self::http()->withHeaders(['Accept' => '*/*'])->get(self::API . "/invoices/{$id}/{$ext}");
                $archivos[$ext] = $a->successful() ? $a->body() : null;
            }
            return ['json' => (array) $r->json(), 'id' => $id, 'serie' => $s->serie, 'folio' => $folio, 'pdf' => $archivos['pdf'], 'xml' => $archivos['xml']];
        });
    }

    /** Guarda el PDF y el XML timbrados en el documento (si alguno no bajó, avisa sin perder el otro) */
    private static function guardarArchivos(Documento $doc, array $e, string $prefijo, array $extra = []): void
    {
        $nombre = "$prefijo {$e['serie']}-{$e['folio']}";
        $falta = [];
        foreach (['pdf' => 'application/pdf', 'xml' => 'application/xml'] as $ext => $mime) {
            if ($e[$ext] === null) { $falta[] = strtoupper($ext); continue; }
            Archivos::guardar($doc, "{$nombre}.{$ext}", $e[$ext], $mime, 'nerby', $e['id'], $extra + ['serie' => $e['serie'], 'folio' => $e['folio'], 'uuid' => $e['json']['uuid'] ?? null]);
        }
        if ($falta) throw new TimbradoIncompleto("Se timbró ({$nombre}, folio fiscal " . ($e['json']['uuid'] ?? '—') . '), pero no se pudo descargar su ' . implode(' y ', $falta) . '. Descárgalo desde Facturapi.');
    }

    /** Factura de ingreso de una cotización o venta */
    public static function timbrar(Documento $doc, string $formaSat, string $metodo, ?string $uso = null, ?int $serieId = null): void
    {
        if ($doc->uuid()) throw new TimbradoError('Ya tiene factura.');
        if (! $doc->fiscalesCompletos()) throw new TimbradoError('Faltan datos fiscales del cliente (RFC, razón social, régimen y código postal).');
        $f = $doc->fiscales();
        $aj = self::ajustes();
        $uso = $uso ?: $f['uso'] ?: 'G03';
        $payload = [
            'customer' => self::cliente($f),
            'items' => self::conceptos($doc, $aj),
            'payment_form' => $metodo === 'PPD' ? '99' : $formaSat,
            'payment_method' => $metodo,
            'use' => $uso,
            'external_id' => $doc->clave(),
        ];
        $e = self::emitir($payload, 'I', $serieId, $doc->folio());
        if ($doc->m instanceof Venta) $doc->m->forceFill(['metodo_pago' => $metodo, 'uso_cfdi' => $uso])->save();
        self::guardarArchivos($doc, $e, 'Factura');
    }

    /** La factura de ingreso timbrada en Nerby de una venta (para complementos y notas de crédito) */
    private static function ingreso(Venta $v): \App\Models\VentaFactura
    {
        $f = $v->facturas()->where('tipo', 'factura')->whereNotNull('facturapi_id')->whereNotNull('uuid')->first();
        if (! $f) throw new TimbradoError('Primero timbra la factura de esta venta en Nerby.');
        return $f;
    }

    /**
     * Complemento de pago (REP) de un abono a una factura PPD: parcialidad, saldo anterior y su IVA proporcional.
     */
    public static function complemento(Venta $v, \App\Models\VentaPago $p): void
    {
        $ing = self::ingreso($v);
        if ($v->metodo_pago !== 'PPD') throw new TimbradoError('Solo las facturas PPD (pago en parcialidades) llevan complemento de pago.');
        if ($v->facturas()->where('tipo', 'complemento')->where('venta_pago_id', $p->id)->whereNull('cancelada_at')->exists()) throw new TimbradoError('Ese pago ya tiene complemento.');
        $doc = new Documento($v);
        if (! $doc->fiscalesCompletos()) throw new TimbradoError('Faltan datos fiscales del cliente.');
        $previos = $v->facturas()->where('tipo', 'complemento')->whereNotNull('venta_pago_id')->whereNull('cancelada_at')->get()->unique('facturapi_id');
        $total = (float) ($ing->total_cfdi ?: $v->total);
        $pagadoAntes = (float) $previos->sum('monto');
        $monto = round(min((float) $p->monto, $total - $pagadoAntes), 2);
        if ($monto <= 0) throw new TimbradoError('La factura ya está pagada por completo.');
        $tasa = $v->con_iva ? round($v->iva_porcentaje / 100, 4) : null;
        $doc_rel = ['uuid' => $ing->uuid, 'amount' => $monto, 'installment' => $previos->count() + 1, 'last_balance' => round($total - $pagadoAntes, 2),
            'taxes' => $tasa !== null ? [['base' => round($monto / (1 + $tasa), 2), 'type' => 'IVA', 'rate' => $tasa]] : []];
        $payload = [
            'type' => 'P',
            'customer' => self::cliente($doc->fiscales()),
            'complements' => [['type' => 'pago', 'data' => [[
                'payment_form' => self::formaDe($p->forma), 'date' => \Illuminate\Support\Carbon::parse($p->fecha->toDateString() . ' 12:00', config('vandu.zona_horaria'))->toIso8601String(),
                'related_documents' => [$doc_rel],
            ]]]],
            'external_id' => 'pago-' . $p->id,
        ];
        $e = self::emitir($payload, 'P', null, $v->folio . ' pago');
        self::guardarArchivos($doc, $e, 'Complemento', ['tipo' => 'complemento', 'venta_pago_id' => $p->id, 'monto' => $monto]);
    }

    /** Nota de crédito (egreso) relacionada a la factura: descuento, bonificación o devolución */
    public static function notaCredito(Venta $v, float $monto, string $motivo, string $formaSat): void
    {
        $ing = self::ingreso($v);
        $total = (float) ($ing->total_cfdi ?: $v->total);
        $yaNotas = (float) $v->facturas()->where('tipo', 'egreso')->whereNull('cancelada_at')->get()->unique('facturapi_id')->sum('monto');
        if ($monto <= 0 || $monto > round($total - $yaNotas, 2) + 0.01) throw new TimbradoError('La nota de crédito no puede ser mayor que lo que queda de la factura ($' . number_format($total - $yaNotas, 2) . ').');
        $doc = new Documento($v);
        $aj = self::ajustes();
        $tasa = $v->con_iva ? round($v->iva_porcentaje / 100, 4) : null;
        $base = $tasa !== null ? round($monto / (1 + $tasa), 6) : $monto;
        $payload = [
            'type' => 'E',
            'customer' => self::cliente($doc->fiscales()),
            'items' => [['quantity' => 1, 'product' => ['description' => Str::limit($motivo, 1000, ''), 'product_key' => '84111506', 'unit_key' => 'ACT',
                'price' => $base, 'tax_included' => false, 'taxes' => $tasa !== null ? [['type' => 'IVA', 'rate' => $tasa]] : []]]],
            'payment_form' => $formaSat,
            'use' => 'G02',
            'related_documents' => [['relationship' => '01', 'documents' => [$ing->uuid]]],
            'external_id' => $v->folio . '-nc-' . ($v->facturas()->where('tipo', 'egreso')->count() + 1),
        ];
        $e = self::emitir($payload, 'E', null, $v->folio . ' nota de crédito');
        self::guardarArchivos($doc, $e, 'Nota de crédito', ['tipo' => 'egreso', 'monto' => round($monto, 2), 'motivo' => Str::limit($motivo, 160, '')]);
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
        if ($doc->m instanceof Venta && $doc->m->facturas()->where('tipo', 'complemento')->whereNull('cancelada_at')->exists()) {
            throw new TimbradoError('Primero cancela sus complementos de pago.');
        }
        return self::marcar($doc->archivos(), self::cancelarId($id, $motivo));
    }

    /** Cancela un complemento o una nota de crédito (todos sus archivos comparten el mismo id) */
    public static function cancelarCfdi(\Illuminate\Support\Collection $archivos, string $motivo): string
    {
        $id = $archivos->first()?->facturapi_id;
        if (! $id) throw new TimbradoError('Este comprobante no se generó en Nerby.');
        return self::marcar($archivos, self::cancelarId($id, $motivo));
    }

    public static function cancelarId(string $id, string $motivo): ?array
    {
        if (! array_key_exists($motivo, self::MOTIVOS)) throw new TimbradoError('Elige el motivo de la cancelación.');
        if (! filled(self::clave())) throw new TimbradoError('La facturación no está activa.');
        try {
            $r = self::http()->delete(self::API . "/invoices/$id?" . http_build_query(['motive' => $motivo]));
        } catch (\Throwable) {
            throw new TimbradoError('Facturapi no respondió. Intenta de nuevo en un momento.');
        }
        if (! $r->successful()) throw new TimbradoError((string) ($r->json('message') ?: 'No se pudo cancelar (' . $r->status() . ').'));
        return $r->json();
    }

    /** Pregunta otra vez si el cliente ya aceptó la cancelación */
    public static function revisarCancelacion(Documento $doc): string
    {
        $id = $doc->facturapiId();
        if (! $id) throw new TimbradoError('Esta factura no se generó en Nerby.');
        $r = self::http()->get(self::API . "/invoices/$id");
        if (! $r->successful()) throw new TimbradoError((string) ($r->json('message') ?: 'No se pudo revisar (' . $r->status() . ').'));
        return self::marcar($doc->archivos(), $r->json());
    }

    public static function estadoCancelacion(?array $inv): string
    {
        return ($inv['status'] ?? null) === 'canceled' ? 'cancelada'
            : (($inv['cancellation_status'] ?? null) === 'rejected' ? 'rechazada' : 'pendiente');
    }

    private static function marcar($archivos, ?array $inv): string
    {
        $estado = self::estadoCancelacion($inv);
        foreach ($archivos as $f) {
            if ($estado === 'cancelada') $f->update(['tipo' => $f->tipo === 'factura' ? 'cancelada' : $f->tipo, 'cancelacion' => 'cancelada', 'cancelada_at' => now()]);
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
                $items[] = self::item($c->resumen ?: 'Servicio', (float) $c->cantidad, (float) $c->precio,
                    $c->producto?->unidad_sat ?: ($c->producto_id ? $aj['clave_unidad'] : $aj['clave_servicio']), $c->producto?->clave_sat ?: $aj['clave_prod'], $tasa);
            }
        } elseif ($m instanceof Venta) {
            $m->loadMissing('conceptos.producto');
            $tasa = $m->con_iva ? round($m->iva_porcentaje / 100, 4) : null;
            $base = max(0.01, (float) $m->subtotal);
            foreach ($m->conceptos as $c) {
                $it = self::item($c->descripcion ?: 'Producto', (float) $c->cantidad, (float) $c->precio,
                    $c->unidad_sat ?: $c->producto?->unidad_sat ?: $aj['clave_unidad'], $c->clave_sat ?: $c->producto?->clave_sat ?: $aj['clave_prod'], $tasa);
                // El descuento de la venta se reparte en proporción al importe de cada concepto
                if ($m->descuento > 0) $it['discount'] = round($m->descuento * ((float) $c->importe / $base), 2);
                $items[] = $it;
            }
            // En Nerby el envío no lleva IVA (así suma igual que la venta)
            if ($m->envio_costo > 0) $items[] = self::item('Envío', 1, (float) $m->envio_costo, $aj['clave_servicio'], '78102203', null);
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
