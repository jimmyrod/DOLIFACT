<?php

// Cliente SOAP para SRI Ecuador (Recepción y Autorización).

/**
 * Class SriClientEC
 *
 * Enviar comprobantes electrónicos firmados al SRI y consultar autorización.
 */
class SriClientEC
{
    /** @var array */
    private $config;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    /**
     * Enviar comprobante firmado al SRI (Recepción).
     *
     * @param string $xmlFirmado
     * @return array
     * @throws Exception
     */
    public function enviarComprobante($xmlFirmado)
    {
        if ($xmlFirmado === '' || !is_string($xmlFirmado)) {
            throw new Exception('XML firmado vacío');
        }

        $client = $this->buildSoapClient($this->recepcionWsdl());

        try {
            $response = $client->__soapCall('validarComprobante', array(array('xml' => base64_encode($xmlFirmado))));
        } catch (SoapFault $e) {
            $this->log('Error SOAP Recepcion: ' . $e->getMessage());
            throw new Exception('Error SOAP en recepción: ' . $e->getMessage());
        }

        $parsed = $this->parseRecepcionResponse($response);
        $this->log('Recepcion estado: ' . $parsed['estado']);

        return $parsed;
    }

    /**
     * Consultar autorización por clave de acceso.
     *
     * @param string $claveAcceso
     * @return array
     * @throws Exception
     */
    public function consultarAutorizacion($claveAcceso)
    {
        if ($claveAcceso === '' || !is_string($claveAcceso)) {
            throw new Exception('Clave de acceso vacía');
        }

        $client = $this->buildSoapClient($this->autorizacionWsdl());

        try {
            $response = $client->__soapCall('autorizacionComprobante', array(array('claveAccesoComprobante' => $claveAcceso)));
        } catch (SoapFault $e) {
            $this->log('Error SOAP Autorizacion: ' . $e->getMessage());
            throw new Exception('Error SOAP en autorización: ' . $e->getMessage());
        }

        $parsed = $this->parseAutorizacionResponse($response);
        $this->log('Autorizacion estado: ' . $parsed['estado']);

        return $parsed;
    }

    /**
     * Obtener endpoint de recepción según ambiente.
     *
     * @return string
     */
    private function recepcionWsdl()
    {
        return $this->ambiente() === 1
            ? 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl'
            : 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl';
    }

    /**
     * Obtener endpoint de autorización según ambiente.
     *
     * @return string
     */
    private function autorizacionWsdl()
    {
        return $this->ambiente() === 1
            ? 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl'
            : 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl';
    }

    /**
     * Crear SoapClient con timeout y opciones SSL seguras.
     *
     * @param string $wsdl
     * @return SoapClient
     * @throws Exception
     */
    private function buildSoapClient($wsdl)
    {
        $timeout = isset($this->config['timeout']) ? (int) $this->config['timeout'] : 30;

        $context = stream_context_create(array(
            'http' => array(
                'timeout' => $timeout,
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        try {
            return new SoapClient($wsdl, array(
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => $timeout,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => $context,
            ));
        } catch (Exception $e) {
            $this->log('No se pudo crear SoapClient: ' . $e->getMessage());
            throw new Exception('No se pudo crear SoapClient: ' . $e->getMessage());
        }
    }

    /**
     * Ambiente configurado (1 pruebas / 2 producción).
     *
     * @return int
     */
    private function ambiente()
    {
        return isset($this->config['ambiente']) ? (int) $this->config['ambiente'] : 1;
    }

    /**
     * Parsear respuesta de Recepción.
     *
     * @param mixed $response
     * @return array
     */
    private function parseRecepcionResponse($response)
    {
        $estado = '';
        $mensajes = array();

        if (isset($response->return)) {
            $return = $response->return;
            if (isset($return->estado)) {
                $estado = (string) $return->estado;
            }
            if (isset($return->comprobantes->comprobante)) {
                $comprobantes = is_array($return->comprobantes->comprobante) ? $return->comprobantes->comprobante : array($return->comprobantes->comprobante);
                foreach ($comprobantes as $comp) {
                    if (isset($comp->mensajes->mensaje)) {
                        $msgs = is_array($comp->mensajes->mensaje) ? $comp->mensajes->mensaje : array($comp->mensajes->mensaje);
                        foreach ($msgs as $msg) {
                            $mensajes[] = array(
                                'identificador' => isset($msg->identificador) ? (string) $msg->identificador : '',
                                'mensaje' => isset($msg->mensaje) ? (string) $msg->mensaje : '',
                                'informacionAdicional' => isset($msg->informacionAdicional) ? (string) $msg->informacionAdicional : '',
                                'tipo' => isset($msg->tipo) ? (string) $msg->tipo : '',
                            );
                        }
                    }
                }
            }
        }

        return array(
            'estado' => $estado,
            'mensajes' => $mensajes,
            'raw' => $response,
        );
    }

    /**
     * Parsear respuesta de Autorización.
     *
     * @param mixed $response
     * @return array
     */
    private function parseAutorizacionResponse($response)
    {
        $estado = '';
        $autorizaciones = array();

        if (isset($response->return)) {
            $return = $response->return;
            if (isset($return->autorizaciones->autorizacion)) {
                $items = is_array($return->autorizaciones->autorizacion) ? $return->autorizaciones->autorizacion : array($return->autorizaciones->autorizacion);
                foreach ($items as $item) {
                    $estado = $estado ?: (isset($item->estado) ? (string) $item->estado : '');
                    $autorizaciones[] = array(
                        'estado' => isset($item->estado) ? (string) $item->estado : '',
                        'numeroAutorizacion' => isset($item->numeroAutorizacion) ? (string) $item->numeroAutorizacion : '',
                        'fechaAutorizacion' => isset($item->fechaAutorizacion) ? (string) $item->fechaAutorizacion : '',
                        'comprobante' => isset($item->comprobante) ? (string) $item->comprobante : '',
                        'mensajes' => $this->extractMensajes($item),
                    );
                }
            }
        }

        return array(
            'estado' => $estado,
            'autorizaciones' => $autorizaciones,
            'raw' => $response,
        );
    }

    /**
     * Extraer mensajes de autorización.
     *
     * @param stdClass $item
     * @return array
     */
    private function extractMensajes($item)
    {
        $mensajes = array();
        if (isset($item->mensajes->mensaje)) {
            $msgs = is_array($item->mensajes->mensaje) ? $item->mensajes->mensaje : array($item->mensajes->mensaje);
            foreach ($msgs as $msg) {
                $mensajes[] = array(
                    'identificador' => isset($msg->identificador) ? (string) $msg->identificador : '',
                    'mensaje' => isset($msg->mensaje) ? (string) $msg->mensaje : '',
                    'informacionAdicional' => isset($msg->informacionAdicional) ? (string) $msg->informacionAdicional : '',
                    'tipo' => isset($msg->tipo) ? (string) $msg->tipo : '',
                );
            }
        }
        return $mensajes;
    }

    /**
     * Registrar en archivo de log si se configuró ruta.
     *
     * @param string $message
     * @return void
     */
    private function log($message)
    {
        if (empty($this->config['ruta_logs'])) {
            return;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents(rtrim($this->config['ruta_logs'], '/\\') . '/facturaelectronicaec.log', $line, FILE_APPEND);
    }
}
