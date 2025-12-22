# mod_facturaelectronicaec

Módulo de facturación electrónica para Ecuador (SRI) compatible con Dolibarr ≥ 16 y PHP 8.x.

## Estado

- **FASE 1 completada:** estructura estándar del módulo, descriptor y acceso desde el menú de administración.

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
