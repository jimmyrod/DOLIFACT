<?php

// Test script for XAdES-BES signing using sample SRI XML.

require_once __DIR__ . '/../class/FirmaXadesEC.php';

$certPath = getenv('FACTURAEC_CERT_PATH');
$certPassword = getenv('FACTURAEC_CERT_PASSWORD');

if (!$certPath || !$certPassword) {
    echo "Defina FACTURAEC_CERT_PATH y FACTURAEC_CERT_PASSWORD\n";
    exit(1);
}

$xmlPath = __DIR__ . '/../xml/ejemplo_factura_sri.xml';
$xml = file_get_contents($xmlPath);
if ($xml === false) {
    echo "No se pudo leer XML de ejemplo\n";
    exit(1);
}

$signer = new FirmaXadesEC();
$signed = $signer->sign($xml, $certPath, $certPassword);

$outputPath = __DIR__ . '/../xml/ejemplo_factura_sri_firmado.xml';
file_put_contents($outputPath, $signed);

echo "XML firmado guardado en: {$outputPath}\n";
