<?php

// Script de prueba para envío y autorización SRI usando XML firmado.

require_once __DIR__ . '/../class/SriClientEC.php';

$xmlPath = $argv[1] ?? getenv('FACTURAEC_SIGNED_XML') ?? (__DIR__ . '/../xml/ejemplo_factura_sri_firmado.xml');

if (!file_exists($xmlPath)) {
    echo "No se encontró el XML firmado: {$xmlPath}\n";
    exit(1);
}

$xmlSigned = file_get_contents($xmlPath);
if ($xmlSigned === false) {
    echo "No se pudo leer el XML firmado\n";
    exit(1);
}

$config = array(
    'ambiente' => getenv('FACTURAEC_AMBIENTE') ?: 1,
    'timeout' => getenv('FACTURAEC_TIMEOUT') ?: 30,
    'ruta_logs' => __DIR__ . '/../logs',
);

$client = new SriClientEC($config);

try {
    $recepcion = $client->enviarComprobante($xmlSigned);
    echo "Estado recepcion: {$recepcion['estado']}\n";
    foreach ($recepcion['mensajes'] as $msg) {
        echo "- {$msg['tipo']} {$msg['identificador']}: {$msg['mensaje']} ({$msg['informacionAdicional']})\n";
    }

    $claveAcceso = extractClaveAcceso($xmlSigned);
    if ($claveAcceso && $recepcion['estado'] === 'RECIBIDA') {
        $aut = $client->consultarAutorizacion($claveAcceso);
        echo "Estado autorizacion: {$aut['estado']}\n";
        foreach ($aut['autorizaciones'] as $item) {
            echo "Autorizacion {$item['numeroAutorizacion']} - {$item['estado']} - {$item['fechaAutorizacion']}\n";
            foreach ($item['mensajes'] as $msg) {
                echo "  * {$msg['tipo']} {$msg['identificador']}: {$msg['mensaje']} ({$msg['informacionAdicional']})\n";
            }
        }
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Extraer claveAcceso del XML firmado.
 *
 * @param string $xmlSigned
 * @return string|null
 */
function extractClaveAcceso($xmlSigned)
{
    $doc = new DOMDocument();
    if (!$doc->loadXML($xmlSigned)) {
        return null;
    }
    $nodes = $doc->getElementsByTagName('claveAcceso');
    if ($nodes->length > 0) {
        return $nodes->item(0)->textContent;
    }
    return null;
}
