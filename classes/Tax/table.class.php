<?php
/**
 * Use the static databsae table to retrieve sales tax rates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.2.3
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Tax;
use Shop\Template;


/**
 * Get the sales tax rate from the DB tables.
 * @package shop
 */
class table extends \Shop\Tax
{
    /**
     * Get the tax data for the current address.
     * Returns the "No Nexus" values if an entry is not found in the DB.
     *
     * @return  array   Decoded array of data from the JSON reply
     */
    protected function _getData()
    {
        global $_SHOP_CONF, $LANG_SHOP, $_TABLES;

        // Default data returned if there is no nexus, or a rate entry
        // is not found.
        $data = $this->default_rates;

        if ($this->hasNexus()) {
            $country = DB_escapeString($this->Address->getCountry());
            $zipcode = DB_escapeString($this->Address->getZip5());
            $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}
                WHERE country = '$country'
                AND (
                    zip_from = '$zipcode' OR
                    '$zipcode' BETWEEN zip_from AND zip_to
                ) ORDER BY zip_from DESC, zip_to ASC
                LIMIT 1";
            //echo $sql;die;
            $res = DB_query($sql, 1);
            if ($res) {
                $A = DB_fetchArray($res, false);
                if ($A) {           // Have to have found a record
                    $data = array(
                        'totalRate' => SHOP_getVar($A, 'combined_rate', 'float'),
                        'rates' => array(
                            array(
                                'rate'  => SHOP_getVar($A, 'state_rate', 'float'),
                                'name'  => $A['state'] . ' ' . $LANG_SHOP['state_rate'],
                                'type'  => 'State',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'county_rate', 'float'),
                                'name'  => $A['state'] . ' ' . $LANG_SHOP['county_rate'],
                                'type'  => 'County',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'city_rate', 'float'),
                                'name'  => $A['region'] . ' ' . $LANG_SHOP['city_rate'],
                                'type'  => 'City',
                            ),
                            array(
                                'rate'  => SHOP_getVar($A, 'special_rate', 'float'),
                                'name'  => $A['region'] . ' ' . $LANG_SHOP['special_rate'],
                                'type'  => 'Special',
                            ),
                        ),
                    );
                }
            }
        }
        return $data;
    }


    /**
     * Display the admin list of tax table rates and allow uploading new rates.
     *
     * @return  string      HTML for admin list
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}";

        $header_arr = array(
            array(
                'text'  => 'Code',
                'field' => 'code',
                'sort'  => true,
            ),
            array(
                'text'  => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['country'],
                'field' => 'country',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['state'],
                'field' => 'state',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['region'],
                'field' => 'region',
                'sort'  => true,
                'align' => 'left',
            ),
            array(
                'text'  => $LANG_SHOP['zip_from'],
                'field' => 'zip_from',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['zip_to'],
                'field' => 'zip_to',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['combined_rate'],
                'field' => 'combined_rate',
                'sort'  => false,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['state_rate'],
                'field' => 'state_rate',
                'sort'  => false,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['county_rate'],
                'field' => 'county_rate',
                'sort'  => false,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['city_rate'],
                'field' => 'city_rate',
                'sort'  => false,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_SHOP['special_rate'],
                'field' => 'special_rate',
                'sort'  => false,
                'align' => 'right',
            ),
            array(
                'text'  => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort'  => false,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'code',
            'direction' => 'asc',
        );

        $display .= COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );
        $display .= COM_createLink($LANG_SHOP['new_rate'],
            SHOP_ADMIN_URL . '/index.php?edittaxrate=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );

        $query_arr = array(
            'table' => 'shop.tax_rates',
            'sql'   => $sql,
            'query_fields' => array('code', 'country', 'state', 'region'),
            'default_filter' => 'WHERE 1 = 1 ',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?taxrates',
        );
        $options = array(
            'chkdelete' => 'true',
            'chkfield' => 'code',
            'chkname' => 'code',
            'chkactions' => '<button type="submit" name="deltaxrate" value="x" ' .
            'class="uk-button uk-button-mini uk-button-danger tooltip" ' .
            'title="' . $LANG_SHOP['delete'] . '" ' .
            '><i name="deltax" class="uk-icon uk-icon-remove"></i>' .
            '</button>',
        );

        $filter = '';
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_salestax',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the sales tax table list.
     *
     * @param   string  $fieldname  Name of field (from the array, not the db)
     * @param   mixed   $fieldvalue Value of the field
     * @param   array   $A          Array of all fields from the database
     * @param   array   $icon_arr   System icon array (not used)
     * @return  string              HTML for field display in the table
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $_SHOP_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';
        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                \Shop\Icon::getHTML('edit', 'tooltip', array('title' => $LANG_ADMIN['edit'])),
                SHOP_ADMIN_URL . "/index.php?edittaxrate=x&amp;code={$A['code']}"
            );
            break;

        case 'delete':
            $retval .= COM_createLink(
                \Shop\Icon::getHTML('delete', 'tooltip', array('title' => $LANG_ADMIN['delete'])),
                SHOP_ADMIN_URL . "/index.php?deltaxrate=x&amp;code={$A['code']}",
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
             );
            break;

        case 'combined_rate':
        case 'state_rate':
        case 'county_rate':
        case 'city_rate':
        case 'special_rate':
            $retval = sprintf('%0.5f', $fieldvalue);
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Present the edit form for a tax table record.
     *
     * @param   string  $code   Code to edit, empty for new entry
     * @return  string      HTML for editing form
     */
    public static function Edit($code='')
    {
        global $_TABLES;

        $A = NULL;
        if ($code != '') {
            $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}
                WHERE code = '" . DB_escapeString($code) . "'";
            $res = DB_query($sql);
            if ($res) {
                $A = DB_fetchArray($res, false);
            }
        }
        if (!$A) {
            $A = array(
                'code' => '',
                'country' => '',
                'state' => '',
                'region' => '',
                'zip_from' => '',
                'zip_to' => '',
                'combined_rate' => 0,
                'state_rate' => 0,
                'county_rate' => 0,
                'city_rate' => 0,
                'special_rate' => 0,
            );
        }
        $T = new Template;
        $T->set_file('form', 'edit_tax.thtml');
        $T->set_var(array(
            'code' => $A['code'],
            'country' => $A['country'],
            'state' => $A['state'],
            'region' => $A['region'],
            'zip_from' => $A['zip_from'],
            'zip_to' => $A['zip_to'],
            'combined_rate' => $A['combined_rate'],
            'state_rate' => $A['state_rate'],
            'county_rate' => $A['county_rate'],
            'city_rate' => $A['city_rate'],
            'special_rate' => $A['special_rate'],
            'doc_url' => SHOP_getDocUrl('edit_tax'),
         ) );
        return $T->parse('output', 'form');
    }


    /**
     * Delete one or more tax table records.
     *
     * @param   array|string    $code   Single code or array of codes
     */
    public static function Delete($code)
    {
        global $_TABLES;

        if (is_array($code) && empty($code)) {
            return;
        }
        if (is_array($code)) {
            foreach ($code as $idx=>$val) {
                $code[$idx] = "'" . DB_escapeString($val) . "'";
            }
            $code_str = implode(',', $code);
        } else {
            $code_str = "'" . DB_escapeString($code) . "'";
        }
        $sql = "DELETE FROM {$_TABLES['shop.tax_rates']} WHERE code IN ($code_str)";
        //echo $sql;die;
        DB_query($sql);
    }


    /**
     * Save a tax table record.
     *
     * @param   array   $A      Arra of data elements
     * @return  boolean     True on success, False on error
     */
    public static function Save($A)
    {
        global $_TABLES;

        $A['combined_rate'] = (float)$A['combined_rate'];
        $A['state_rate'] = (float)$A['state_rate'];
        $A['county_rate'] = (float)$A['county_rate'];
        $A['city_rate'] = (float)$A['city_rate'];
        $A['special_rate'] = (float)$A['special_rate'];
        if ($A['combined_rate'] == 0) {
            $A['combined_rate'] = $A['state_rate'] + $A['county_rate'] + $A['city_rate'] + $A['special_rate'];
        }
        $sql = "INSERT INTO {$_TABLES['shop.tax_rates']} SET
            code = '" . DB_escapeString(substr($A['code'], 0, 25)) . "',
            country = '" . DB_escapeString($A['country']) . "',
            state = '" . DB_escapeString($A['state']) . "',
            region = '" . DB_escapeString(substr($A['region'],0,128)) . "',
            zip_from = '" . DB_escapeString($A['zip_from']) . "',
            zip_to = '" . DB_escapeString($A['zip_to']) . "',
            combined_rate = {$A['combined_rate']},
            state_rate = {$A['state_rate']},
            county_rate = {$A['county_rate']},
            city_rate = {$A['city_rate']},
            special_rate = {$A['special_rate']}
            ON DUPLICATE KEY UPDATE
            country = '" . DB_escapeString($A['country']) . "',
            state = '" . DB_escapeString($A['state']) . "',
            region = '" . DB_escapeString($A['region']) . "',
            zip_from = '" . DB_escapeString($A['zip_from']) . "',
            zip_to = '" . DB_escapeString($A['zip_to']) . "',
            combined_rate = {$A['combined_rate']},
            state_rate = {$A['state_rate']},
            county_rate = {$A['county_rate']},
            city_rate = {$A['city_rate']},
            special_rate = {$A['special_rate']}";
        DB_query($sql);
        if (DB_error()) {
            SHOP_log("Error saving tax rate: $sql");
            return false;
        } else {
            return true;
        }
    }


    /**
     * Import tax records from an uploaded .csv file.
     *
     * @return  string      Message to display
     */
    public static function Import()
    {
        global $_CONF, $_TABLES, $LANG04, $LANG28;

        $retval = '';

        $upload = new \Shop\UploadDownload();
        $upload->setPath($_CONF['path_data']);
        $upload->setAllowedMimeTypes(array(
            'text/plain' => array('txt', 'csv'),
            'application/octet-stream' => array('txt', 'csv'),
        ) );
        // allow uploading all 50 states. May still fail due to ovarall size.
        $upload->setMaxFileUploads(50);
        $upload->setFieldName('importfile');
        $filenames = array();
        for ($i = 0; $i < $upload->numFiles(); $i++) {
            $filenames[] =  uniqid('_' . rand(100,999)) . '.csv';
        }
        $upload->setFileNames($filenames);

        if ($upload->uploadFiles()) {
            // Good, files got uploaded
            foreach ($upload->getFilenames() as $fname) {
                $filename = $_CONF['path_data'] . $fname;
                if (!is_file($filename)) { // empty upload form
                    SHOP_log("Tax upload file $filename not found");
                    echo COM_refresh(SHOP_ADMIN_URL . '/index.php?taximport');
                }
            }
        } else {
            // A problem occurred, print debug information
            $retval .= COM_startBlock ($LANG28[24], '',
                COM_getBlockTemplate ('_msg_block', 'header'));
            $retval .= $upload->printErrors(false);
            $retval .= COM_endBlock (COM_getBlockTemplate ('_msg_block', 'footer'));
            return $retval;
        }

        // Following variables track import processing statistics
        $successes = 0;
        $failures = 0;
        $sql_values = array();

        foreach ($upload->getFilenames() as $fname) {
            $filename = $_CONF['path_data'] . $fname;
            switch($_POST['provider']) {
            case 'avalara':
            default:
                $fh = fopen($filename, "r");
                $l = fgetcsv($fh, 1500, ",");    // Eat the first line
                while ($data = fgetcsv($fh, 1500, ",")) {
                    $num = count($data);
                    if ($num < 9) {
                        $failures++;
                        continue;
                    }
                    // Set the field values. Limit length based on schema
                    $country = 'US';    // only US supported
                    $code = substr(DB_escapeString($country . $data[0] . $data[1]), 0, 25);
                    $state = substr(DB_escapeString($data[0]), 0, 10);
                    $zip = substr(DB_escapeString($data[1]), 0, 10);
                    $region = substr(DB_escapeString($data[2]), 0, 128);
                    $state_rate = (float)$data[3];
                    $combined_rate = (float)$data[4];
                    $county_rate = (float)$data[5];
                    $city_rate = (float)$data[6];
                    $special_rate = (float)$data[7];
                    $risk_level = (int)$data[8];

                    $sql = "INSERT INTO {$_TABLES['shop.tax_rates']} SET
                        code = '$code',
                        country = '$country',
                        state = '$state',
                        zip_from = '$zip',
                        region = '$region',
                        combined_rate = $combined_rate,
                        state_rate = $state_rate,
                        county_rate = $county_rate,
                        city_rate = $city_rate,
                        special_rate = $special_rate
                    ON DUPLICATE KEY UPDATE
                        region = '$region',
                        combined_rate = $combined_rate,
                        state_rate = $state_rate,
                        county_rate = $county_rate,
                        city_rate = $city_rate,
                        special_rate = $special_rate";

                    $result = DB_query($sql);
                    if (!$result) {
                        $failures++;
                    } else {
                        $successes++;
                    }
                }
                break;
            }
            unlink ($filename);
        }
        $retval .= '<p>' . sprintf ($LANG28[32], $successes, $failures);
        return $retval;
    }

}

?>
