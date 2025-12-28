<?php

// Firma electronica XAdES-BES para SRI Ecuador.

/**
 * Class FirmaXadesEC
 *
 * Firma un XML de comprobante con XAdES-BES usando OpenSSL.
 */
class FirmaXadesEC
{
    /**
     * Firmar XML con certificado .p12/.pfx.
     *
     * @param string $xml
     * @param string $certPath
     * @param string $certPassword
     * @return string
     */
    public function sign($xml, $certPath, $certPassword)
    {
        $p12 = @file_get_contents($certPath);
        if ($p12 === false) {
            throw new Exception('No se pudo leer el certificado');
        }

        $certs = array();
        if (!openssl_pkcs12_read($p12, $certs, $certPassword)) {
            throw new Exception('No se pudo abrir el certificado');
        }

        if (empty($certs['pkey']) || empty($certs['cert'])) {
            throw new Exception('Certificado incompleto');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = false;
        if (!$doc->loadXML($xml)) {
            throw new Exception('XML inválido');
        }

        $root = $doc->documentElement;

        $signatureId = 'Signature-' . uniqid();
        $signedPropertiesId = 'SignedProperties-' . uniqid();
        $signature = $doc->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'ds:Signature');
        $signature->setAttribute('Id', $signatureId);

        $signedInfo = $doc->createElement('ds:SignedInfo');
        $canonicalizationMethod = $doc->createElement('ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signatureMethod = $doc->createElement('ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');
        $signedInfo->appendChild($canonicalizationMethod);
        $signedInfo->appendChild($signatureMethod);

        $reference = $doc->createElement('ds:Reference');
        $reference->setAttribute('URI', '');

        $transforms = $doc->createElement('ds:Transforms');
        $transformEnveloped = $doc->createElement('ds:Transform');
        $transformEnveloped->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transformC14N = $doc->createElement('ds:Transform');
        $transformC14N->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transformEnveloped);
        $transforms->appendChild($transformC14N);
        $reference->appendChild($transforms);

        $referenceDigestMethod = $doc->createElement('ds:DigestMethod');
        $referenceDigestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($referenceDigestMethod);

        $digestValue = $doc->createElement('ds:DigestValue', $this->digest($root->C14N(true, false)));
        $reference->appendChild($digestValue);
        $signedInfo->appendChild($reference);

        $referenceProps = $doc->createElement('ds:Reference');
        $referenceProps->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');
        $referenceProps->setAttribute('URI', '#' . $signedPropertiesId);
        $referencePropsDigestMethod = $doc->createElement('ds:DigestMethod');
        $referencePropsDigestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $referenceProps->appendChild($referencePropsDigestMethod);

        $signature->appendChild($signedInfo);

        $qualifyingProperties = $this->buildQualifyingProperties($doc, $certs['cert'], $signatureId, $signedPropertiesId);
        $referencePropsDigestValue = $doc->createElement('ds:DigestValue', $this->digest($qualifyingProperties->C14N(true, false)));
        $referenceProps->appendChild($referencePropsDigestValue);
        $signedInfo->appendChild($referenceProps);

        $signatureValue = $doc->createElement('ds:SignatureValue', $this->signData($signedInfo->C14N(true, false), $certs['pkey']));

        $keyInfo = $doc->createElement('ds:KeyInfo');
        $x509Data = $doc->createElement('ds:X509Data');
        $x509Cert = $doc->createElement('ds:X509Certificate', $this->formatCert($certs['cert']));
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);

        $signature->appendChild($signatureValue);
        $signature->appendChild($keyInfo);
        $signature->appendChild($qualifyingProperties);

        $root->appendChild($signature);

        return $doc->saveXML();
    }

    /**
     * Build XAdES QualifyingProperties.
     *
     * @param DOMDocument $doc
     * @param string      $cert
     * @param string      $signatureId
     * @param string      $signedPropertiesId
     * @return DOMElement
     */
    private function buildQualifyingProperties(DOMDocument $doc, $cert, $signatureId, $signedPropertiesId)
    {
        $qualifyingProperties = $doc->createElementNS('http://uri.etsi.org/01903/v1.3.2#', 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', '#' . $signatureId);

        $signedProperties = $doc->createElement('xades:SignedProperties');
        $signedProperties->setAttribute('Id', $signedPropertiesId);

        $signedSignatureProperties = $doc->createElement('xades:SignedSignatureProperties');
        $signedSignatureProperties->appendChild($doc->createElement('xades:SigningTime', gmdate('Y-m-d\TH:i:s\Z')));

        $signingCertificate = $doc->createElement('xades:SigningCertificate');
        $certElement = $doc->createElement('xades:Cert');
        $certDigest = $doc->createElement('xades:CertDigest');
        $digestMethod = $doc->createElement('ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $digestValue = $doc->createElement('ds:DigestValue', $this->digest($this->certToDer($cert)));
        $certDigest->appendChild($digestMethod);
        $certDigest->appendChild($digestValue);

        $issuerSerial = $doc->createElement('xades:IssuerSerial');
        $issuerName = $doc->createElement('ds:X509IssuerName', $this->certificateIssuer($cert));
        $serialNumber = $doc->createElement('ds:X509SerialNumber', $this->certificateSerial($cert));
        $issuerSerial->appendChild($issuerName);
        $issuerSerial->appendChild($serialNumber);

        $certElement->appendChild($certDigest);
        $certElement->appendChild($issuerSerial);
        $signingCertificate->appendChild($certElement);

        $signedSignatureProperties->appendChild($signingCertificate);
        $signedProperties->appendChild($signedSignatureProperties);

        $qualifyingProperties->appendChild($signedProperties);

        return $qualifyingProperties;
    }

    /**
     * Sign data with private key.
     *
     * @param string $data
     * @param string $pkey
     * @return string
     */
    private function signData($data, $pkey)
    {
        $signature = '';
        $resource = openssl_pkey_get_private($pkey);
        if (!$resource) {
            throw new Exception('No se pudo cargar la clave privada');
        }

        if (!openssl_sign($data, $signature, $resource, OPENSSL_ALGO_SHA256)) {
            throw new Exception('No se pudo firmar el XML');
        }

        return base64_encode($signature);
    }

    /**
     * Digest helper.
     *
     * @param string $data
     * @return string
     */
    private function digest($data)
    {
        return base64_encode(hash('sha256', $data, true));
    }

    /**
     * Extract cert DER bytes.
     *
     * @param string $certPem
     * @return string
     */
    private function certToDer($certPem)
    {
        $clean = str_replace(array('-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"), '', $certPem);
        return base64_decode($clean);
    }

    /**
     * Format certificate without PEM markers.
     *
     * @param string $certPem
     * @return string
     */
    private function formatCert($certPem)
    {
        $clean = str_replace(array('-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"), '', $certPem);
        return trim($clean);
    }

    /**
     * Certificate issuer name.
     *
     * @param string $certPem
     * @return string
     */
    private function certificateIssuer($certPem)
    {
        $data = openssl_x509_parse($certPem);
        return isset($data['issuer']) ? $this->formatDn($data['issuer']) : '';
    }

    /**
     * Certificate serial.
     *
     * @param string $certPem
     * @return string
     */
    private function certificateSerial($certPem)
    {
        $data = openssl_x509_parse($certPem);
        return isset($data['serialNumber']) ? (string) $data['serialNumber'] : '';
    }

    /**
     * Format DN array to string.
     *
     * @param array $dn
     * @return string
     */
    private function formatDn(array $dn)
    {
        $parts = array();
        foreach ($dn as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        return implode(',', $parts);
    }
}
