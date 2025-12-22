<?php
// Admin setup page for Factura Electronica Ecuador module.

require_once __DIR__ . '/../../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

$langs->load('admin');
$langs->load('facturaelectronicaec@mod_facturaelectronicaec');

$action = GETPOST('action', 'aZ09');

if (!$user->admin) {
    accessforbidden();
}

if ($action === 'save') {
    $db->begin();

    $ambiente = GETPOST('facturaelectronicaec_ambiente', 'int');
    $ruc = trim(GETPOST('facturaelectronicaec_ruc', 'alphanohtml'));
    $razonSocial = trim(GETPOST('facturaelectronicaec_razon_social', 'alphanohtml'));
    $nombreComercial = trim(GETPOST('facturaelectronicaec_nombre_comercial', 'alphanohtml'));
    $establecimiento = trim(GETPOST('facturaelectronicaec_establecimiento', 'alphanohtml'));
    $puntoEmision = trim(GETPOST('facturaelectronicaec_punto_emision', 'alphanohtml'));
    $direccionMatriz = trim(GETPOST('facturaelectronicaec_direccion_matriz', 'alphanohtml'));
    $certPath = trim(GETPOST('facturaelectronicaec_cert_path', 'alphanohtml'));
    $certPassword = trim(GETPOST('facturaelectronicaec_cert_password', 'none'));
    $timeoutSRI = GETPOST('facturaelectronicaec_timeout', 'int');
    $rutaXml = trim(GETPOST('facturaelectronicaec_ruta_xml', 'alphanohtml'));
    $rutaLogs = trim(GETPOST('facturaelectronicaec_ruta_logs', 'alphanohtml'));

    $result = dol_set_const($db, 'FACTURAELECTRONICAEC_AMBIENTE', $ambiente, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_RUC', $ruc, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_RAZON_SOCIAL', $razonSocial, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_NOMBRE_COMERCIAL', $nombreComercial, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_ESTABLECIMIENTO', $establecimiento, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_PUNTO_EMISION', $puntoEmision, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_DIRECCION_MATRIZ', $direccionMatriz, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_CERT_PATH', $certPath, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_CERT_PASSWORD', $certPassword, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_TIMEOUT', $timeoutSRI, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_RUTA_XML', $rutaXml, 'chaine', 0, '', $conf->entity);
    $result |= dol_set_const($db, 'FACTURAELECTRONICAEC_RUTA_LOGS', $rutaLogs, 'chaine', 0, '', $conf->entity);

    if ($result > 0) {
        $db->commit();
        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans('Error'), null, 'errors');
    }
}

$help_url = '';
llxHeader('', $langs->trans('FacturaElectronicaEC'), $help_url);

print load_fiche_titre($langs->trans('FacturaElectronicaEC'));
print '<div class="opacitymedium">' . $langs->trans('FacturaElectronicaECSetupDesc') . '</div>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="save">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

$ambienteValue = getDolGlobalInt('FACTURAELECTRONICAEC_AMBIENTE', 1);
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_Ambiente') . '</td>';
print '<td>';
print '<select class="flat" name="facturaelectronicaec_ambiente">';
print '<option value="1"' . ($ambienteValue === 1 ? ' selected' : '') . '>' . $langs->trans('FacturaElectronicaEC_AmbientePruebas') . '</option>';
print '<option value="2"' . ($ambienteValue === 2 ? ' selected' : '') . '>' . $langs->trans('FacturaElectronicaEC_AmbienteProduccion') . '</option>';
print '</select>';
print '</td>';
print '</tr>';

$rucValue = getDolGlobalString('FACTURAELECTRONICAEC_RUC');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_Ruc') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_ruc" value="' . dol_escape_htmltag($rucValue) . '"></td>';
print '</tr>';

$razonSocialValue = getDolGlobalString('FACTURAELECTRONICAEC_RAZON_SOCIAL');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_RazonSocial') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_razon_social" value="' . dol_escape_htmltag($razonSocialValue) . '"></td>';
print '</tr>';

$nombreComercialValue = getDolGlobalString('FACTURAELECTRONICAEC_NOMBRE_COMERCIAL');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_NombreComercial') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_nombre_comercial" value="' . dol_escape_htmltag($nombreComercialValue) . '"></td>';
print '</tr>';

$establecimientoValue = getDolGlobalString('FACTURAELECTRONICAEC_ESTABLECIMIENTO');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_Establecimiento') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_establecimiento" value="' . dol_escape_htmltag($establecimientoValue) . '"></td>';
print '</tr>';

$puntoEmisionValue = getDolGlobalString('FACTURAELECTRONICAEC_PUNTO_EMISION');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_PuntoEmision') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_punto_emision" value="' . dol_escape_htmltag($puntoEmisionValue) . '"></td>';
print '</tr>';

$direccionMatrizValue = getDolGlobalString('FACTURAELECTRONICAEC_DIRECCION_MATRIZ');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_DireccionMatriz') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_direccion_matriz" value="' . dol_escape_htmltag($direccionMatrizValue) . '"></td>';
print '</tr>';

$certPathValue = getDolGlobalString('FACTURAELECTRONICAEC_CERT_PATH');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_CertPath') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_cert_path" value="' . dol_escape_htmltag($certPathValue) . '"></td>';
print '</tr>';

$certPasswordValue = getDolGlobalString('FACTURAELECTRONICAEC_CERT_PASSWORD');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_CertPassword') . '</td>';
print '<td><input class="flat" type="password" name="facturaelectronicaec_cert_password" value="' . dol_escape_htmltag($certPasswordValue) . '"></td>';
print '</tr>';

$timeoutValue = getDolGlobalInt('FACTURAELECTRONICAEC_TIMEOUT', 30);
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_Timeout') . '</td>';
print '<td><input class="flat" type="number" min="1" name="facturaelectronicaec_timeout" value="' . dol_escape_htmltag((string) $timeoutValue) . '"></td>';
print '</tr>';

$rutaXmlValue = getDolGlobalString('FACTURAELECTRONICAEC_RUTA_XML');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_RutaXml') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_ruta_xml" value="' . dol_escape_htmltag($rutaXmlValue) . '"></td>';
print '</tr>';

$rutaLogsValue = getDolGlobalString('FACTURAELECTRONICAEC_RUTA_LOGS');
print '<tr class="oddeven">';
print '<td>' . $langs->trans('FacturaElectronicaEC_RutaLogs') . '</td>';
print '<td><input class="flat" type="text" name="facturaelectronicaec_ruta_logs" value="' . dol_escape_htmltag($rutaLogsValue) . '"></td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="center">';
print '<input class="button button-save" type="submit" value="' . $langs->trans('Save') . '">';
print '</div>';

print '</form>';

llxFooter();
$db->close();
