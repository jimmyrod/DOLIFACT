<?php
// Dolibarr module descriptor for Factura Electronica Ecuador.

// Load Dolibarr environment if not already in context.
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = @include __DIR__ . '/../../../main.inc.php';
    if (!$res) {
        die('Include of main.inc.php failed');
    }
}

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Module descriptor for mod_facturaelectronicaec.
 */
class modFacturaElectronicaEC extends DolibarrModules
{
    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs;

        $this->db = $db;
        $this->numero = 540000; // Unique module number
        $this->rights_class = 'facturaelectronicaec';
        $this->family = 'financial';
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Factura Electronica Ecuador (SRI)';
        $this->version = 'development';
        $this->picto = 'bill';

        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 0;

        $this->dirs = array(
            '/mod_facturaelectronicaec/temp',
            '/mod_facturaelectronicaec/logs',
            '/mod_facturaelectronicaec/xml',
            '/mod_facturaelectronicaec/pdf',
        );

        $this->config_page_url = array('facturaelectronicaec_setup.php@mod_facturaelectronicaec');

        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('facturaelectronicaec@mod_facturaelectronicaec');

        $this->const = array();
        $this->boxes = array();
        $this->crons = array();

        $this->rights = array();
        $this->rights[0][0] = 540001;
        $this->rights[0][1] = 'Read module';
        $this->rights[0][4] = 'read';

        $this->menu = array();
        $this->menu[0] = array(
            'fk_menu' => 'fk_mainmenu=home',
            'type' => 'top',
            'titre' => 'FacturaElectronicaEC',
            'mainmenu' => 'facturaelectronicaec',
            'leftmenu' => 'facturaelectronicaec',
            'url' => '/custom/mod_facturaelectronicaec/admin/facturaelectronicaec_setup.php',
            'langs' => 'facturaelectronicaec@mod_facturaelectronicaec',
            'position' => 100,
            'enabled' => '$conf->facturaelectronicaec->enabled',
            'perms' => '$user->rights->facturaelectronicaec->read',
            'target' => '',
            'user' => 2,
        );

        $this->menu[1] = array(
            'fk_menu' => 'fk_mainmenu=facturaelectronicaec',
            'type' => 'left',
            'titre' => 'Configuracion',
            'mainmenu' => 'facturaelectronicaec',
            'leftmenu' => 'facturaelectronicaec_setup',
            'url' => '/custom/mod_facturaelectronicaec/admin/facturaelectronicaec_setup.php',
            'langs' => 'facturaelectronicaec@mod_facturaelectronicaec',
            'position' => 100,
            'enabled' => '$conf->facturaelectronicaec->enabled',
            'perms' => '$user->rights->facturaelectronicaec->read',
            'target' => '',
            'user' => 2,
        );
    }

    /**
     * Initialize module.
     *
     * @param string $options Options
     * @return int
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/mod_facturaelectronicaec/sql/');
        if ($result < 0) {
            return -1;
        }
        return $this->_init($options);
    }

    /**
     * Remove module.
     *
     * @param string $options Options
     * @return int
     */
    public function remove($options = '')
    {
        return $this->_remove($options);
    }
}
