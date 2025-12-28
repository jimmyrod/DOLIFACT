# mod_facturaelectronicaec

Módulo de facturación electrónica para Ecuador (SRI) compatible con Dolibarr ≥ 16 y PHP 8.x.

## Estado

- **FASE 1 completada:** estructura estándar del módulo, descriptor y acceso desde el menú de administración.
- **FASE 2 completada:** interfaz de configuración administrativa con parámetros SRI y rutas locales.
- **FASE 3 completada:** clase base para generación XML de Factura conforme SRI.
- **FASE 4 completada:** firma electrónica XAdES-BES con OpenSSL y script de prueba.
- **FASE 5 completada:** cliente SOAP para recepción/autorización del SRI y script de prueba de envío.
- **FASE 6 completada:** integración con validación de facturas Dolibarr (envío automático, persistencia de estado y almacenamiento de XML).
- **FASE 8 completada:** logs detallados, reintentos manuales y contingencia offline.
- **FASE 7 completada:** generación de PDF autorizado con clave de acceso y datos de autorización.

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

## Integración con facturas (Fase 6)

- Al **validar** una factura (acción `BILL_VALIDATE`), el trigger `InterfaceFacturaElectronicaEC`:
  1. Genera el XML SRI con `FacturaElectronicaEC`.
  2. Firma con `FirmaXadesEC` usando el certificado configurado.
  3. Envía a Recepción SRI y, si aplica, consulta Autorización.
  4. Guarda los XML firmados/autorizados en la ruta configurada (o `dol_data_root/mod_facturaelectronicaec/xml`).
  5. Registra estado, número y fecha de autorización y respuesta completa en la tabla `llx_facturaelectronicaec_doc`.

- Estados registrados: `RECIBIDA` (luego normalizado a `EN_PROCESO`), `AUTORIZADO`, `DEVUELTO`, u otro que devuelva el SRI.
- Si el estado final no es `AUTORIZADO`, se muestra advertencia en la interfaz; si es autorizado, se muestra mensaje con el número de autorización.

> **Nota:** el instalador del módulo crea la tabla `llx_facturaelectronicaec_doc`. Si ya tenía el módulo activo antes de esta fase, desactive/active para ejecutar la creación de tablas.

## PDF autorizado (Fase 7)

- Cuando el SRI devuelve `AUTORIZADO`, el trigger genera un PDF resumido (`<ref>-sri.pdf`) en el directorio de documentos de la factura (`$conf->facture->dir_output/<ref>/`).
- El PDF incluye la clave de acceso, número y fecha de autorización y referencia al XML autorizado.
- El camino del PDF queda registrado en la respuesta almacenada en `llx_facturaelectronicaec_doc.respuesta_sri` para trazabilidad.

## Logs y contingencia (Fase 8)

- El flujo de validación de factura escribe hitos y errores en `FACTURAELECTRONICAEC_RUTA_LOGS/facturaelectronicaec.log` (por defecto `$dol_data_root/mod_facturaelectronicaec/logs`).
- Los mensajes SRI de recepción/autorización se adjuntan al campo `respuesta_sri` de `llx_facturaelectronicaec_doc` para depuración.
- En caso de error SOAP o indisponibilidad SRI, el estado guardado será `PENDIENTE_OFFLINE` manteniendo el XML firmado para reenvío manual.
- Reintento manual: usar el script CLI con el ID de factura Dolibarr:

```bash
php htdocs/custom/mod_facturaelectronicaec/scripts/reintentar_envio_sri.php <factura_id>
```

- El script reutiliza el XML firmado almacenado, intenta recepción y autorización, y actualiza el tracking (incluyendo PDF autorizado si aplica). Los resultados se muestran por consola y se guardan en el log.

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
