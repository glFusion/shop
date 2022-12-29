<?php
/**
 * Use the static databsae table to retrieve sales tax rates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Tax;
use glFusion\Database\Database;
use Shop\Template;
use Shop\Config;
use Shop\FieldList;
use Shop\Log;
use Shop\Models\DataArray;


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
    protected function _getData() : array
    {
        global $LANG_SHOP, $_TABLES;

        // Default data returned if there is no nexus, or a rate entry
        // is not found.
        $data = $this->default_rates;

        if ($this->hasNexus()) {
            $sql = "SELECT * FROM {$_TABLES['shop.tax_rates']}
                WHERE country = ?
                AND (
                    zip_from = ? OR ? BETWEEN zip_from AND zip_to
                ) ORDER BY zip_from DESC, zip_to ASC
                LIMIT 1";
            try {
                $A = Database::getInstance()->conn->executeQuery(
                    $sql,
                    array(
                        $this->Address->getCountry(),
                        $this->Address->getPostal(),
                        $this->Address->getPostal(),
                    ),
                    array(Database::STRING, Database::STRING, Database::STRING)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }

            if (is_array($A)) {
                $A = new DataArray($A);
                $data = array(
                    'totalRate' => $A->getFloat('combined_rate'),
                    'rates' => array(
                        array(
                            'rate'  => $A->getFloat('state_rate'),
                            'name'  => $A['state'] . ' ' . $LANG_SHOP['state_rate'],
                            'type'  => 'State',
                        ),
                        array(
                            'rate'  => $A->getFloat('county_rate'),
                            'name'  => $A['state'] . ' ' . $LANG_SHOP['county_rate'],
                            'type'  => 'County',
                        ),
                        array(
                            'rate'  => $A->getFloat('city_rate'),
                            'name'  => $A['region'] . ' ' . $LANG_SHOP['city_rate'],
                            'type'  => 'City',
                        ),
                        array(
                            'rate'  => $A->getFloat('special_rate'),
                            'name'  => $A['region'] . ' ' . $LANG_SHOP['special_rate'],
                            'type'  => 'Special',
                        ),
                    ),
                );
                if ($this->Order) {
                    foreach ($this->Order->getItems() as $OI) {
                        $OI->setTaxRate((float)$A['combined_rate']);
                    }
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
    public static function adminList() : string
    {
        global $_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

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
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_rate'],
            'url' => SHOP_ADMIN_URL . '/index.php?edittaxrate=x',
            'style' => 'success',
        ) );

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
            'chkactions' => FieldList::button(array(
                'type' => 'submit',
                'name' => 'deltaxrate',
                'value' => 'x',
                'style' => 'primary',
                'size' => 'mini',
                'title' => $LANG_SHOP['delete'],
                'text' => FieldList::minus(),
            ) ),
        );

        $filter = '';
        $display .= ADMIN_list(
            Config::PI_NAME . '_salestax',
            array(__CLASS__ , 'getAdminField'),
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
    public static function getAdminField(string $fieldname, string $fieldvalue, array $A, array $icon_arr)
    {
        global $_CONF, $LANG_SHOP, $LANG_ADMIN;

        $retval = '';
        switch($fieldname) {
        case 'edit':
            $retval .= FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . "/index.php?edittaxrate=x&amp;code={$A['code']}",
            ) );
            break;

        case 'delete':
            $retval .= FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL . "/index.php?deltaxrate=x&amp;code={$A['code']}",
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                ),
            ) );
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
    public static function Edit(string $code='') : string
    {
        global $_TABLES;

        $A = NULL;
        if ($code != '') {
            try {
                $A = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.tax_rates']} WHERE code = ?",
                    array($code),
                    array(Database::STRING)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
        }
        if (empty($A)) {
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

        if (!is_array($code)) {
            $code = array($code);
        }
        try {
            Database::getInstance()->conn->executeStatement(
                "DELETE FROM {$_TABLES['shop.tax_rates']} WHERE code IN (?)",
                array($code_str),
                array(Database::PARAM_STR_ARRAY)
            );
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Save a tax table record.
     *
     * @param   array   $A      Arra of data elements
     * @return  boolean     True on success, False on error
     */
    public static function Save(array $A) : bool
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
        $db = Database::getInstance();
        $values = array(
            'country' => $A['country'],
            'state' => $A['state'],
            'region' => substr($A['region'],0,128),
            'zip_from' => $A['zip_from'],
            'zip_to' => $A['zip_to'],
            'combined_rate' => $A['combined_rate'],
            'state_rate' => $A['state_rate'],
            'county_rate' => $A['county_rate'],
            'city_rate' => $A['city_rate'],
            'special_rate' => $A['special_rate'],
            'code' => substr($A['code'], 0, 25),
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::STRING,
        );
        try {
            $db->conn->insert($_TABLES['shop.tax_rates'], $values, $types);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            array_pop($values);     // remove code
            $db->conn->update(
                $_TABLES['shop.tax_rates'],
                $values,
                array('code' => substr($A['code'], 0, 25)),
                $types
            );
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Import tax records from an uploaded .csv file.
     *
     * @return  string      Message to display
     */
    public static function Import() : string
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
                    Log::error("Tax upload file $filename not found");
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

        $db = Database::getInstance();
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
                    $code = substr($country . $data[0] . $data[1], 0, 25);
                    $state = substr($data[0], 0, 10);
                    $zip = substr($data[1], 0, 10);
                    $region = substr($data[2], 0, 128);
                    $combined_rate = (float)$data[3];
                    $state_rate = (float)$data[4];
                    $county_rate = (float)$data[5];
                    $city_rate = (float)$data[6];
                    $special_rate = (float)$data[7];
                    $risk_level = (int)$data[8];

                    try {
                        $db->conn->insert(
                            $_TABLES['shop.tax_rates'],
                            array(
                                'code' => $code,
                                'country' => $country,
                                'state' => $state,
                                'zip_from' => $zip,
                                'region' => $region,
                                'combined_rate' => $combined_rate,
                                'state_rate' => $state_rate,
                                'county_rate' => $county_rate,
                                'city_rate' => $city_rate,
                                'special_rate' => $special_rate,
                            ),
                            array(
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                            )
                        );
                        $successes++;
                    } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
                        $db->conn->update(
                            $_TABLES['shop.tax_rates'],
                            array(
                                'region' => $region,
                                'combined_rate' => $combined_rate,
                                'state_rate' => $state_rate,
                                'county_rate' => $county_rate,
                                'city_rate' => $city_rate,
                                'special_rate' => $special_rate,
                            ),
                            array('code' => $code,),
                            array(
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                                Database::STRING,
                            )
                        );
                        $successes++;
                    } catch (\Throwable $e) {
                        Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                        $failures++;
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

