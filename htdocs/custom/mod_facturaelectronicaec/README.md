# mod_facturaelectronicaec

Módulo de facturación electrónica para Ecuador (SRI) compatible con Dolibarr ≥ 16 y PHP 8.x.

## Estado

- **FASE 1 completada:** estructura estándar del módulo, descriptor y acceso desde el menú de administración.
- **FASE 2 completada:** interfaz de configuración administrativa con parámetros SRI y rutas locales.
- **FASE 3 completada:** clase base para generación XML de Factura conforme SRI.
- **FASE 4 completada:** firma electrónica XAdES-BES con OpenSSL y script de prueba.
- **FASE 5 completada:** cliente SOAP para recepción/autorización del SRI y script de prueba de envío.

## Estructura

```
custom/mod_facturaelectronicaec/
├── core/modules/
├── class/
├── admin/
├── scripts/
├── logs/
├── xml/
├── pdf/
├── sql/
├── langs/
├── README.md
├── modFacturaElectronicaEC.class.php
```

## Instalación

1. Copie el directorio `mod_facturaelectronicaec` dentro de `htdocs/custom/`.
2. Ingrese a Dolibarr como administrador.
3. Active el módulo **Factura Electronica Ecuador** desde la lista de módulos.
4. Abra el menú **FacturaElectronicaEC** para acceder a la configuración.

## Próximas fases

- Configuración administrativa.
- Generación XML (Factura).
- Firma XAdES-BES.
- Envío y autorización SRI.
- Integración con facturas Dolibarr.
- PDF autorizado.
- Logs y contingencia.

## Pruebas locales (firma)

Usar el XML de ejemplo del SRI y un certificado válido:

```bash
FACTURAEC_CERT_PATH=/ruta/certificado.p12 \
FACTURAEC_CERT_PASSWORD=clave \
php htdocs/custom/mod_facturaelectronicaec/scripts/test_firma_xades.php
```

El XML firmado se guarda en:
`htdocs/custom/mod_facturaelectronicaec/xml/ejemplo_factura_sri_firmado.xml`.

## Pruebas locales (envío y autorización SRI)

Con un XML firmado válido, ejecutar:

```bash
FACTURAEC_AMBIENTE=1 \
FACTURAEC_TIMEOUT=60 \
php htdocs/custom/mod_facturaelectronicaec/scripts/test_envio_sri.php /ruta/al/xml_firmado.xml
```

El script realiza la llamada a **Recepción** y, si el estado es `RECIBIDA`, consulta **Autorización**.
