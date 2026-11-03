<?php

namespace Tests\Feature;

use App\Support\Facturas\Qr;
use App\Support\Facturas\Representacion;
use Tests\TestCase;

/** El PDF de la factura que arma Nerby desde el XML timbrado, con los datos que pide el SAT */
class RepresentacionCfdiTest extends TestCase
{
    private function xml(): string
    {
        $sello = str_repeat('A', 336) . 'pX9Lq2t=';
        return '<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Serie="A" Folio="7" Fecha="2026-10-26T10:42:18" FormaPago="99" MetodoPago="PPD" NoCertificado="00001000000512345678" SubTotal="702.00" Moneda="MXN" Total="814.32" TipoDeComprobante="I" Exportacion="01" LugarExpedicion="97100" Sello="' . $sello . '">
 <cfdi:Emisor Rfc="CAPG800101AB1" Nombre="GUADALUPE CANCHE PECH" RegimenFiscal="626"/>
 <cfdi:Receptor Rfc="HSO010101AB1" Nombre="HOTEL SOL" DomicilioFiscalReceptor="97000" RegimenFiscalReceptor="601" UsoCFDI="G03"/>
 <cfdi:Conceptos><cfdi:Concepto ClaveProdServ="50202306" Cantidad="20" ClaveUnidad="H87" Unidad="Pieza" Descripcion="Paleta de mango" ValorUnitario="35.10" Importe="702.00" ObjetoImp="02"><cfdi:Impuestos><cfdi:Traslados><cfdi:Traslado Base="702.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="112.32"/></cfdi:Traslados></cfdi:Impuestos></cfdi:Concepto></cfdi:Conceptos>
 <cfdi:Impuestos TotalImpuestosTrasladados="112.32"><cfdi:Traslados><cfdi:Traslado Base="702.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="112.32"/></cfdi:Traslados></cfdi:Impuestos>
 <cfdi:Complemento><tfd:TimbreFiscalDigital Version="1.1" UUID="1A2B3C4D-5E6F-4A7B-8C9D-0E1F2A3B4C5D" FechaTimbrado="2026-10-26T10:42:20" RfcProvCertif="SPR190613I52" SelloCFD="' . $sello . '" NoCertificadoSAT="00001000000505211329" SelloSAT="QmSAT=="/></cfdi:Complemento>
</cfdi:Comprobante>';
    }

    public function test_lee_el_xml_y_arma_lo_que_pide_el_sat(): void
    {
        $d = Representacion::leer($this->xml());
        $this->assertSame(['Factura', 'A', '7', 814.32, '1A2B3C4D-5E6F-4A7B-8C9D-0E1F2A3B4C5D'], [$d['titulo'], $d['serie'], $d['folio'], $d['total'], $d['uuid']]);
        $this->assertSame('OCHOCIENTOS CATORCE PESOS 32/100 M.N.', $d['letra']);
        $this->assertStringStartsWith('||1.1|1A2B3C4D-5E6F-4A7B-8C9D-0E1F2A3B4C5D|2026-10-26T10:42:20|SPR190613I52|', $d['cadena']);
        $this->assertStringEndsWith('|00001000000505211329||', $d['cadena']);
        $this->assertSame('https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx?id=1A2B3C4D-5E6F-4A7B-8C9D-0E1F2A3B4C5D&re=CAPG800101AB1&rr=HSO010101AB1&tt=0000000814.320000&fe=pX9Lq2t=', $d['qr_url']);
        $this->assertSame([['impuesto' => 'IVA', 'tasa' => '0.160000', 'factor' => 'Tasa', 'importe' => 112.32]], $d['traslados']);

        $pdf = Representacion::pdf($this->xml());
        $this->assertNotNull($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertNull(Representacion::pdf('<no-es-xml'));
    }

    public function test_importe_con_letra_y_qr(): void
    {
        $this->assertSame('VEINTIÚN PESOS 00/100 M.N.', Representacion::conLetra(21));
        $this->assertSame('UN MILLÓN DE PESOS 00/100 M.N.', Representacion::conLetra(1000000));
        $this->assertSame('CIENTO UN MIL QUINIENTOS PESOS 05/100 M.N.', Representacion::conLetra(101500.05));
        $this->assertCount(21, Qr::matriz('hola'));        // versión 1
        $this->assertCount(49, Qr::matriz(str_repeat('x', 150)));    // versión 8 (150 bytes, nivel M)
        $this->assertStringStartsWith('data:image/png;base64,', Qr::png('hola'));
    }
}
