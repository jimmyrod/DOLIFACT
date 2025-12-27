CREATE TABLE IF NOT EXISTS llx_facturaelectronicaec_doc (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_facture INT NOT NULL,
    clave_acceso VARCHAR(64) NOT NULL,
    estado VARCHAR(32) NOT NULL,
    numero_autorizacion VARCHAR(64) DEFAULT NULL,
    fecha_autorizacion VARCHAR(64) DEFAULT NULL,
    ruta_xml_firmado VARCHAR(255) DEFAULT NULL,
    ruta_xml_autorizado VARCHAR(255) DEFAULT NULL,
    respuesta_sri MEDIUMTEXT,
    datec DATETIME NULL,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_facturaelectronicaec_doc_facture (fk_facture),
    KEY idx_facturaelectronicaec_clave (clave_acceso)
) ENGINE=innodb;
