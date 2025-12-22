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

$help_url = '';
llxHeader('', $langs->trans('FacturaElectronicaEC'), $help_url);

print load_fiche_titre($langs->trans('FacturaElectronicaEC'));
print '<div class="opacitymedium">' . $langs->trans('FacturaElectronicaECSetupDesc') . '</div>';

llxFooter();
$db->close();
