<?php
/**
 * Class to handle addresses for suppliers and brands.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.5.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;
use Shop\Models\DataArray;


/**
 * Class for supplier and brand information.
 * @package shop
 */
class Supplier extends Address
{
    use \Shop\Traits\DBO;        // Import database operations

    /** Table name, used by DBO.
     * @var string */
    protected static $TABLE = 'shop.suppliers';

    /** Key field ID, used by DBO.
     * @var string */
    protected static $F_ID = 'sup_id';

    /** Flag indicates that this is a supplier (default)
     * @var integer */
    private $is_supplier = 1;

    /** Flag indicates that this is a product brand (not default)
     * @var integer */
    private $is_brand = 0;

    /** Description field.
     * @var string */
    private $dscp = '';

    /** Normal lead time for this supplier.
     * @var string */
    private $lead_time = '';

    /** Logo image filename.
     * @var string */
    private $logo_image = '';

    /** Array of error messages.
     * @var array */
    private $_errors = array();


    /**
     * Constructor. Reads in the specified record,
     *
     * @param   integer     $sup_id    Supplier/Brand ID
     */
    public function __construct($sup_id=0)
    {
        global $_TABLES, $_SHOP_CONF;

        $this->setID($sup_id);
        $this->setCountry($_SHOP_CONF['country']);
        $this->setState($_SHOP_CONF['state']);
        if ($this->getID() > 0) {
            try {
                $A = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.suppliers']}
                    WHERE sup_id = ?",
                    array($this->getID()),
                    array(Database::INTEGER)
                )->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
            if (is_array($A)) {
                $this->setVars(new DataArray($A));
            } else {
                $this->setID(0);
            }
        }
    }


    /**
     * Get a specific supplier by ID.
     *
     * @param   integer $id Record ID to retrieve
     * @return  object      Supplier object, empty if not found
     */
    public static function getInstance(?int $id=NULL) : self
    {
        static $suppliers = array();

        $id = (int)$id;
        if (isset($suppliers[$id])) {
            return $suppliers[$id];
        } else {
            return new self($id);
        }
    }


    /**
     * Set the record values into local variables.
     *
     * @param   DataArray   $data   Form or DB record data
     * @return  object  $this
     */
    public function setVars(DataArray $data) : self
    {
        global $_SHOP_CONF;

        if (isset($data['logo_image'])) {
            $this->setLogoImage($data->getString('logo_image'));
        }
        return $this->setID($data->getInt('sup_id'))
            ->setName($data->getString('name'))
            ->setCompany($data->getString('company'))
            ->setAddress1($data->getString('address1'))
            ->setAddress2($data->getString('address2'))
            ->setCity($data->getString('city'))
            ->setState($data->getString('state'))
            ->setPostal($data->getString('zip'))
            ->setCountry($data->getString('country', $_SHOP_CONF['country']))
            ->setPhone($data->getString('phone'))
            ->setDscp($data->getString('dscp'))
            ->setIsSupplier($data->getInt('is_supplier', 0))
            ->setIsBrand($data->getInt('is_brand', 0))
            ->setLeadTime($data->getString('lead_time'));
    }


    /**
     * Set the lead time text for this supplier.
     *
     * @param   string  $str    Lead time description
     * @return  object  $this
     */
    public function setLeadTime($str)
    {
        $this->lead_time = $str;
        return $this;
    }


    /**
     * Set the description text.
     *
     * @param   string  $dscp   Description text
     * @return  object  $this
     */
    public function setDscp($dscp)
    {
        $this->dscp = $dscp;
        return $this;
    }


    /**
     * Get the description text.
     *
     * @rturn   string      Description text
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Set the `supplier` flag for this supplier.
     * Public so it can be called from the upgrade process.
     *
     * @param   integer $val    Zero or one
     * @return  object  $this
     */
    public function setIsSupplier($val)
    {
        $this->is_supplier = $val == 1 ? 1 : 0;
        return $this;
    }


    /**
     * Get the is_supplier flag value.
     *
     * @return  integer     Value of is_supplier flag
     */
    public function getIsSupplier()
    {
        return (int)$this->is_supplier;
    }


    /**
     * Set the `brand` flag for this supplier.
     * Public so it can be called from the upgrade process.
     *
     * @param   integer $val    Zero or one
     * @return  object  $this
     */
    public function setIsBrand($val)
    {
        $this->is_brand = $val == 0 ? 0 : 1;
        return $this;
    }


    /**
     * Get the is_brand flag value.
     *
     * @return  integer     Value of is_brand flag
     */
    public function getIsBrand()
    {
        return (int)$this->is_brand;
    }


    /**
     * Get the display name.
     * If the company is provided, return it. Otherwise return the name.
     * The company name should always be available.
     *
     * @return  string  Company or Individual name.
     */
    public function getDisplayName()
    {
        return empty($this->getCompany()) ? $this->getName() : $this->getCompany();
    }


    /**
     * Get the lead time text for this supplier.
     *
     * @return  string  Lead time description
     */
    public function getLeadTime()
    {
        return $this->lead_time;
    }


    /**
     * Set the logo image filename.
     *
     * @param   string  $fname  Image filename
     * @return  object  $this
     */
    public function setLogoImage($fname)
    {
        $this->logo_image = $fname;
        return $this;
    }


    /**
     * Get the logo image filename only, no path.
     *
     * @return  string      Logo image filename
     */
    public function getLogoImage()
    {
        return $this->logo_image;
    }


    /**
     * Get a selection list for brand, supplier, or all records.
     *
     * @param   integer $sel    Selected record ID
     * @param   string  $type   Type (brand or supplier), empty for all
     * @return  string      HTML `<option>` elements
     */
    public static function getSelection($sel=0, $type='')
    {
        global $_TABLES;

        switch ($type) {
        case 'brand':
        case 'supplier':
            $where = "is_{$type} = 1";
            break;
        default:
            $where = '';
            break;
        }
        return COM_optionList(
            $_TABLES['shop.suppliers'],
            'sup_id,company',
            (int)$sel,
            1,
            $where
        );
    }


    /**
     * Get the selection list for the brand.
     *
     * @uses    self::getSelection()
     * @param   integer $sel    Selected record ID
     * @return  string      HTML `<option>` elements
     */
    public static function getBrandSelection($sel=0)
    {
        return self::getSelection($sel, 'brand');
    }


    /**
     * Get the selection list for the supplier.
     *
     * @uses    self::getSelection()
     * @param   integer $sel    Selected record ID
     * @return  string      HTML `<option>` elements
     */
    public static function getSupplierSelection($sel=0)
    {
        return self::getSelection($sel, 'supplier');
    }


    /**
     * Get the error messages created during an operation.
     *
     * @return  array   Array of error messages
     */
    public function getErrors()
    {
        return $this->_errors;
    }


    /**
     * Deletes a single image from disk.
     *
     * @param   string  $fname  Optional filename override.
     */
    public function deleteImage(?string $fname=NULL) : void
    {
        if ($fname === NULL) {
            $fname = $this->getLogoImage();
        }
        $path = Config::get('tmpdir') . '/images/brands';
        if (is_file("{$path}/{$fname}")) {
            @unlink("{$path}/{$fname}");
        }
        if ($fname === NULL) {
            $this->setLogoImage('');
        }
    }


    /**
     * Save the supplier information.
     *
     * @param   array   $A  Optional data array from $_POST
     * @return  int     Record number saved
     */
    public function Save(?DataArray $A=NULL) : int
    {
        global $_TABLES;

        // Handle the image upload first.
        if (
            !empty($_FILES) &&
            is_array($_FILES['logofile']) &&
            !empty($_FILES['logofile']['tmp_name'])
        ) {
            $Img = new Images\Supplier($this->getID(), 'logofile');
            $Img->uploadFiles();
            if (!empty($Img->getErrors())) {
                $this->_errors = array_merge($this->_errors, $Img->getErrors());
                return false;
            } else {
                if (!empty($this->getLogoImage())) {
                    $this->deleteImage();
                }
                $this->setLogoImage($Img->getFilenames()[0]);
            }
        }
        if (!empty($A)) {
            $this->setVars($A);
            if (isset($A['del_logo']) && !empty($this->getLogoImage())) {
                $this->deleteImage();
            }
        }
        $values = array(
            'name' => $this->getName(),
            'company' => $this->getCompany(),
            'address1' => $this->getAddress1(),
            'address2' => $this->getAddress2(),
            'city' => $this->getCity(),
            'state' => $this->getState(),
            'country' => $this->getCountry(),
            'phone' => $this->getPhone(),
            'zip' => $this->getPostal(),
            'dscp' => $this->getDscp(),
            'is_supplier' => $this->getIsSupplier(),
            'is_brand' => $this->getIsBrand(),
            'lead_time' => $this->getLeadTime(),
            'logo_image' => $this->getLogoImage(),
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
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
        );
        $db = Database::getInstance();
        try {
            if ($this->getID() > 0) {
                $types[] = Database::INTEGER;
                $db->conn->update(
                    $_TABLES['shop.suppliers'],
                    $values,
                    array('sup_id' => $this->getID()),
                    $types
                );
            } else {
                $db->conn->insert(
                    $_TABLES['shop.suppliers'],
                    $values,
                    $types
                );
                $this->setID($db->conn->lastInsertId());
            }
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return 0;
        }
        return $this->getID();
    }


    /**
     * Delete all information for a supplier.
     *
     * @param   integer $sup_id     Supplier ID
     */
    public static function deleteSupplier(int $sup_id) : void
    {
        global $_TABLES;

        $sup_id = (int)$sup_id;
        try {
            Database::getInstance()->conn->delete(
                $_TABLES['shop.suppliers'],
                array('sup_id' => $sup_id),
                array(Database::INTEGER)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Get the URL to the brand logo.
     * Returns an empty string if no image defined or found.
     *
     * @return  string  URL of image, empty string if file not found
     */
    public function getImage()
    {
        return Images\Supplier::getUrl($this->getLogoImage());
    }


    /**
     * Creates the address edit form.
     * Pre-fills values from another address if supplied
     *
     * @param   string  $type   Address type (billing or shipping)
     * @param   array   $A      Optional values to pre-fill form
     * @param   integer $step   Current step number
     * @return  string          HTML for edit form
     */
    public function Edit() : string
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP;

        $T = new Template('admin');
        $T->set_file('form', 'supplier_form.thtml');
        $tpl_var = $_SHOP_CONF['pi_name'] . '_entry';
        switch (PLG_getEditorType()) {
        case 'ckeditor':
            $T->set_var('show_htmleditor', true);
            PLG_requestEditor($_SHOP_CONF['pi_name'], $tpl_var, 'ckeditor_shop.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        case 'tinymce' :
            $T->set_var('show_htmleditor',true);
            PLG_requestEditor($_SHOP_CONF['pi_name'], $tpl_var, 'tinymce_shop.thtml');
            PLG_templateSetVars($tpl_var, $T);
            break;
        default :
            // don't support others right now
            $T->set_var('show_htmleditor', false);
            break;
        }
        $state_options = State::optionList(
            $this->getCountry(),
            $this->getState()
        );
        $T->set_var(array(
            'entry_id'  => $this->getID(),
            'name'      => $this->getName(),
            'company'   => $this->getCompany(),
            'address1'  => $this->getAddress1(),
            'address2'  => $this->getAddress2(),
            'city'      => $this->getCity(),
            //'state'     => $this->getState(),
            'zip'       => $this->getPostal(),
            //'country'   => $this->getCountry(),
            'phone'     => $this->getPhone(),
            'brand_chk' => $this->getIsBrand() ? 'checked="checked"' : '',
            'supplier_chk' => $this->getIsSupplier() ? 'checked="checked"' : '',
            'doc_url'   => SHOP_getDocURL('supplier_form'),
            'logo_img'  => $this->getImage()['url'],
            'dscp'      => $this->getDscp(),
            'country_options' => Country::optionList($this->getCountry()),
            'state_options' => $state_options,
            'state_sel_vis' => strlen($state_options) > 0 ? '' : 'none',
            'lead_time' => $this->getLeadTime(),
        ) );
        $T->parse('output','form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Supplier/Brand admin list view.
     *
     * @param   integer $uid    Optional user ID (not used here)
     * @return  string      HTML for the list.
     */
    public static function adminList(?int $uid=NULL) : string
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN;

        $header_arr = array(
            array(
                'text' => $LANG_ADMIN['edit'],
                'field' => 'edit',
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['logo_img'],
                'field' => 'logo',
                'align' => 'center',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['name'],
                'field' => 'name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_SHOP['company'],
                'field' => 'company',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['city'],
                'field' => 'city',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['state'],
                'field' => 'state',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['country'],
                'field' => 'country',
                'sort' => true,
            ),
            array(
                'text' => $LANG_SHOP['supplier'],
                'field' => 'is_supplier',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_SHOP['brand'],
                'field' => 'is_brand',
                'sort' => true,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'company',
            'direction' => 'ASC',
        );

        $display = COM_startBlock(
            '', '',
            COM_getBlockTemplate('_admin_block', 'header')
        );

        $query_arr = array(
            'table' => 'shop.suppliers',
            'sql' => "SELECT * FROM {$_TABLES['shop.suppliers']}",
            'query_fields' => array(),
            'default_filter' => '',
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => SHOP_ADMIN_URL . '/index.php?suppliers',
        );

        $display .= '<div>' . FieldList::buttonLink(array(
            'text' => $LANG_SHOP['new_item'],
            'url' => SHOP_ADMIN_URL . '/index.php?edit_sup=0',
            'style' => 'success',
        ) ) . '</div>';
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_supplierlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', '', '', ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the Suppliers admin list.
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
                'url' => SHOP_ADMIN_URL . '/index.php?edit_sup=' . $A['sup_id'],
            ) );
            break;

        case 'logo':
            $url = Images\Supplier::getUrl($A['logo_image'])['url'];
            if ($url != '') {
                $retval = COM_createImage($url, '', array(
                    'class' => 'shopLogoImage small',
                ) );
            }
            break;
        case 'delete':
            $retval = FieldList::delete(array(
                'delete_url' => SHOP_ADMIN_URL . '/index.php?del_sup&id=' . $A['sup_id'],
                'attr' => array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                ),
            ) );
            break;

        case 'is_supplier':
        case 'is_brand':
            $retval .= FieldList::checkbox(array(
                'name' => $fieldname . '_check',
                'checked' => $fieldvalue == 1,
                'id' => "tog{$fieldname}{$A['sup_id']}",
                'onclick' => "SHOP_toggle(this,'{$A['sup_id']}','$fieldname','supplier');",
            ) );
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }
        return $retval;
    }

}
