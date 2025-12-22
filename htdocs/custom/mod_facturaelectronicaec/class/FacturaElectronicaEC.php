<?php

// Factura electronica Ecuador - XML generator for SRI.

/**
 * Class FacturaElectronicaEC
 *
 * Build XML for factura according to SRI schema.
 */
class FacturaElectronicaEC
{
    /** @var DoliDB */
    private $db;

    /** @var array */
    private $config;

    /**
     * Constructor.
     *
     * @param DoliDB $db
     * @param array  $config
     */
    public function __construct($db, array $config = array())
    {
        $this->db = $db;
        $this->config = $config;
    }

    /**
     * Build factura XML without signature.
     *
     * @param Facture $facture
     * @return string
     */
    public function buildFacturaXML($facture)
    {
        $this->assertRequiredFields($facture);

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $factura = $doc->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', '1.0.0');

        $infoTributaria = $doc->createElement('infoTributaria');
        $infoTributaria->appendChild($doc->createElement('ambiente', $this->configValue('ambiente', '1')));
        $infoTributaria->appendChild($doc->createElement('tipoEmision', '1'));
        $infoTributaria->appendChild($doc->createElement('razonSocial', $this->configValue('razon_social')));
        $infoTributaria->appendChild($doc->createElement('nombreComercial', $this->configValue('nombre_comercial')));
        $infoTributaria->appendChild($doc->createElement('ruc', $this->configValue('ruc')));
        $infoTributaria->appendChild($doc->createElement('claveAcceso', $this->generateClaveAcceso($facture)));
        $infoTributaria->appendChild($doc->createElement('codDoc', '01'));
        $infoTributaria->appendChild($doc->createElement('estab', $this->configValue('establecimiento')));
        $infoTributaria->appendChild($doc->createElement('ptoEmi', $this->configValue('punto_emision')));
        $infoTributaria->appendChild($doc->createElement('secuencial', $this->formatSecuencial($facture->ref)));
        $infoTributaria->appendChild($doc->createElement('dirMatriz', $this->configValue('direccion_matriz')));

        $factura->appendChild($infoTributaria);

        $infoFactura = $doc->createElement('infoFactura');
        $infoFactura->appendChild($doc->createElement('fechaEmision', $this->formatFechaEmision($facture->date)));
        $infoFactura->appendChild($doc->createElement('dirEstablecimiento', $this->configValue('direccion_matriz')));
        $infoFactura->appendChild($doc->createElement('obligadoContabilidad', 'NO'));
        $infoFactura->appendChild($doc->createElement('tipoIdentificacionComprador', $this->tipoIdentificacionComprador($facture)));
        $infoFactura->appendChild($doc->createElement('razonSocialComprador', $facture->thirdparty->name));
        $infoFactura->appendChild($doc->createElement('identificacionComprador', $this->identificacionComprador($facture)));

        $totals = $this->calculateTotals($facture);
        $totalImpuestos = $doc->createElement('totalConImpuestos');
        foreach ($totals['impuestos'] as $impuesto) {
            $totalImpuesto = $doc->createElement('totalImpuesto');
            $totalImpuesto->appendChild($doc->createElement('codigo', $impuesto['codigo']));
            $totalImpuesto->appendChild($doc->createElement('codigoPorcentaje', $impuesto['codigo_porcentaje']));
            $totalImpuesto->appendChild($doc->createElement('baseImponible', $this->formatAmount($impuesto['base'])));
            $totalImpuesto->appendChild($doc->createElement('valor', $this->formatAmount($impuesto['valor'])));
            $totalImpuestos->appendChild($totalImpuesto);
        }

        $infoFactura->appendChild($totalImpuestos);
        $infoFactura->appendChild($doc->createElement('totalSinImpuestos', $this->formatAmount($totals['total_sin_impuestos'])));
        $infoFactura->appendChild($doc->createElement('importeTotal', $this->formatAmount($totals['total'])));
        $infoFactura->appendChild($doc->createElement('moneda', $facture->multicurrency_code ?: 'USD'));

        $factura->appendChild($infoFactura);

        $detalles = $doc->createElement('detalles');
        foreach ($facture->lines as $line) {
            $detalle = $doc->createElement('detalle');
            $detalle->appendChild($doc->createElement('descripcion', $line->desc));
            $detalle->appendChild($doc->createElement('cantidad', $this->formatAmount($line->qty)));
            $detalle->appendChild($doc->createElement('precioUnitario', $this->formatAmount($line->subprice)));
            $detalle->appendChild($doc->createElement('descuento', $this->formatAmount($line->remise_percent ? ($line->subprice * $line->qty * $line->remise_percent / 100) : 0)));
            $detalle->appendChild($doc->createElement('precioTotalSinImpuesto', $this->formatAmount($line->total_ht)));

            $impuestos = $doc->createElement('impuestos');
            foreach ($this->taxesForLine($line) as $tax) {
                $impuesto = $doc->createElement('impuesto');
                $impuesto->appendChild($doc->createElement('codigo', $tax['codigo']));
                $impuesto->appendChild($doc->createElement('codigoPorcentaje', $tax['codigo_porcentaje']));
                $impuesto->appendChild($doc->createElement('tarifa', $this->formatAmount($tax['tarifa'])));
                $impuesto->appendChild($doc->createElement('baseImponible', $this->formatAmount($tax['base'])));
                $impuesto->appendChild($doc->createElement('valor', $this->formatAmount($tax['valor'])));
                $impuestos->appendChild($impuesto);
            }
            $detalle->appendChild($impuestos);
            $detalles->appendChild($detalle);
        }

        $factura->appendChild($detalles);
        $doc->appendChild($factura);

        return $doc->saveXML();
    }

    /**
     * Generate claveAcceso according to SRI spec.
     *
     * @param Facture $facture
     * @return string
     */
    public function generateClaveAcceso($facture)
    {
        $fecha = $this->formatFechaClave($facture->date);
        $tipoComprobante = '01';
        $ruc = $this->configValue('ruc');
        $ambiente = $this->configValue('ambiente', '1');
        $serie = $this->configValue('establecimiento') . $this->configValue('punto_emision');
        $secuencial = $this->formatSecuencial($facture->ref);
        $codigoNumerico = str_pad((string) $facture->id, 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1';

        $base = $fecha . $tipoComprobante . $ruc . $ambiente . $serie . $secuencial . $codigoNumerico . $tipoEmision;
        return $base . $this->modulo11($base);
    }

    /**
     * Validate required fields.
     *
     * @param Facture $facture
     * @return void
     */
    private function assertRequiredFields($facture)
    {
        $required = array(
            'ambiente' => $this->configValue('ambiente'),
            'ruc' => $this->configValue('ruc'),
            'razon_social' => $this->configValue('razon_social'),
            'nombre_comercial' => $this->configValue('nombre_comercial'),
            'establecimiento' => $this->configValue('establecimiento'),
            'punto_emision' => $this->configValue('punto_emision'),
            'direccion_matriz' => $this->configValue('direccion_matriz'),
        );

        foreach ($required as $key => $value) {
            if ($value === '') {
                throw new Exception('Missing required config: ' . $key);
            }
        }

        if (empty($facture->thirdparty)) {
            throw new Exception('Missing thirdparty on invoice');
        }
    }

    /**
     * Calculate totals and taxes.
     *
     * @param Facture $facture
     * @return array
     */
    private function calculateTotals($facture)
    {
        $totals = array(
            'total_sin_impuestos' => 0,
            'total' => 0,
            'impuestos' => array(),
        );

        $iva12Base = 0;
        $iva12Valor = 0;
        $iva0Base = 0;

        foreach ($facture->lines as $line) {
            $totals['total_sin_impuestos'] += $line->total_ht;
            $totals['total'] += $line->total_ttc;

            if ((float) $line->tva_tx > 0) {
                $iva12Base += $line->total_ht;
                $iva12Valor += $line->total_tva;
            } else {
                $iva0Base += $line->total_ht;
            }
        }

        if ($iva12Base > 0) {
            $totals['impuestos'][] = array(
                'codigo' => '2',
                'codigo_porcentaje' => '2',
                'base' => $iva12Base,
                'valor' => $iva12Valor,
            );
        }

        if ($iva0Base > 0) {
            $totals['impuestos'][] = array(
                'codigo' => '2',
                'codigo_porcentaje' => '0',
                'base' => $iva0Base,
                'valor' => 0,
            );
        }

        return $totals;
    }

    /**
     * Taxes per line.
     *
     * @param object $line
     * @return array
     */
    private function taxesForLine($line)
    {
        $taxes = array();
        $codigoPorcentaje = ((float) $line->tva_tx > 0) ? '2' : '0';
        $tarifa = ((float) $line->tva_tx > 0) ? $line->tva_tx : 0;

        $taxes[] = array(
            'codigo' => '2',
            'codigo_porcentaje' => $codigoPorcentaje,
            'tarifa' => $tarifa,
            'base' => $line->total_ht,
            'valor' => $line->total_tva,
        );

        return $taxes;
    }

    /**
     * Format amount.
     *
     * @param float $amount
     * @return string
     */
    private function formatAmount($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Format invoice date (dd/mm/yyyy).
     *
     * @param int $timestamp
     * @return string
     */
    private function formatFechaEmision($timestamp)
    {
        return dol_print_date($timestamp, '%d/%m/%Y');
    }

    /**
     * Format clave date (ddmmyyyy).
     *
     * @param int $timestamp
     * @return string
     */
    private function formatFechaClave($timestamp)
    {
        return dol_print_date($timestamp, '%d%m%Y');
    }

    /**
     * Format secuencial from invoice ref.
     *
     * @param string $ref
     * @return string
     */
    private function formatSecuencial($ref)
    {
        $digits = preg_replace('/\D/', '', $ref);
        if ($digits === '') {
            $digits = '1';
        }
        return str_pad($digits, 9, '0', STR_PAD_LEFT);
    }

    /**
     * Identify buyer doc type.
     *
     * @param Facture $facture
     * @return string
     */
    private function tipoIdentificacionComprador($facture)
    {
        if (!empty($facture->thirdparty->idprof1)) {
            $length = strlen($facture->thirdparty->idprof1);
            if ($length === 13) {
                return '04';
            }
            if ($length === 10) {
                return '05';
            }
        }
        return '07';
    }

    /**
     * Buyer identification.
     *
     * @param Facture $facture
     * @return string
     */
    private function identificacionComprador($facture)
    {
        return $facture->thirdparty->idprof1 ?: '9999999999999';
    }

    /**
     * Get config value.
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    private function configValue($key, $default = '')
    {
        return isset($this->config[$key]) ? (string) $this->config[$key] : $default;
    }

    /**
     * Modulo 11.
     *
     * @param string $value
     * @return int
     */
    private function modulo11($value)
    {
        $baseMultiplicador = 2;
        $total = 0;

        for ($i = strlen($value) - 1; $i >= 0; $i--) {
            $total += (int) $value[$i] * $baseMultiplicador;
            $baseMultiplicador++;
            if ($baseMultiplicador > 7) {
                $baseMultiplicador = 2;
            }
        }

        $mod = 11 - ($total % 11);
        if ($mod === 11) {
            $mod = 0;
        } elseif ($mod === 10) {
            $mod = 1;
        }

        return $mod;
    }
}
