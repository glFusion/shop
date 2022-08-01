<?php
/**
 * Class to handle Regions.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;


/**
 * Class to handle UN subregion information.
 * @package shop
 */
class Region extends RegionBase
{
    /** Table key.
     * @var string */
    protected static $TABLE = 'shop.regions';

    /** Cache tag.
     * @var string */
    protected static $TAG = 'regions';

    /** Table type, used to create variable names.
     * .@var string */
    protected static $KEY = 'region';

    /** Region DB record ID.
     * @var integer */
    private $region_id = 0;

    /** UN Region Code.
     * @var integer */
    private $region_code = 0;

    /** Region Name.
     * @var string */
    private $region_name = '';

    /** Sales are allowed to this region?
     * @var integer */
    private $region_enabled = 1;


    /**
     * Create an object and set the variables.
     *
     * @param   array   $A      Array from form or DB record
     */
    public function __construct($A)
    {
        $this->setID($A['region_id'])
            ->setCode($A['region_code'])
            ->setName($A['region_name'])
            ->setEnabled($A['region_enabled']);
    }


    /**
     * Get an instance of a Region object.
     *
     * @param   integer $id     Region DB record ID
     * @return  object  Country object
     */
    public static function getInstance($id)
    {
        global $_TABLES;
        static $instances = array();

        $id = (int)$id;
        if (isset($instances[$id])) {
            return $instances[$id];
        } else {
            try {
                $A = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.regions']} WHERE region_id = ?",
                    array($id),
                    array(Database::INTEGER)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
            if (!is_array($A)) {
                // Create an empty region.
                // Set enabled to true so isEnabled() will return true
                // when there is no region assigned (e.g. Antarctica)
                $A = array(
                    'region_id'     => 0,
                    'region_code'   => 0,
                    'region_name'   => '',
                    'region_enabled' => 1,
                );
            }
            return new self($A);
        }
    }


    /**
     * Set the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->region_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the country.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->region_id;
    }


    /**
     * Set the UN region code.
     *
     * @param   $code   integer     Region code
     */
    private function setCode($code)
    {
        $this->region_code = (int)$code;
        return $this;
    }


    /**
     * Get the UN region code.
     *
     * @return  integer     Region code
     */
    public function getCode()
    {
        return (int)$this->region_code;
    }


    /**
     * Set the Enabled flag to one or zero.
     *
     * @param   integer $enabled    Zero to disable, nonzero to enable
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->region_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Check if this region is enabled for sales.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    public function isEnabled()
    {
        return (int)$this->region_enabled;
    }


    /**
     * Set the Region name.
     *
     * @param   string  $name   Name of region
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->region_name = $name;
        return $this;
    }


    /**
     * Get the region name.
     *
     * @return  string      Country name, empty string if not found
     */
    public function getName()
    {
        return $this->region_name;
    }


    /**
     * Get all regions into an array of objects.
     *
     * @param   string  $enabled    True to only include enabled regions
     * @return  array       Array of Region objects
     */
    public static function getAll($enabled=true)
    {
        global $_TABLES;

        $enabled = $enabled ? 1 : 0;
        $cache_key = 'shop.regions_all_' . $enabled;
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $qb = Database::getInstance()->conn->createQueryBuilder();
            $qb->select('*')
                ->from($_TABLES['shop.regions'])
               ->orderBy('region_name', 'ASC');
            if ($enabled) {
                $qb->where('region_enabled = 1');
            }
            try {
                $stmt = $qb->execute();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $retval[$A['region_id']] = new self($A);
                }
            }
            // Cache for a month, this doesn't change often
            Cache::set($cache_key, $retval, 'regions', 43200);
        }
        if (!is_array($retval)) {
            $retval = array();
        }
        return $retval;
    }


    /**
     * Create the option tags for a region selection list.
     *
     * @uses    self::getAll()
     * @param   string  $sel    Currently-selected region ID
     * @param   boolean $enabled    True for only enabled regions
     * @return  string      Option tags for selection list
     */
    public static function optionList($sel = 0, $enabled = true)
    {
        $retval = '';
        $arr = self::getAll($enabled);
        foreach ($arr as $id=>$R) {
            $selected = $sel == $id ? 'selected="selected"' : '';
            $retval .= "<option $selected value=\"$id\">{$R->getName()}</option>";
        }
        return $retval;
    }


    /**
     * Edit a region record.
     *
     * @return  string      HTML for editing form
     */
    public function Edit()
    {
        $T = new Template('admin');
        $T->set_file(array(
            'form' => 'region.thtml',
        ) );

        $T->set_var(array(
            'region_id'     => $this->getID(),
            'region_code'   => $this->getCode(),
            'region_name'   => $this->getName(),
            'ena_chk'       => $this->region_enabled ? 'checked="checked"' : '',
            'tooltipster_js' => Tooltipster::get('region_form'),
        ) );
        $T->parse('output','form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save the region information.
     *
     * @param   array   $A  Optional data array from $_POST
     * @return  boolean     True on success, False on failure
     */
    public function Save($A=NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setID($A['region_id'])
                ->setCode($A['region_code'])
                ->setName($A['region_name'])
                ->setEnabled($A['region_enabled']);
        }
        $db = Database::getInstance();
        $values = array(
            'region_name' => $this->getName(),
            'region_code' => $this->getCode(),
            'region_enabled' => $this->isEnabled(),
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
        );
        try {
            if ($this->getID() > 0) {
                $values[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.regions'],
                    $values,
                    array('region_id' => $this->getID()),
                    $types
                );
            } else {
                $db->conn->insert(
                    $_TABLES['shop.regions'],
                    $values,
                    $types
                );
            }
            $this->setID($db->conn->lastInsertId());
            $status = true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $status = false;
        }
        return $status;
    }


    /**
     * Region Admin List View.
     *
     * @return  string      HTML for the product list.
     */
    public static function adminList()
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $sql = "SELECT * FROM {$_TABLES['shop.regions']}";
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'region_id',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => 'Region Name',
                'field' => 'region_name',
                'sort'  => true,
            ),
            array(
                'text'  => 'Enabled',
                'field' => 'region_enabled',
                'sort'  => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'region_name',
            'direction' => 'asc',
        );

        $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/regions.php?editregion=x',
            'style' => 'success',
        ) );

        $query_arr = array(
            'table' => 'shop.regions',
            'sql' => $sql,
            'query_fields' => array('region_name'),
            'default_filter' => 'WHERE 1=1',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/regions.php?regions',
        );

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_regionlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '',
            self::getAdminListOptions(),
            ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the region admin list.
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
            $retval = FieldList::edit(array(
                'url' => SHOP_ADMIN_URL . '/regions.php?editregion=' . $A['region_id'],
            ) );
            break;

        case 'region_enabled':
            $retval = FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['region_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['region_id']}','region_enabled','region');",
            ) );
            break;

        case 'region_name':
            // Drill down to the countries in this region
            $retval = COM_createLink(
                $fieldvalue,
                SHOP_ADMIN_URL . "/regions.php?countries&region_id={$A['region_id']}"
            );
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
