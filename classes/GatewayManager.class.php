<?php
/**
 * Payment gateway manager class.
 * Handles admin lists, uploading and installing gateways.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.5.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\FileSystem;
use glFusion\Database\Database;
use Shop\Config;
use Shop\Cache;
use Shop\Log;
use Shop\Collections\GatewayCollection;


/**
 * Payment Gateway Manager.
 * Manages installing and removing gateways.
 * @package shop
 */
class GatewayManager
{
    /** Array to hold cached gateways.
     * @var array */
    private static $gateways = array();

    /** List of bundled gateways.
     * @var array */
    public static $_bundledGateways = array(
        'paypal', 'ppcheckout', 'test', '_coupon',
        'check', 'terms', '_internal', 'free',
    );

    /** Collection of error messages.
     * @var array */
    private $_errors = array();


    /**
     * Get all gateways into a static array.
     *
     * @param   boolean $enabled    True to get only enabled gateways
     * @return  array       Array of gateways, enabled or all
     */
    public static function getAll(bool $enabled = false) : array
    {
        global $_TABLES;

        $gateways = array();
        $key = $enabled ? 1 : 0;
        $gateways[$key] = array();
        $cache_key = 'gateways_' . $key;
        $tmp = Cache::get($cache_key);
        if ($tmp === NULL) {
            $tmp = array();
            // Load the gateways
            $db = Database::getInstance();
            $sql = "SELECT * FROM {$_TABLES['shop.gateways']}";
            // If not loading all gateways, get just then enabled ones
            if ($enabled) $sql .= ' WHERE enabled=1';
            $sql .= ' ORDER BY orderby';
            try {
                $data = $db->conn->executeQuery($sql)->fetchAllAssociative();
            } catch (\Exception $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $data = false;
            }
            if (is_array($data)) {
                foreach ($data as $A) {
                    $tmp[] = $A;
                }
            }
            Cache::set($cache_key, $tmp, 'shop.gateways');
        }
        // For each available gateway, load its class file and add it
        // to the static array. Check that a valid object is
        // returned from getInstance()
        foreach ($tmp as $A) {
            $cls = __NAMESPACE__ . '\\Gateways\\' . $A['id'] . '\\Gateway';
            if (class_exists($cls)) {
                $gw = new $cls($A);
            } else {
                $gw = NULL;
            }
            if (is_object($gw)) {
                $gateways[$key][$A['id']] = $gw;
            } else {
                continue;       // Gateway enabled but not installed
            }
        }
        return $gateways[$key];
    }


    /**
     * Get an array of uninstalled gateways for the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function getUninstalled(array &$data_arr) : void
    {
        global $LANG32;

        $installed = self::getAll(false);
        $base_path = __DIR__ . '/Gateways';
        $dirs = scandir($base_path);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                if (
                    $dir !== '.' && $dir !== '..' &&
                    $dir[0] != '_' &&       // skip internal utility gateways
                    is_dir("{$base_path}/{$dir}") &&
                    is_file("{$base_path}/{$dir}/Gateway.class.php") &&
                    !array_key_exists($dir, $installed)
                ) {
                    $clsfile = 'Shop\\Gateways\\' . $dir . '\\Gateway';
                    $gw = new $clsfile;
                    if (is_object($gw)) {
                        $data_arr[] = array(
                            'id'    => $gw->getName(),
                            'description' => $gw->getDscp(),
                            'enabled' => 'na',
                            'orderby' => 999,
                        );
                    }
                }
            }
        }
    }


    /**
     * Get all the installed gateways for the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function getInstalled(array &$data_arr) : void
    {
        global $_TABLES;

        $sql = "SELECT *, g.grp_name
            FROM {$_TABLES['shop.gateways']} gw
            LEFT JOIN {$_TABLES['groups']} g
                ON g.grp_id = gw.grp_access
            ORDER BY orderby ASC";

        $db = Database::getInstance();
        try {
            $data = $db->conn->executeQuery($sql)->fetchAllAssociative();
        } catch (\Exception $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $msg);
            $data = false;
        }
        if (is_array($data)) {
            foreach ($data as $A) {
                $gw = Gateway::create($A['id']);
                if ($gw) {
                    $data_arr[] = array(
                        'id'    => $A['id'],
                        'orderby' => $A['orderby'],
                        'enabled' => $A['enabled'],
                        'description' => $A['description'],
                        'grp_name' => $A['grp_name'],
                        'version' => $A['version'],
                        'code_version' => $gw->getCodeVersion(),
                        'shop_version' => $gw->getShopVersion(),
                    );
                }
            }
        }
    }


    /**
     * Payment Gateway Admin View.
     *
     * @return  string      HTML for the gateway listing
     */
    public static function adminList() : string
    {
        global $_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN,
            $LANG32;

        $data_arr = array();
        self::getInstalled($data_arr);
        self::getUninstalled($data_arr);
        // Future - check versions of pluggable gateways
        self::_checkAvailableVersions($data_arr);

        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['orderby'],
                'field' => 'orderby',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'id',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_SHOP['description'],
                'field' => 'description',
                'sort'  => true,
            ),
            array (
                'text'  => $LANG32[84],
                'field' => 'bundled',
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['grp_access'],
                'field' => 'grp_name',
                'sort'  => false,
            ),
            array(
                'text'  => $LANG_SHOP['version'],
                'field' => 'version',
            ),
            array(
                'text'  => $LANG_SHOP['control'],
                'field' => 'enabled',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => 'false',
                'align' => 'center',
            ),
        );

        $extra = array(
            'gw_count' => DB_count($_TABLES['shop.gateways']),
        );

        $defsort_arr = array(
            'field' => 'orderby',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/gateways.php?gwadmin',
        );
        $T = new Template('admin');
        $T->set_file('form', 'gw_adminlist_form.thtml');
        $T->set_var('lang_select_file', $LANG_SHOP['select_file']);
        $T->set_var('lang_upload', $LANG_SHOP['upload']);
        $T->parse('output', 'form');
        $display .= $T->finish($T->get_var('output'));
        $display .= ADMIN_listArray(
            Config::PI_NAME . '_gwlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $data_arr, $defsort_arr,
            '', $extra, '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the options admin list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @param   array   $extra      Extra information passed in verbatim
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField(string $fieldname, $fieldvalue, array $A, array $icon_arr, array $extra) : string
    {
        global $_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            if ($A['enabled'] !== 'na') {
                $retval .= FieldList::edit(array(
                    'url' => SHOP_ADMIN_URL . "/gateways.php?gwedit&amp;gw_id={$A['id']}",
                ) );
            }
            break;

        case 'enabled':
            if ($fieldvalue == 'na') {
                return FieldList::add(array(
                    'url' => SHOP_ADMIN_URL. '/gateways.php?gwinstall&gwname=' . urlencode($A['id']),
                    array(
                        'title' => $LANG_SHOP['ck_to_install'],
                    )
                ) );
            } elseif ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
                $tip = $LANG_SHOP['ck_to_disable'];
            } else {
                $switch = '';
                $enabled = 0;
                $tip = $LANG_SHOP['ck_to_enable'];
            }
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['id']}",
                'checked' => $fieldvalue == 1,
                'title' => $tip,
                'onclick' => "SHOP_toggle(this,'{$A['id']}','{$fieldname}','gateway');",
            ) );
            break;

        case 'version':
            // Show the upgrade link if needed. Only display this for
            // installed gateways.
            if (isset($A['version'])) {
                $retval = $fieldvalue;
                if (!COM_checkVersion($fieldvalue, $A['code_version'])) {
                    $retval .= ' ' . FieldList::update(array(
                        'url' => Config::get('admin_url') . '/gateways.php?gwupgrade=' . $A['id'],
                    ) );
                    $retval .= $A['code_version'];
                } elseif (!COM_checkVersion($A['version'], $A['available'])) {
                    $retval .= ' ' . FieldList::buttonLink(array(
                        'text' => $A['available'],
                        'url' => $A['upgrade_url'],
                        'size' => 'mini',
                        'style' => 'success',
                        'attr' => array(
                            'target' => '_blank',
                        )
                    ) );
                }
            }
            break;

        case 'orderby':
            $fieldvalue = (int)$fieldvalue;
            if ($fieldvalue == 999) {
                return '';
            } elseif ($fieldvalue > 10) {
                $retval = FieldList::up(array(
                    'url' => SHOP_ADMIN_URL . '/gateways.php?gwmove=up&id=' . $A['id'],
                ) );
            } else {
                $retval = FieldList::space();
            }
            if ($fieldvalue < $extra['gw_count'] * 10) {
                $retval .= FieldList::down(array(
                    'url' => SHOP_ADMIN_URL . '/gateways.php?gwmove=down&id=' . $A['id'],
                )) ;
            } else {
                $retval .= FieldList::space();
            }
            break;

        case 'bundled':
            if (in_array($A['id'], self::$_bundledGateways)) {
                $retval .= FieldList::checkmark(array(
                    'active' => $A['enabled'] != 'na',
                ) );
            }
            break;

        case 'delete':
            if ($A['enabled'] != 'na' && $A['id'][0] != '_') {
                $retval = FieldList::delete(array(
                    'delete_url' => SHOP_ADMIN_URL. '/gateways.php?gwdelete&amp;id=' . $A['id'],
                    'attr' => array(
                        'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                        'title' => $LANG_SHOP['del_item'],
                        'class' => 'tooltip',
                    ),
                ) );
            }
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }


    /**
     * Upload and install the files for a gateway package.
     *
     * @return  boolean     True on success, False on error
     */
    public function upload() : bool
    {
        global $_CONF, $LANG_SHOP;

        $retval = '';

        if (
            count($_FILES) > 0 &&
            $_FILES['gw_file']['error'] != UPLOAD_ERR_NO_FILE
        ) {
            $upload = new UploadDownload();
            $upload->setMaxFileUploads(1);
            $upload->setMaxFileSize(25165824);
            $upload->setAllowedMimeTypes(array (
                'application/x-gzip'=> array('.gz', '.gzip,tgz'),
                'application/gzip'=> array('.gz', '.gzip,tgz'),
                'application/zip'   => array('.zip'),
                'application/octet-stream' => array(
                    '.gz' ,'.gzip', '.tgz', '.zip', '.tar', '.tar.gz',
                ),
                'application/x-tar' => array('.tar', '.tar.gz', '.gz'),
                'application/x-gzip-compressed' => array('.tar.gz', '.tgz', '.gz'),
            ) );
            $upload->setFieldName('gw_file');
            if (!$upload->setPath($_CONF['path_data'] . 'temp')) {
                Log::system(Log::ERROR, "Error setting temp path: " . $upload->printErrors(false));
            }

            $filename = $_FILES['gw_file']['name'];
            $upload->setFileNames($filename);
            $upload->uploadFiles();

            if ($upload->areErrors()) {
                Log::system(Log::ERROR, "Errors during upload: " . $upload->printErrors());
                $this->addError($LANG_SHOP['err_occurred']);
                return false;
            }
            $Finalfilename = $_CONF['path_data'] . 'temp/' . $filename;
        } else {
            Log::system(Log::ERROR, "No file found to upload");
            $this->addError("No file found to upload");
            return false;
        }

        // decompress into temp directory
        if (function_exists('set_time_limit')) {
            @set_time_limit( 60 );
        }
        $tmp = FileSystem::mkTmpDir();
        if ($tmp === false) {
            Log::system(Log::ERROR, 'Failed to create temp directory');
            $this->addError('Failed to create temp directory');
            return false;
        }
        $tmp_path = $_CONF['path_data'] . $tmp;
        if (!COM_decompress($Finalfilename, $tmp_path)) {
            Log::system(Log::ERROR, "Failed to decompress $Finalfilename into $tmp_path");
            $this->addError("Failed to decompress $Finalfilename into $tmp_path");
            FileSystem::deleteDir($tmp_path);
            return false;
        }
        @unlink($Finalfilename);

        if (!$dh = @opendir($tmp_path)) {
            Log::system(Log::ERROR, "Failed to open $tmp_path");
            $this->addError("Failed to open $tmp_path");
            return false;
        }
        $upl_path = $tmp_path;
        while (false !== ($file = readdir($dh))) {
            if ($file == '..' || $file == '.') {
                continue;
            }
            if (@is_dir($tmp_path . '/' . $file)) {
                $upl_path = $tmp_path . '/' . $file;
                break;
            }
        }
        closedir($dh);

        if (empty($upl_path)) {
            Log::system(Log::ERROR, "Could not find upload path under $tmp_path");
            $this->addError("Could not find upload path under $tmp_path");
            return false;
        }

        // Copy the extracted upload into the Gateways class directory.
        $fs = new FileSystem;
        $gw_name = '';
        if (is_file($upl_path . '/gateway.json')) {
            $json = @file_get_contents($upl_path . '/gateway.json');
            if ($json) {
                $json = @json_decode($json, true);
                if ($json) {
                    if (!isset($json['shop_version'])) {
                        $json['shop_version'] = '0.0.0';
                    }
                    if (
                        !COM_checkVersion($json['shop_version'], Config::get('pi_version'))
                    ) {
                        $this->addError(
                            sprintf($LANG_SHOP['err_gw_version'], Config::get('pi_version'), $json['shop_version'])
                        );
                        return false;
                    }
                    $gw_name = $json['name'];
                    $gw_path = Config::get('path') . 'classes/Gateways/' . $gw_name;
                    $status = $fs->dirCopy($upl_path, $gw_path);
                    if ($status) {
                        // Got the files copied, delete the uploaded files.
                        FileSystem::deleteDir($tmp_path);
                        if (@is_dir($gw_path . '/public_html')) {
                            // Copy any public_html files, like custom webhook handles
                            $fs->dirCopy($gw_path . '/public_html', $_CONF['path_html'] . Config::PI_NAME);
                            FileSystem::deleteDir($gw_path . '/public_html');
                        }
                    }
                }
            }
        }

        // If there are any error messages, log them and return false.
        // Otherwise return true.
        if (empty($fs->getErrors())) {
            if (!empty($gw_name)) {
                $gw = Gateway::getInstance($gw_name);
                $gw->doUpgrade();
            }
            return true;
        } else {
            $errors = $fs->getErrors();
            if (!empty($errors)) {
                foreach ($fs->getErrors() as $msg) {
                    Log::system(Log::ERROR, __METHOD__ . ': ' . $msg);
                }
                $this->addError($LANG_SHOP['err_occurred']);
            }
            // Otherwise the error message has already been added. 
            return false;
        }
    }


    /**
     * Upgrade all bundled gateways. Called during plugin update.
     *
     * @param   string  $to     New version
     */
    public static function upgradeBundled(string $to) : void
    {
        $Coll = new GatewayCollection;
        $gateways = $Coll->withBundled()->getObjects();
        foreach ($gateways as $gw) {
            $gw->doUpgrade();
        }
    }


    /**
     * Check available versions for all pluggable gateways and add to data_arr.
     * Bundled gateways always use '0.0.0' as the version to avoid indicating
     * that an update is available.
     * Versions are added to the $data_arr array to be used in the admin list.
     *
     * @param   array   $data_arr   Reference to data array
     */
    private static function _checkAvailableVersions(array &$data_arr) : void
    {
        global $_VARS;

        // Only check in sync with other update checks.
        $versions = Cache::get('shop_gw_versions');
        if ($versions === NULL) {
            $versions = array();
            foreach ($data_arr as $idx=>$gw) {
                $key = $gw['id'];
                $versions[$key] = self::_checkAvailableVersion($key);
            }
            Cache::set('shop_gw_versions', $versions, 'shop.gateways');
        }

        foreach ($data_arr as $key=>$gw) {
            if (array_key_exists($gw['id'], $versions)) {
                $data_arr[$key]['available'] = $versions[$gw['id']]['available'];
                $data_arr[$key]['upgrade_url'] = $versions[$gw['id']]['upgrade_url'];
            } else {
                // Bundled or no version available
                $data_arr[$key]['available'] = $gw['version'];
                $data_arr[$key]['upgrade_url'] = '';
            }
        }
    }


    /**
     * Get the latest release and download URL for a single gateway.
     * Queries Github or Gitlab based on gateway.json.
     *
     * @param   string  $gwname     Gateway name
     * @return  array       Array of (available, download_link)
     */
    private static function _checkAvailableVersion(string $gwname) : array
    {
        $default = array(
            'available' => '0.0.0',
            'upgrade_url' => '',
        );

        $filename = __DIR__ . '/Gateways/' . $gwname . '/gateway.json';
        if (is_file($filename)) {
            $json = @file_get_contents($filename);
            $json = @json_decode($json, true);
            if (!$json || !isset($json['repo']['type'])) {
                return $default;
            }
        } else {
            return $default;
        }

        switch ($json['repo']['type']) {
        case 'gitlab':
            $releases_url = 'https://gitlab.com/api/v4/projects/' . $json['repo']['project_id'] . '/releases/';
            break;
        case 'github':
            $releases_url = 'https://api.github.com/repos/glshop-gateways/' . $json['repo']['project_id'] . '/releases/latest';
            break;
        default:
            $releases_url = '';
            break;
        }
        if (empty($releases_url)) {
            return $default;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $releases_url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Accept: application/vnd.github.v3+json"));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'glFusion Shop');
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result) {
            $data = @json_decode($result, true);
        }
        if (!$data) {
            return $default;
        }

        switch ($json['repo']['type']) {
        case 'gitlab':
            $data = $data[0];
            $releases_link = $data['_links']['self'];
            break;
        case 'github':
            $releases_link = $data['html_url'];
            break;
        default:
            $releases_link = '';
            break;
        }
        if (empty($releases_link)) {
            return $default;
        }

        $latest = $data['tag_name'];
        if ($latest[0] == 'v') {
            $latest = substr($latest, 1);
        }
        return array(
            'available' => $latest,
            'upgrade_url' => $releases_link,
        );
    }


    public function getErrors(bool $format)
    {
        if ($format && !empty($this->_errors)) {
            return '<ul><li>' . implode('</li></li>', $this->_errors) . '</li></ul>';
        } else {
            return $this->_errors;
        }
    }


    private function addError(string $msg) : void
    {
        $this->_errors[] = $msg;
    }
            
}
