<?php
// Reintento manual de envío/consulta de autorización SRI usando XML firmado almacenado.
if (php_sapi_name() !== 'cli') {
    echo "Solo CLI" . PHP_EOL;
    exit(1);
}

if ($argc < 2) {
    echo "Uso: php reintentar_envio_sri.php <factura_id>" . PHP_EOL;
    exit(1);
}

require __DIR__ . '/../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once __DIR__ . '/../class/SriClientEC.php';
require_once __DIR__ . '/../class/FacturaElectronicaEC.php';

$factureId = (int) $argv[1];
$langs->load('facturaelectronicaec@mod_facturaelectronicaec');

$facture = new Facture($db);
if ($facture->fetch($factureId) <= 0) {
    echo "Factura no encontrada" . PHP_EOL;
    exit(1);
}

$config = collectConfigFromGlobals($conf);
$doc = fetchTracking($db, $factureId);
if (!$doc) {
    echo "No existe tracking previo para la factura" . PHP_EOL;
    exit(1);
}

if (empty($doc['ruta_xml_firmado']) || !is_readable($doc['ruta_xml_firmado'])) {
    echo "No se encuentra XML firmado para reenvío" . PHP_EOL;
    exit(1);
}

$claveAcceso = $doc['clave_acceso'];
$signedXml = file_get_contents($doc['ruta_xml_firmado']);
$payload = array();
$estadoFinal = 'PENDIENTE';
$rutaAutorizado = !empty($doc['ruta_xml_autorizado']) ? $doc['ruta_xml_autorizado'] : '';
$numeroAutorizacion = !empty($doc['numero_autorizacion']) ? $doc['numero_autorizacion'] : '';
$fechaAutorizacion = !empty($doc['fecha_autorizacion']) ? $doc['fecha_autorizacion'] : '';
$rutaPdfAutorizado = '';

try {
    $client = new SriClientEC($config);
    $recepcion = $client->enviarComprobante($signedXml);
    $payload['recepcion'] = $recepcion;
    $estadoFinal = $recepcion['estado'] ?? 'DEVUELTO';

    if ($estadoFinal === 'RECIBIDA') {
        $autorizacion = $client->consultarAutorizacion($claveAcceso);
        $payload['autorizacion'] = $autorizacion;

        if (!empty($autorizacion['autorizaciones'][0])) {
            $auth = $autorizacion['autorizaciones'][0];
            $estadoFinal = $auth['estado'] ?: $autorizacion['estado'];
            $numeroAutorizacion = $auth['numeroAutorizacion'];
            $fechaAutorizacion = $auth['fechaAutorizacion'];
            if (!empty($auth['comprobante'])) {
                $rutaAutorizado = storeXml($config['ruta_xml'], $claveAcceso, $auth['comprobante'], 'autorizado');
            }
            if ($estadoFinal === 'AUTORIZADO') {
                $rutaPdfAutorizado = generateAuthorizedPdf($facture, $claveAcceso, $numeroAutorizacion, $fechaAutorizacion, $rutaAutorizado);
                if (!empty($rutaPdfAutorizado)) {
                    $payload['pdf_autorizado'] = $rutaPdfAutorizado;
                }
            }
        }
    }

    if ($estadoFinal === 'RECIBIDA') {
        $estadoFinal = 'EN_PROCESO';
    }

    saveTracking($db, $factureId, $claveAcceso, $estadoFinal, $numeroAutorizacion, $fechaAutorizacion, $doc['ruta_xml_firmado'], $rutaAutorizado, json_encode($payload));
    logMessage($config, $facture->ref, 'Reintento completado con estado ' . $estadoFinal);

    echo 'Estado final: ' . $estadoFinal . PHP_EOL;
    if ($numeroAutorizacion) {
        echo 'Número de autorización: ' . $numeroAutorizacion . PHP_EOL;
    }
    if ($rutaAutorizado) {
        echo 'XML autorizado: ' . $rutaAutorizado . PHP_EOL;
    }
    if ($rutaPdfAutorizado) {
        echo 'PDF autorizado: ' . $rutaPdfAutorizado . PHP_EOL;
    }
} catch (Exception $e) {
    $estadoFinal = (stripos($e->getMessage(), 'soap') !== false) ? 'PENDIENTE_OFFLINE' : 'DEVUELTO';
    saveTracking($db, $factureId, $claveAcceso, $estadoFinal, $numeroAutorizacion, $fechaAutorizacion, $doc['ruta_xml_firmado'], $rutaAutorizado, json_encode(array('error' => $e->getMessage())));
    logMessage($config, $facture->ref, 'Reintento falló: ' . $e->getMessage());
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

/**
 * Obtener configuración desde llx_const.
 */
function collectConfigFromGlobals($conf)
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
        'timeout' => getDolGlobalInt('FACTURAELECTRONICAEC_TIMEOUT', 30),
        'ruta_xml' => $rutaXml,
        'ruta_logs' => $rutaLogs,
    );
}

/**
 * Buscar tracking existente.
 */
function fetchTracking($db, $factureId)
{
    $sql = 'SELECT * FROM llx_facturaelectronicaec_doc WHERE fk_facture = :id LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $factureId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Guardar tracking de reintento.
 */
function saveTracking($db, $factureId, $claveAcceso, $estado, $numeroAutorizacion, $fechaAutorizacion, $rutaFirmado, $rutaAutorizado, $respuesta)
{
    $sql = 'INSERT INTO llx_facturaelectronicaec_doc (fk_facture, clave_acceso, estado, numero_autorizacion, fecha_autorizacion, ruta_xml_firmado, ruta_xml_autorizado, respuesta_sri, datec, tms) '
        . 'VALUES (:fk_facture, :clave_acceso, :estado, :numero_autorizacion, :fecha_autorizacion, :ruta_xml_firmado, :ruta_xml_autorizado, :respuesta_sri, NOW(), NOW()) '
        . 'ON DUPLICATE KEY UPDATE estado = VALUES(estado), numero_autorizacion = VALUES(numero_autorizacion), fecha_autorizacion = VALUES(fecha_autorizacion), ruta_xml_firmado = VALUES(ruta_xml_firmado), ruta_xml_autorizado = VALUES(ruta_xml_autorizado), respuesta_sri = VALUES(respuesta_sri), tms = NOW()';

    $stmt = $db->prepare($sql);
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

/**
 * Guardar XML.
 */
function storeXml($basePath, $claveAcceso, $xml, $suffix)
{
    $filename = rtrim($basePath, '/\\') . '/' . $claveAcceso . '_' . $suffix . '.xml';
    file_put_contents($filename, $xml);
    return $filename;
}

/**
 * Generar PDF autorizado reutilizando helper de trigger.
 */
function generateAuthorizedPdf($object, $claveAcceso, $numeroAutorizacion, $fechaAutorizacion, $rutaXmlAutorizado)
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

    global $conf, $langs;
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
 * Log simple a archivo.
 */
function logMessage($config, $invoiceRef, $message)
{
    if (empty($config['ruta_logs'])) {
        return;
    }

    $line = sprintf('[%s] FACT-%s %s%s', date('Y-m-d H:i:s'), $invoiceRef, $message, PHP_EOL);
    @file_put_contents(rtrim($config['ruta_logs'], '/\\') . '/facturaelectronicaec.log', $line, FILE_APPEND);
}
