<?php
// Trigger for Factura Electronica Ecuador integration with invoices.

if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
    define('NOREQUIRETRAN', '1');
}
if (!defined('NOCSRFCHECK')) {
    define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOLOGIN')) {
    define('NOLOGIN', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}

/**
 * Trigger class to integrate invoice validation with SRI flow.
 */
class InterfaceFacturaElectronicaEC
{
    /**
     * Name
     *
     * @var string
     */
    public $name = 'InterfaceFacturaElectronicaEC';

    /**
     * Description
     *
     * @var string
     */
    public $description = 'Envio de factura electronica al validar factura.';

    /**
     *
     * @var DoliDB
     */
    public $db;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs;

        $this->db = $db;
        $langs->load('facturaelectronicaec@mod_facturaelectronicaec');
    }

    /**
     * Execute trigger.
     *
     * @param string    $action Event action code
     * @param CommonObject $object Object
     * @param User      $user   User
     * @param Translate $langs  Langs
     * @param Conf      $conf   Conf
     * @return int               <0 if KO, 0 if no action, >0 if OK
     */
    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if ($action !== 'BILL_VALIDATE') {
            return 0;
        }

        if ($object->element !== 'facture') {
            return 0;
        }

        require_once __DIR__ . '/../../class/FacturaElectronicaEC.php';
        require_once __DIR__ . '/../../class/FirmaXadesEC.php';
        require_once __DIR__ . '/../../class/SriClientEC.php';

        $config = $this->collectConfig($conf);
        $claveAcceso = '';
        $estadoFinal = 'PENDIENTE';
        $numeroAutorizacion = '';
        $fechaAutorizacion = '';
        $rutaFirmado = '';
        $rutaAutorizado = '';
        $rutaPdfAutorizado = '';
        $payloadRespuesta = array();

        try {
            $builder = new FacturaElectronicaEC($this->db, $config);
            $unsignedXml = $builder->buildFacturaXML($object);
            $claveAcceso = $builder->generateClaveAcceso($object);

            $signer = new FirmaXadesEC();
            $signedXml = $signer->sign($unsignedXml, $config['cert_path'], $config['cert_password']);

            $rutaFirmado = $this->storeXml($config['ruta_xml'], $claveAcceso, $signedXml, 'firmado');

            $client = new SriClientEC($config);
            $recepcion = $client->enviarComprobante($signedXml);
            $payloadRespuesta['recepcion'] = $recepcion;

            if (isset($recepcion['estado'])) {
                $estadoFinal = $recepcion['estado'];
            }

            if ($estadoFinal === 'RECIBIDA') {
                $autorizacion = $client->consultarAutorizacion($claveAcceso);
                $payloadRespuesta['autorizacion'] = $autorizacion;

                if (!empty($autorizacion['autorizaciones'][0])) {
                    $auth = $autorizacion['autorizaciones'][0];
                    $estadoFinal = $auth['estado'] ?: $autorizacion['estado'];
                    $numeroAutorizacion = $auth['numeroAutorizacion'];
                    $fechaAutorizacion = $auth['fechaAutorizacion'];
                    if (!empty($auth['comprobante'])) {
                        $rutaAutorizado = $this->storeXml($config['ruta_xml'], $claveAcceso, $auth['comprobante'], 'autorizado');
                    }
                    if ($estadoFinal === 'AUTORIZADO') {
                        $rutaPdfAutorizado = $this->generateAuthorizedPdf($object, $claveAcceso, $numeroAutorizacion, $fechaAutorizacion, $rutaAutorizado);
                        if (!empty($rutaPdfAutorizado)) {
                            $payloadRespuesta['pdf_autorizado'] = $rutaPdfAutorizado;
                        }
                    }
                }
            }

            if ($estadoFinal === 'RECIBIDA') {
                $estadoFinal = 'EN_PROCESO';
            }

            $this->saveTracking($object->id, $claveAcceso, $estadoFinal, $numeroAutorizacion, $fechaAutorizacion, $rutaFirmado, $rutaAutorizado, json_encode($payloadRespuesta));

            if ($estadoFinal !== 'AUTORIZADO') {
                setEventMessages($langs->trans('FacturaElectronicaEC_StatusWarning', $estadoFinal), null, 'warnings');
            } else {
                setEventMessages($langs->trans('FacturaElectronicaEC_StatusOk', $numeroAutorizacion), null, 'mesgs');
            }
        } catch (Exception $e) {
            $this->saveTracking($object->id, $claveAcceso, 'DEVUELTO', $numeroAutorizacion, $fechaAutorizacion, $rutaFirmado, $rutaAutorizado, json_encode(array('error' => $e->getMessage())));
            setEventMessages($e->getMessage(), null, 'errors');
            return -1;
        }

        return 1;
    }

    /**
     * Gather config from dolibarr constants.
     *
     * @param Conf $conf
     * @return array
     */
    private function collectConfig($conf)
    {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

        $rutaXml = getDolGlobalString('FACTURAELECTRONICAEC_RUTA_XML');
        if (empty($rutaXml)) {
            $rutaXml = $conf->dol_data_root . '/mod_facturaelectronicaec/xml';
        }
        dol_mkdir($rutaXml);

        $rutaLogs = getDolGlobalString('FACTURAELECTRONICAEC_RUTA_LOGS');
        if (empty($rutaLogs)) {
            $rutaLogs = $conf->dol_data_root . '/mod_facturaelectronicaec/logs';
        }
        dol_mkdir($rutaLogs);

        return array(
            'ambiente' => getDolGlobalInt('FACTURAELECTRONICAEC_AMBIENTE', 1),
            'ruc' => getDolGlobalString('FACTURAELECTRONICAEC_RUC'),
            'razon_social' => getDolGlobalString('FACTURAELECTRONICAEC_RAZON_SOCIAL'),
            'nombre_comercial' => getDolGlobalString('FACTURAELECTRONICAEC_NOMBRE_COMERCIAL'),
            'establecimiento' => getDolGlobalString('FACTURAELECTRONICAEC_ESTABLECIMIENTO'),
            'punto_emision' => getDolGlobalString('FACTURAELECTRONICAEC_PUNTO_EMISION'),
            'direccion_matriz' => getDolGlobalString('FACTURAELECTRONICAEC_DIRECCION_MATRIZ'),
            'cert_path' => getDolGlobalString('FACTURAELECTRONICAEC_CERT_PATH'),
            'cert_password' => getDolGlobalString('FACTURAELECTRONICAEC_CERT_PASSWORD'),
            'timeout' => getDolGlobalInt('FACTURAELECTRONICAEC_TIMEOUT', 30),
            'ruta_xml' => $rutaXml,
            'ruta_logs' => $rutaLogs,
        );
    }

    /**
     * Generate a PDF with authorization data and attach it to the invoice document directory.
     *
     * @param Facture $object
     * @param string  $claveAcceso
     * @param string  $numeroAutorizacion
     * @param string  $fechaAutorizacion
     * @param string  $rutaXmlAutorizado
     * @return string Path to generated PDF or empty string on failure.
     */
    private function generateAuthorizedPdf($object, $claveAcceso, $numeroAutorizacion, $fechaAutorizacion, $rutaXmlAutorizado)
    {
        global $conf, $langs;

        require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

        $langs->loadLangs(array('main', 'bills', 'companies', 'facturaelectronicaec@mod_facturaelectronicaec'));

        $dirOutput = !empty($conf->facture->dir_output) ? $conf->facture->dir_output : $conf->dol_data_root . '/facture';
        $dirOutput .= '/' . $object->ref;
        dol_mkdir($dirOutput);

        $formatArray = pdf_getFormat();
        $pdf = pdf_getInstance($formatArray);
        if (!is_object($pdf)) {
            return '';
        }

        $pdf->SetAutoPageBreak(true, 0);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $defaultFont = pdf_getPDFFont($langs);
        $marginLeft = 15;
        $marginTop = 20;

        $pdf->AddPage();
        $pdf->SetFont($defaultFont, 'B', 12);
        $pdf->SetXY($marginLeft, $marginTop);
        $pdf->MultiCell(0, 6, $langs->trans('FacturaElectronicaEC_PdfTitle'), 0, 'L');

        $pdf->Ln(4);
        $pdf->SetFont($defaultFont, '', 10);
        $pdf->MultiCell(0, 5, $langs->trans('Invoice') . ': ' . $object->ref, 0, 'L');

        if (empty($object->thirdparty) && method_exists($object, 'fetch_thirdparty')) {
            $object->fetch_thirdparty();
        }

        if (!empty($object->thirdparty->name)) {
            $pdf->MultiCell(0, 5, $langs->trans('Customer') . ': ' . $object->thirdparty->name, 0, 'L');
        }

        $pdf->Ln(2);
        $pdf->SetFont($defaultFont, '', 9);
        $pdf->MultiCell(0, 5, $langs->trans('FacturaElectronicaEC_AuthorizationNumber', $numeroAutorizacion), 0, 'L');
        $pdf->MultiCell(0, 5, $langs->trans('FacturaElectronicaEC_AuthorizationDate', $fechaAutorizacion), 0, 'L');
        $pdf->MultiCell(0, 5, $langs->trans('FacturaElectronicaEC_AccessKey', $claveAcceso), 0, 'L');

        if (!empty($rutaXmlAutorizado)) {
            $pdf->Ln(2);
            $pdf->MultiCell(0, 5, $langs->trans('FacturaElectronicaEC_AuthorizedXml', $rutaXmlAutorizado), 0, 'L');
        }

        $pdfFile = $dirOutput . '/' . $object->ref . '-sri.pdf';
        $pdf->Output($pdfFile, 'F');

        return $pdfFile;
    }

    /**
     * Save XML file with naming convention.
     *
     * @param string $basePath
     * @param string $claveAcceso
     * @param string $xml
     * @param string $suffix
     * @return string
     */
    private function storeXml($basePath, $claveAcceso, $xml, $suffix)
    {
        $filename = rtrim($basePath, '/\\') . '/' . $claveAcceso . '_' . $suffix . '.xml';
        file_put_contents($filename, $xml);
        return $filename;
    }

    /**
     * Persist tracking info in table.
     *
     * @param int    $factureId
     * @param string $claveAcceso
     * @param string $estado
     * @param string $numeroAutorizacion
     * @param string $fechaAutorizacion
     * @param string $rutaFirmado
     * @param string $rutaAutorizado
     * @param string $respuesta
     * @return void
     */
    private function saveTracking($factureId, $claveAcceso, $estado, $numeroAutorizacion, $fechaAutorizacion, $rutaFirmado, $rutaAutorizado, $respuesta)
    {
        $sql = 'INSERT INTO llx_facturaelectronicaec_doc (fk_facture, clave_acceso, estado, numero_autorizacion, fecha_autorizacion, ruta_xml_firmado, ruta_xml_autorizado, respuesta_sri, datec, tms) ' .
            'VALUES (:fk_facture, :clave_acceso, :estado, :numero_autorizacion, :fecha_autorizacion, :ruta_xml_firmado, :ruta_xml_autorizado, :respuesta_sri, NOW(), NOW()) ' .
            'ON DUPLICATE KEY UPDATE estado = VALUES(estado), numero_autorizacion = VALUES(numero_autorizacion), fecha_autorizacion = VALUES(fecha_autorizacion), ruta_xml_firmado = VALUES(ruta_xml_firmado), ruta_xml_autorizado = VALUES(ruta_xml_autorizado), respuesta_sri = VALUES(respuesta_sri), tms = NOW()';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':fk_facture', $factureId);
        $stmt->bindValue(':clave_acceso', $claveAcceso);
        $stmt->bindValue(':estado', $estado);
        $stmt->bindValue(':numero_autorizacion', $numeroAutorizacion);
        $stmt->bindValue(':fecha_autorizacion', $fechaAutorizacion);
        $stmt->bindValue(':ruta_xml_firmado', $rutaFirmado);
        $stmt->bindValue(':ruta_xml_autorizado', $rutaAutorizado);
        $stmt->bindValue(':respuesta_sri', $respuesta);
        $stmt->execute();
    }
}
