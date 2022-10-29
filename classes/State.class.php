<?php
/**
 * Class to handle State information.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.2
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class to handle state information.
 * @package shop
 */
class State extends RegionBase
{
    /** Table key.
     * @var string */
    protected static $TABLE = 'shop.states';

    /** Cache tag.
     * @var string */
    protected static $TAG = 'states';

    /** Table type, used to create variable names.
     * .@var string */
    protected static $KEY = 'state';

    /** State DB record ID.
     * @var integer */
    private $state_id;

    /** Country DB record ID.
     * @var integer */
    private $country_id = '';

    /** Country ISO code.
     * @var string */
    private $country_iso = '';

    /** Country Name.
     * @var string */
    private $state_name = '';

    /** Country ISO code.
     * @var string */
    private $iso_code = '';

    /** Sales are allowed to this state?
     * @var integer */
    private $state_enabled = 1;

    /** Country object.
     * @var object */
    private $Country = NULL;

    /** Does this state charge tax on shipping?
     * @var boolean */
    private $tax_shipping = 0;

    /** Does this state charge tax on handling?
     * @var boolean */
    private $tax_handling = 0;


    /**
     * Create an object and set the variables.
     *
     * @param   array   $A  DB record array
     */
    public function __construct(?array $A=NULL)
    {
        if (is_array($A)) {
            $this->setID(SHOP_getVar($A, 'state_id', 'integer'))
                 ->setCountryID(SHOP_getVar($A, 'country_id', 'integer'))
                 ->setCountryISO(SHOP_getVar($A, 'country_iso'))
                 ->setISO(SHOP_getVar($A, 'iso_code'))
                 ->setName(SHOP_getVar($A, 'state_name'))
                 ->setEnabled(SHOP_getVar($A,'state_enabled', 'integer', 1))
                 ->setTaxHandling(SHOP_getVar($A, 'tax_handling', 'integer'))
                 ->setTaxShipping(SHOP_getVar($A, 'tax_shipping', 'integer'));
        }
    }


    /**
     * Get a State object based on the DB record ID.
     *
     * @param   integer $id     Record ID
     * @return  object      State object
     */
    public static function getByRecordId(int $id) : self
    {
        $qb = Database::getInstance()->conn->createQueryBuilder();
        $qb->where('s.state_id = :id')
           ->setParameter('id', $id, Database::INTEGER);
        return self::_getInstance($qb);
    }


    /**
     * Get a State object based on an ISO code, e.g. `US-CA`.
     *
     * @param   string  $code       ISO code
     * @return  object      State object
     */
    public static function getByIsoCode(string $code) : self
    {
        $qb = Database::getInstance()->conn->createQueryBuilder();
        $parts = explode('-', $code);
        if (count($parts) == 2) {
            $qb->where('s.iso_code = :s_iso')
               ->andWhere('c.alpha2 = :c_iso')
               ->setParameter('s_iso', $parts[1], Database::STRING)
               ->setParameter('c_iso', $parts[0], Database::STRING);
        } else {
            // Try with just the state, but this is unpredictable
            $qb->where('s.iso_code = :s_iso')
               ->setParameter('s_iso', $parts[0], Database::STRING);
        }
        return self::_getInstance($qb);
    }


    /**
     * Get a state object by passing in an Address object.
     *
     * @param   object  $Addr   Address object
     * @return  object      State object
     */
    public static function getByAddress(Address $Addr) : self
    {
        $code = $Addr->getCountry() . '-' . $Addr->getState();
        return self::getByIsoCode($code);
    }


    /**
     * Create the final query to retrieve a state object.
     *
     * @param   object  $qb     QueryBuilder object
     * @return  object      State object
     */
    private static function _getInstance(\Doctrine\DBAL\Query\QueryBuilder $qb) : self
    {
        global $_TABLES;

        $qb->select('s.*', 'c.alpha2 AS country_iso')
           ->from($_TABLES['shop.states'], 's')
           ->leftJoin('s', $_TABLES['shop.countries'], 'c', 'c.country_id = s.country_id')
           ->setFirstResult(0)
           ->setMaxResults(1);
        try {
            $A = $qb->execute()->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (!$A) {
            // Expected by the constructor.
            $A = NULL;
        }
        return new self($A);
    }


    /**
     * Set the ISO code.
     *
     * @param   string  $code   2-letter ISO code
     * @return  object  $this
     */
    private function setISO($code)
    {
        $this->iso_code = $code;
        return $this;
    }


    /**
     * Return the ISO code for the state.
     *
     * @return  string      ISO code
     */
    public function getISO()
    {
        return $this->iso_code;
    }


    /**
     * Set the record ID.
     *
     * @param   integer $id     DB record ID
     * @return  object  $this
     */
    private function setID($id)
    {
        $this->state_id = (int)$id;
        return $this;
    }


    /**
     * Return the DB record ID for the state.
     *
     * @return  integer     Record ID
     */
    public function getID()
    {
        return (int)$this->state_id;
    }


    /**
     * Set the Country record ID.
     *
     * @param   integer $id     DB record ID for the parent country
     * @return  object  $this
     */
    private function setCountryID($id)
    {
        $this->country_id = (int)$id;
        return $this;
    }


    /**
     * Set the Country ISO code.
     * This is not set in the DB, just used as needed.
     *
     * @param   string  $iso    Country ISO code
     * @return  object  $this
     */
    private function setCountryISO($iso)
    {
        $this->country_iso = $iso;
        return $this;
    }


    /**
     * Return the DB record ID for the state.
     *
     * @return  integer     Record ID
     */
    public function getCountryID()
    {
        return (int)$this->country_id;
    }


    /**
     * Set the Enabled flag for the state.
     *
     * @param   integer $enabled    Zero to disable, nonzero to enable
     * @return  object  $this
     */
    private function setEnabled($enabled)
    {
        $this->state_enabled = $enabled == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Get the value of the Enabled flag for the state.
     *
     * @return  integer     Zero if disabled, 1 if enabled
     */
    public function getEnabled()
    {
        return $this->state_enabled ? 1 : 0;
    }


    /**
     * Check if the state is enabled.
     * State, Country and Region must be enabled to return true
     *
     * @return  boolean     True if enabled, False if not.
     */
    public function isEnabled()
    {
        return (
            $this->state_enabled &&
            $this->getCountry()->isEnabled()
        );
    }


    /**
     * Set the State full name.
     *
     * @param   string  $name   Name of state
     * @return  object  $this
     */
    private function setName($name)
    {
        $this->state_name = $name;
        return $this;
    }


    /**
     * Return USPS state name by state ISO 3166-1-alpha-2 code.
     * Return empty string for unknown countries.
     *
     * @return  string      Country name, empty string if not found
     */
    public function getName()
    {
        return $this->state_name;
    }


    /**
     * Get the Country object associated with this state.
     *
     * @return  object  Country object
     */
    public function getCountry()
    {
        if ($this->Country === NULL) {
            $this->Country = Country::getByRecordId($this->getCountryID());
        }
        return $this->Country;
    }


    /**
     * Set the tax_shipping flag.
     *
     * @param   boolean $flag   True to tax shipping, False if not
     * @return  object  $this
     */
    public function setTaxShipping($flag)
    {
        $this->tax_shipping = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this state charges tax on shipping.
     *
     * @return  integer     1 if tax is charged, 0 if not.
     */
    public function taxesShipping()
    {
        return $this->tax_shipping ? 1 : 0;
    }


    /**
     * Set the tax_handling flag.
     *
     * @param   boolean $flag   True to tax handling, False if not
     * @return  object  $this
     */
    public function setTaxHandling($flag)
    {
        $this->tax_handling = $flag ? 1 : 0;
        return $this;
    }


    /**
     * Check if this state charges tax on handling charges.
     *
     * @return  integer     1 if tax is charged, 0 if not.
     */
    public function taxesHandling()
    {
        return $this->tax_handling ? 1 : 0;
    }


    /**
     * Get all the state objects as an array.
     *
     * @param   string  $country    ISO code for a country
     * @param   boolean $enabled    True to get only enabled states
     * @return  array       Array of State objects
     */
    public static function getAll(string $country, bool $enabled=true) : array
    {
        global $_TABLES;

        $enabled = $enabled ? 1 : 0;
        $cache_key = 'shop.states.' . $country . '_' . $enabled;
        $retval = Cache::get($cache_key);
        if ($retval === NULL) {
            $retval = array();
            $qb = Database::getInstance()->conn->createQueryBuilder();
            try {
                $qb->select('s.state_name', 's.iso_code')
                   ->from($_TABLES['shop.states'], 's')
                   ->leftJoin('s', $_TABLES['shop.countries'], 'c', 'c.country_id = s.country_id')
                   ->leftJoin('c', $_TABLES['shop.regions'], 'r', 'r.region_id = c.region_id')
                   ->where('c.alpha2 = :country')
                   ->setParameter('country', $country, Database::STRING)
                   ->orderBy('s.state_name', 'ASC');
                if ($enabled) {
                    $qb->andWhere('s.state_enabled = 1')
                       ->andWhere('c.country_enabled = 1')
                       ->andWhere('r.region_enabled = 1');
                }
                $stmt = $qb->execute();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $retval[$A['iso_code']] = new self($A);
                }
            }
            Cache::set($cache_key, $retval, 'regions', 43200);
        }
        return $retval;
    }


    /**
     * Return option list elements to select a state.
     *
     * @param   string  $country    Country code
     * @param   string  $state      Selected state code
     * @param   boolean $enabled    True to return only enabled states
     * @return  string      HTML option elements
     */
    public static function optionList($country='', $state='', $enabled=true)
    {
        $retval = '';
        $arr = self::getAll($country, $enabled);
        foreach ($arr as $iso=>$S) {
            $selected = $state == $iso ? 'selected="selected"' : '';
            $retval .= "<option $selected value=\"$iso\">{$S->getName()}</option>";
        }
        return $retval;
    }


    /**
     * Edit a state record.
     *
     * @return  string      HTML for editing form
     */
    public function Edit()
    {
        $T = new Template('admin');
        $T->set_file(array(
            'form' => 'state.thtml',
        ) );

        $T->set_var(array(
            'state_id'      => $this->getID(),
            'state_name'    => $this->getName(),
            'iso_code'      => $this->getISO(),
            'ena_chk'       => $this->state_enabled ? 'checked="checked"' : '',
            'tx_shp_chk'    => $this->tax_shipping ? 'checked="checked"' : '',
            'tx_hdl_chk'    => $this->tax_handling ? 'checked="checked"' : '',
            'country_options' => Country::optionLIst($this->country_iso, false),
            'tooltipster_js' => Tooltipster::get('state_form'),
        ) );
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Save the state information.
     *
     * @param   array   $A  Optional data array from $_POST
     * @return  boolean     True on success, False on failure
     */
    public function Save($A=NULL)
    {
        global $_TABLES, $LANG_SHOP;

        $this->Country = Country::getByIsoCode($A['country_iso']);
        $country_id = $this->Country->getID();
        if (is_array($A)) {
            $this->setID($A['state_id'])
                ->setCountryID($country_id)
                ->setCountryISO($A['country_iso'])
                ->setISO($A['iso_code'])
                ->setName($A['state_name'])
                ->setEnabled($A['state_enabled']);
        }
        $values = array(
            'country_id' => $this->getCountryID(),
            'iso_code' => $this->getISO(),
            'state_name' => $this->getName(),
            'state_enabled' => $this->getEnabled(),
            'tax_shipping' =>$this->taxesShipping(),
            'tax_handling' => $this->taxesShipping(),
        );
        $types = array(
            Database::STRING,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::INTEGER,
        );

        $db = Database::getInstance();
        try {
            if ($this->getID() > 0) {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.states'],
                    $values,
                    array('state_id' => $this->getID()),
                    $types
                );
            } else {
                $db->conn->insert(
                    $_TABLES['shop.states'],
                    $values,
                    $types
                );
                $this->setID($db->conn->lastInsertId());
            }
            $status = true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $this->addError($LANG_SHOP['err_dup_iso']);
            $status = false;
        }

        return $status;
    }


    /**
     * State Admin List View.
     *
     * @param   integer $country_id     Optional country ID to limit list
     * @return  string      HTML for the product list.
     */
    public static function adminList($country_id = 0)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $LANG_SHOP_HELP;

        $display = '';
        $country_id = (int)$country_id;
        $sql = "SELECT s.state_id, s.state_name, s.state_enabled, s.iso_code,
                s.tax_shipping, s.tax_handling, c.country_name
            FROM {$_TABLES['shop.states']} s
            LEFT JOIN {$_TABLES['shop.countries']} c
                ON c.country_id = s.country_id";
        $header_arr = array(
            array(
                'text'  => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort'  => false,
                'align' => 'center',
            ),
            array(
                'text'  => 'ID',
                'field' => 'state_id',
                'sort'  => true,
                'align' => 'right',
            ),
            array(
                'text'  => 'State Name',
                'field' => 'state_name',
                'sort'  => true,
            ),
            array(
                'text'  => 'Country Name',
                'field' => 'country_name',
                'sort'  => true,
            ),
            array(
                'text'  => 'ISO Code',
                'field' => 'iso_code',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['tax_shipping'],
                'field' => 'tax_shipping',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['tax_handling'],
                'field' => 'tax_handling',
                'sort'  => true,
                'align' => 'center',
            ),
            array(
                'text'  => $LANG_SHOP['enabled'],
                'field' => 'state_enabled',
                'sort'  => true,
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 's.iso_code',
            'direction' => 'asc',
        );

        $display .= COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/regions.php?editstate=0',
            'style' => 'success',
        ) );

        $query_arr = array(
            'table' => 'shop.states',
            'sql' => $sql,
            'query_fields' => array('s.iso_code', 's.state_name'),
            'default_filter' => $country_id > 0 ? "WHERE s.country_id=$country_id" : 'WHERE 1=1',
        );

        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . "/regions.php?states=x&country_id=$country_id",
        );

        /*$options = array(
            'chkdelete' => 'true',
            'chkfield' => 'state_id',
            'chkname' => 'state_id',
            'chkactions' => self::getBulkActionButtons(),
        );*/

        $T = new Template('admin');
        $T->set_file(array(
            'filter' => 'sel_region.thtml',
        ) );
        $T->set_var(array(
            'lang_regiontype' => $LANG_SHOP['country'],
            'fld_name' => 'country_id',
            'onchange_url' => SHOP_ADMIN_URL . '/regions.php?states&amp;country_id=',
        ) );
        $T->set_block('filter', 'regionOptions', 'rOpts');
        foreach (self::countrySelection($country_id) as $c_id=>$c_data) {
            $T->set_var(array(
                'opt_value' => $c_id,
                'opt_name' => $c_data['country_name'],
                'selected' => $c_id == $country_id,
            ) );
            $T->parse('rOpts', 'regionOptions', true);
        }
        $T->parse('output', 'filter');
        $filter = $T->finish($T->get_var('output'));

        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_statelist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '',
            self::getAdminListOptions(),
            ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the state admin list.
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
                'url' => SHOP_ADMIN_URL . '/regions.php?editstate=' . $A['state_id'],
            ) );
            break;

        case 'state_enabled':
        case 'tax_shipping':
        case 'tax_handling':
            $retval .= FieldList::checkbox(array(
                'name' => 'ena_check',
                'id' => "togenabled{$A['state_id']}",
                'checked' => $fieldvalue == 1,
                'onclick' => "SHOP_toggle(this,'{$A['state_id']}','{$fieldname}','state');",
            ) );
            break;
        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Get the state ISO code from the country ISO and state name.
     *
     * @param   string  $alpha2     2-letter ISO code for the country
     * @param   string  $state_name Full state name
     * @return  string      2-letter ISO code for state
     */
    public static function isoFromName(string $alpha2, string $state_name) : string
    {
        global $_TABLES;

        $retval = '';
        if (empty($state_name)) {
            return $retval;
        }

        $qb = Database::getInstance()->conn->createQueryBuilder();
        try {
            $A = $qb->select('s.iso_code')
               ->from($_TABLES['shop.states'], 's')
               ->leftJoin('s', $_TABLES['shop.countries'], 'c', 'c.country_id = s.country_id')
               ->where('c.alpha2 = :alpha2')
               ->andWhere('s.state_name = :state_name')
               ->setParameter('alpha2', $alpha2, Database::STRING)
               ->setParameter('state_name', $state_name, Database::STRING)
               ->execute()
               ->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $A = false;
        }
        if (is_array($A)) {
            $retval = $A['iso_code'];
        }
        return $retval;
    }


    /**
     * Create a country selection dropdown showing only countries with states.
     *
     * @param   integer $sel    Selected country ID
     * @return  array       Array of country_id=>array(country_name, selected)
     */
    private static function countrySelection(int $sel = 0) : array
    {
        global $_TABLES;

        $qb = Database::getInstance()->conn->createQueryBuilder();
        try {
            $stmt = $qb->select('s.country_id', 'c.country_name')
                    ->from($_TABLES['shop.states'], 's')
                    ->leftJoin('s', $_TABLES['shop.countries'], 'c', 's.country_id = c.country_id')
                    ->groupBy('s.country_id')
                    ->orderBy('c.country_name', 'ASC')
                    ->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $retval[$A['country_id']] = array(
                    'country_name' => $A['country_name'],
                    'selected' => $A['country_id'] == $sel,
                );
            }
        }
        return $retval;
    }

}
