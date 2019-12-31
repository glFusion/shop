<?php
/**
 * Class to manage product variants.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.1.0
 * @since       v1.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;

/**
 * Class for product variants.
 * Variants are combinations of options represented by a single sku, such as 
 * color, size and style.
 * @package shop
 */
class ProductVariant
{
    /** Variant record ID.
     * @var integer */
    private $pv_id;

    /** Product record ID.
     * @var integer */
    private $item_id;

    /** Variant description.
     * @var string */
    private $dscp;

    /** Price impact amount.
     * @var float */
    private $price;

    /** Weight impact.
     * @var float */
    private $weight;

    /** Shipping Units impact.
     * @var float */
    private $shipping_units;

    /** Variant SKU.
     * @var string */
    private $sku;

    /** Quantity on hand.
     * @var float */
    private $onhand;

    /** OptionValue items associated with this variant.
     * @var array */
    private $Options = NULL;

    /**
     * Constructor.
     * Initializes the variant variables
     *
     * @param   integer $pv_id  Variant record ID
     * @uses    self::Load()
     */
    public function __construct($pv_id = 0)
    {
        if (is_numeric($pv_id) && $pv_id > 0) {
            // Got an item ID, read from the DB
            $status = $this->Read($pv_id);
            if (!$status) {
                $this->pv_id = 0;
            }
        } elseif (is_array($pv_id) && isset($pv_id['pv_id'])) {
            // Got an item record, just set the variables
            $this->setVars($pv_id);
        }
    }


    /**
     * Get an instance of a specific variant record.
     *
     * @param   integer $pv Record ID to retrieve
     * @return  object      ProductVariant object
     */
    public static function getInstance($pv)
    {
        static $items = array();
        if (is_array($pv)) {
            $pv_id = $pv['pv_id'];
        } else {
            $pv_id = $pv;
        }

        if (!array_key_exists($pv_id, $items)) {
            $items[$pv_id] = new self($pv);
        }
        return $items[$pv_id];
    }


    /**
    * Load the item information.
    *
    * @param    integer $rec_id     DB record ID of item
    * @return   boolean     True on success, False on failure
    */
    public function Read($rec_id)
    {
        global $_SHOP_CONF, $_TABLES;

        $rec_id = (int)$rec_id;
        $sql = "SELECT * FROM {$_TABLES['shop.product_variants']}
                WHERE pv_id = $rec_id";
        //echo $sql;die;
        $res = DB_query($sql);
        if ($res) {
            $this->setVars(DB_fetchArray($res, false));
            $this->loadOptions();
            $this->makeDscp();
            return true;
        } else {
            return false;
        }
    }


    /**
     * Set the object variables from an array.
     *
     * @param   array   $A      Array of values
     * @return  boolean     True on success, False if $A is not an array
     */
    public function setVars($A)
    {
        if (is_array($A)) {
            $this
                ->setId(SHOP_getVar($A, 'pv_id', 'integer'))
                ->setItemId(SHOP_getVar($A, 'item_id'))
                ->setPrice(SHOP_getVar($A, 'price', 'float'))
                ->setWeight(SHOP_getVar($A, 'weight', 'float'))
                ->setShippingUnits(SHOP_getVar($A, 'shipping_units', 'float'))
                ->setSku(SHOP_getVar($A, 'sku'))
                ->setOnhand(SHOP_getVar($A, 'onhand', 'float'));
            if (isset($A['dscp'])) {        // won't be set from the edit form
                $this->setDscp($A['dscp']);
            }
        }
        return $this;
    }


    /**
     * Get the product variant record that has all the requested attributes.
     *
     * @param   integer $item_id    Product ID
     * @param   array   $attribs    Array of OptionValue IDs
     * @return  object  Matching ProductVariant object
     */
    public static function getByAttributes($item_id, $attribs)
    {
        global $_TABLES;

        $item_id = (int)$item_id;
        $count = count($attribs);
        $attr_sql = implode(',', $attribs);
        $sql = "SELECT vxo.pv_id FROM {$_TABLES['shop.variantXopt']} vxo
            INNER JOIN {$_TABLES['shop.product_variants']} pv
                ON vxo.pv_id = pv.pv_id
            WHERE vxo.pov_id IN ($attr_sql) AND pv.item_id = $item_id
            GROUP BY pv.pv_id
            HAVING COUNT(pv.item_id) = $count
            LIMIT 1";
        //echo $sql;
        $res = DB_query($sql);
        if ($res) {
            $A = DB_fetchArray($res, false);
            return self::getInstance($A['pv_id']);
        } else {
            return new Self;
        }
    }


    /**
     * Get the option selections for a product's variants.
     *
     * @deprecated
     * @param   integer $item_id    Product record ID
     */
    public static function getSelections($item_id)
    {
        $sql = "SELECT pov.* FROM {$_TABLES['shop.product_var_opts']} pov
            INNER JOIN {$_TABLES['shop.variantXopt']} vxo
                ON pov.pov_id = vxo.pov_id
            INNER JOIN {$_TABLES['shop.product_variants']} pv
                ON pv.pv_id = vxo.pv_id
            INNER JOIN {$_TABLES['shop.product_option_groups']} pog
                ON pog.pog_id = pov.pog_id
            WHERE pv.item_id = $item_id
            ORDER BY pog.pog_orderby asc";
    }


    /**
     * Load the product attributs into the options array.
     *
     * @return  object  $this
     */
    private function loadOptions()
    {
        global $_TABLES;

        if ($this->Options === NULL) {
            $this->Options = array();
            $sql = "SELECT pov.*, pog.pog_name FROM {$_TABLES['shop.prod_opt_vals']} pov
                INNER JOIN {$_TABLES['shop.variantXopt']} vx
                    ON vx.pov_id = pov.pov_id
                INNER JOIN {$_TABLES['shop.prod_opt_grps']} pog
                    ON pog.pog_id = pov.pog_id
                WHERE vx.pv_id = {$this->pv_id}
                ORDER BY pog.pog_orderby ASC";
            $res = DB_query($sql);
            while ($A = DB_fetchArray($res, false)) {
                $this->Options[$A['pog_name']] = new ProductOptionValue($A);
            }
        }
        return $this;
    }


    /**
     * Set the record ID property.
     *
     * @param   integer $rec_id     Record ID
     * @return  object  $this
     */
    public function setId($rec_id)
    {
        $this->pv_id = (int)$rec_id;
        return $this;
    }


    /**
     * Set the product ID.
     *
     * @param   integer $rec_id     Record ID
     * @return  object  $this
     */
    public function setItemId($rec_id)
    {
        $this->item_id = (int)$rec_id;
        return $this;
    }


    /**
     * Set the description field.
     *
     * @param   array|string    $dscp   Array or JSON string
     * @return  object      $this
     */
    public function setDscp($dscp)
    {
        if (is_array($dscp)) {
            $this->dscp = $dscp;
        } else {
            $this->dscp = json_decode($dscp,true);
        }
        return $this;
    }


    /**
     * Set the variant price impact.
     *
     * @param   float   $price      Price impact
     * @return  object  $this
     */
    public function setPrice($price)
    {
        $this->price = (float)$price;
        return $this;
    }


    /**
     * Set the variant weight impact.
     *
     * @param   float   $weight     Weight impact in KG or LB
     * @return  object  $this
     */
    public function setWeight($weight)
    {
        $this->weight = (float)$weight;
        return $this;
    }


    /**
     * Set the variant shipping unit impact
     *
     * @param   float   $units      Additional shipping units for this variant
     * @return  object  $this
     */
    public function setShippingUnits($units)
    {
        $this->units= (float)$units;
        return $this;
    }


    /**
     * Set the quantity on hand.
     *
     * @param   float   $onhand     Number of units on hand
     * @return  object  $this
     */
    public function setOnhand($onhand)
    {
        $this->onhand = (float)$onhand;
        return $this;
    }


    /**
     * Get the quantity on hand for this variant.
     *
     * @return  float       Quantity onhand
     */
    public function getOnhand()
    {
        return (float)$this->onhand;
    }


    /**
     * Set the SKU field.
     *
     * @param   string  $sku        SKU for this variant
     * @return  object  $this
     */
    public function setSku($sku)
    {
        $this->sku = $sku;
        return $this;
    }


    /**
     * Get the SKU value.
     *
     * @return  string      SKU for this variant
     */
    public function getSku()
    {
        return $this->sku;
    }


    /**
     * Get all variants related to a given productd.
     *
     * @param   integer $product_id     Product record ID
     * @return  array       Array of ProductVariant objects
     */
    public static function getByProduct($product_id)
    {
        global $_TABLES;

        $retval = array();
        $product_id = (int)$product_id;
        $sql = "SELECT * FROM {$_TABLES['shop.product_variants']}
            WHERE item_id = '$product_id'";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[] = self::getInstance($A);
        }
        return $retval;
    }


    /**
     * Get the description array elements.
     *
     * @return  string      Item description
     */
    public function getDscp()
    {
        return $this->dscp;
    }


    /**
     * Get a multi-line version of the description for HTML display.
     *
     * @return  string      HTML version of the description
     */
    public function getDscpHTML()
    {
        $retval = '';
        foreach ($this->dscp as $dscp) {
            $retval .= " -- {$dscp['name']}: {$dscp['value']}<br />\n";
        }
        return $retval;
    }


    /**
     * Convert a json string from the DB directly to a string description.
     *
     * @param   array|string    Array of elements or JSON string
     * @return  string      One-line description
     */
    private static function jsonToString($json)
    {
        $retval = array();
        $json = json_decode($json,true);
        if (!is_array($json)) {
            return '';
        }
        foreach ($json as $dscp) {
            $retval[] = "{$dscp['name']}:{$dscp['value']}";
        }
        $retval = implode(', ', $retval);
        return $retval;
    }


    /**
     * Get the description as a comma separated string.
     * name:value, name.value, name.value ...
     *
     * @return  string      One-line description
     */
    public function getDscpString()
    {
        foreach ($this->dscp as $dscp) {
            $retval[] = "{$dscp['name']}:{$dscp['value']}";
        }
        $retval = implode(', ', $retval);
        return $retval;
    }


    /**
     * Get the decriptive elements from the option names and values.
     * Sets the local dscp property
     *
     * @return  object  $this
     */
    private function makeDscp()
    {
        $this->dscp = array();
        foreach ($this->Options as $name=>$POV) {
            $this->dscp[] = array(
                'name' => $name,
                'value' => $POV->getValue(),
            );
        }
        return $this;
    }


    /**
     * Creates the edit form for an existing variant.
     * The component options are set during creation along with calculated
     * values for sku and price/shipping/weight impacts unless values are set
     * for these values.
     *
     * @param   integer $item_id    Product record ID
     * @return  string      HTML for edit form
     */
    public function Create($item_id)
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('form', 'variant_new.thtml');

        $T->set_var(array(
            'doc_url'       => SHOP_getDocURL('variant_form', $_CONF['language']),
            'item_id'       => $item_id,
            'item_name'     => Product::getByID($item_id)->name,
            'pv_id'         => $this->getId(),
            'price'         => $this->getPrice(),
            'weight'        => $this->getWeight(),
            'onhand'        => $this->getOnhand(),
            'shipping_units' => $this->getShippingUnits(),
            'sku'           => $this->getSku(),
            //'ena_chk'       => $this->enabled == 1 ? ' checked="checked"' : '',
        ) );
        $Groups = ProductOptionGroup::getAll();
        $T->set_block('form', 'OptionGroups', 'Grps');
        foreach ($Groups as $gid=>$Grp) {
            $T->set_var(array(
                'pog_id'    => $gid,
                'pog_name'  => $Grp->getName(),
                'val_select_opts' => COM_optionList(
                    $_TABLES['shop.prod_opt_vals'],
                    'pov_id,pov_value',
                    '',
                    1,
                    "pog_id = '$gid'"
                ),
            ) );
            $T->parse('Grps', 'OptionGroups', true);
        }
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }


    /**
     * Creates the edit form for an existing variant.
     * Editing allows changes to price, weight, shipping impacts and sku.
     * Changes to the component options is not allowed.
     *
     * @param   integer $id Optional ID, current record used if zero
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF, $_SHOP_CONF, $LANG_SHOP, $_SYSTEM;

        $T = new \Template(__DIR__ . '/../templates');
        $T->set_file('form', 'variant_edit.thtml');

        $T->set_var(array(
            'action_url'    => SHOP_ADMIN_URL,
            'pi_url'        => SHOP_URL,
            'doc_url'       => SHOP_getDocURL('variant_form', $_CONF['language']),
            'item_id'       => $this->getItemId(),
            'item_name'     => Product::getByID($this->item_id)->name,
            'pv_id'         => $this->getId(),
            'price'         => $this->getPrice(),
            'weight'        => $this->getWeight(),
            'onhand'        => $this->getOnhand(),
            'shipping_units' => $this->getShippingUnits(),
            'sku'           => $this->getSku(),
            'dscp'          => $this->getDscpString(),
            //'ena_chk'       => $this->enabled == 1 ? ' checked="checked"' : '',
        ) );
        $retval .= $T->parse('output', 'form');
        $retval .= COM_endBlock();
        return $retval;
    }



    /**
     * Save a new product variant.
     *
     * @param   array   $A      Form values
     * @return  boolean     True on success, False on failure
     */
    public static function saveNew($A)
    {
        global $_TABLES;

        $item_id = (int)$A['item_id'];
        if ($item_id < 1) {
            return false;
        }
        $price = 0;
        $weight = 0;
        $shipping = 0;
        $sku_parts = array();
        $dscp = array();
        $opt_ids = array();
        foreach($A['groups'] as $name=>$pov_id) {
            if ($pov_id == 0) {
                continue;
            }
            $opt_ids[] = $pov_id;   // save for the variant->opt table
            $Opt = new ProductOptionValue($pov_id);

            if ($A['price'] === '') {   // Zero is valid
                $price += $Opt->getPrice();
            }
            $dscp[] = array(
                'name' => $name,
                'value' => $Opt->getValue(),
            );
            if (empty($A['sku'])) {
                if ($Opt->getSku() != '') {
                    $sku_parts[] = $Opt->getSku();
                }
            }
        }
        if (empty($A['sku'])) {
            $P = Product::getInstance($item_id);
            if (!empty($sku_parts)) {
                $sku = $P->getName() . '-' . implode('-', $sku_parts);
            }
        } else {
            $sku = $A['sku'];
        }
        if ($A['price'] !== '') {
            $price = $A['price'];
        }
        $args = array(
            'pv_id' => 0,
            'item_id' => $item_id,
            'dscp' => json_encode($dscp),
            'price' => $price,
            'sku' => $sku,
            'weight' => (float)$A['weight'],
            'shipping_units' => (float)$A['shipping'],
            'onhand' => (float)$A['onhand'],
        );
        $Var = new self($args);
        $Var->Save();
        $vals = array();
        foreach ($opt_ids as $opt_id) {
            $vals[] = '(' . $Var->getID() . ',' . $opt_id . ')';
        }
        $sql_vals = implode(',', $vals);
        $sql = "INSERT IGNORE INTO {$_TABLES['shop.variantXopt']}
            (pv_id, pov_id) VALUES $sql_vals";
        DB_query($sql);
    }


    /**
     * Save a variant to the database.
     *
     * @param   array   $A  Optional array of data to save
     * @return  boolean     True on success, False on DB error
     */
    public function Save($A= NULL)
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }

        if (empty($this->dscp)) {
            $this->makeDscp();
        }

        if ($this->pv_id > 0) {
            $sql1 = "UPDATE {$_TABLES['shop.product_variants']} ";
            $sql3 = " WHERE pv_id = '{$this->pv_id}'";
        } else {
            $sql1 = "INSERT INTO {$_TABLES['shop.product_variants']} ";
            $sql3 = '';
        }

        $sql2 = "SET item_id = '" . (int)$this->item_id . "',
                sku = '" . DB_escapeString($this->sku) . "',
                price = '" . (float)$this->price . "',
                weight = '" . (float)$this->weight . "',
                shipping_units = '" . (float)$this->shipping_units . "',
                onhand = " . (float)$this->onhand . ",
                dscp = '" . DB_escapeString(json_encode($this->dscp)) . "'";
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        SHOP_log($sql, SHOP_LOG_DEBUG);
        DB_query($sql);
        if (!DB_error()) {
            if ($this->pv_id == 0) {
                $this->pv_id = DB_insertID();
            }
            return true;
        } else {
            return false;
        }
    }


    /**
     * Delete a product variant.
     * TODO: not implemented, need to determine effects on existing orders.
     *
     * @param   integer $id     Variant record ID
     */
    public static function Delete($id)
    {
        global $_TABLES;

        DB_delete($_TABLES['shop.product_variants'], 'pv_id', (int)$pv_id);
    }


    /**
     * Just return the price property.
     *
     * @return  float       Item Price impact
     */
    public function getPrice()
    {
        return (float)$this->price;
    }


    /**
     * Just return the weithg property.
     *
     * @return  float       Weight impact
     */
    public function getWeight()
    {
        return (float)$this->weight;
    }


    /**
     * Just return the shipping units property.
     *
     * @return  float       Shipping Units impact
     */
    public function getShippingUnits()
    {
        return (float)$this->shipping_units;
    }


    /**
     * Get the product ID for this order item.
     *
     * @return  string      Product ID
     */
    public function getItemId()
    {
        return $this->item_id;
    }


    /**
     * Get the database record ID of this item.
     *
     * @return  integer     DB record ID
     */
    public function getID()
    {
        return $this->pv_id;
    }


    /**
     * Set the net price for the item.
     *
     * @param   float   $price  New net price
     * @return  object  $this
     */
    public function setNetPrice($price)
    {
        $this->net_price = $price;
        return $this;
    }


    /**
     * Product Variant List View.
     *
     * @param   integer $prod_id    Optional product ID to limit listing
     * @return  string      HTML for the attribute list.
     */
    public static function adminList($prod_id)
    {
        global $_CONF, $_SHOP_CONF, $_TABLES, $LANG_SHOP, $_USER, $LANG_ADMIN, $_SYSTEM;

        $prod_id = (int)$prod_id;
        $sql = "SELECT * FROM {$_TABLES['shop.product_variants']}";

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'pv_id',
            ),
            array(
                'text' => $LANG_SHOP['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            /*array(
                'text' => $LANG_SHOP['enabled'],
                'field' => 'enabled',
                'sort' => false,
            ),*/
            array(
                'text'  => 'SKU',
                'field' => 'sku',
                'sort'  => true,
            ),
            array(
                'text' => $LANG_SHOP['description'],
                'field' => 'dscp',
            ),
            array(
                'text' => $LANG_SHOP['opt_price'],
                'field' => 'price',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['shipping'],
                'field' => 'shipping_units',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_SHOP['onhand'],
                'field' => 'onhand',
                'align' => 'right',
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => 'false',
                'align' => 'center',
            ),
        );

        $defsort_arr = array(
            'field' => 'sku',
            'direction' => 'ASC',
        );
        $display = COM_startBlock('', '', COM_getBlockTemplate('_admin_block', 'header'));
        $display .= COM_createLink($LANG_SHOP['new_opt'],
            SHOP_ADMIN_URL . '/index.php?pv_edit=0&item_id=' . $prod_id,
            array(
                'style' => 'float:left;',
                'class' => 'uk-button uk-button-success',
            )
        );
        $query_arr = array(
            'table' => 'shop.product_variants',
            'sql' => $sql,
            'query_fields' => array('dscp', 'sku'),
            //'default_filter' => $def_filter,
        );

        /*if ($prod_id == -1) {
        $filter = "{$LANG_SHOP['product']}: <select name=\"product_id\"
            onchange=\"this.form.submit();\">
            <option value=\"0\">-- {$LANG_SHOP['any']} --</option>\n" .
            COM_optionList($_TABLES['shop.products'], 'id, name', $sel_prod_id) .
            "</select>&nbsp;\n";
        $text_arr = array(
            'has_extras' => true,
            'form_url' => SHOP_ADMIN_URL . '/index.php?options=x',
        );
        $options = array('chkdelete' => true, 'chkfield' => 'pv_id');
        } else {*/
            $text_arr = array();
            $filter = '';
            $options = array();
            $query_arr['sql'] .= " WHERE item_id = '$prod_id'";
        //}
        $display .= ADMIN_list(
            $_SHOP_CONF['pi_name'] . '_pvlist',
            array(__CLASS__,  'getAdminField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            $filter, '', $options, ''
        );
        $display .= COM_endBlock(COM_getBlockTemplate('_admin_block', 'footer'));
        return $display;
    }


    /**
     * Get an individual field for the attribute list.
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

        switch($fieldname) {
        case 'edit':
            $retval .= COM_createLink(
                Icon::getHTML('edit', 'tooltip', array(
                    'title' => $LANG_ADMIN['edit'],
                ) ),
                SHOP_ADMIN_URL . "/index.php?pv_edit=x&amp;pv_id={$A['pv_id']}"
            );
            break;

        case 'dscp':
            if ($fieldvalue != '') {
                $retval = self::jsonToString($fieldvalue);
            }
            break;

        case 'enabled':
            if ($fieldvalue == '1') {
                $switch = ' checked="checked"';
                $enabled = 1;
            } else {
                $switch = '';
                $enabled = 0;
            }
            $retval .= "<input type=\"checkbox\" $switch value=\"1\" name=\"ena_check\"
                id=\"togenabled{$A['pv_id']}\"
                onclick='SHOP_toggle(this,\"{$A['pv_id']}\",\"enabled\",".
                "\"option\");' />" . LB;
            break;

        case 'delete':
            $retval .= COM_createLink(
                Icon::getHTML('delete'),
                SHOP_ADMIN_URL. '/index.php?pv_del=x&amp;opt_id=' . $A['pv_id'],
                array(
                    'onclick' => 'return confirm(\'' . $LANG_SHOP['q_del_item'] . '\');',
                    'title' => $LANG_SHOP['del_item'],
                    'class' => 'tooltip',
                )
            );
            break;

        case 'price':
            $retval = \Shop\Currency::getInstance()->FormatValue($fieldvalue);
            break;

        default:
            $retval = htmlspecialchars($fieldvalue, ENT_QUOTES, COM_getEncodingt());
            break;
        }

        return $retval;
    }


    /**
     * Verify that the product is available with the selected options.
     *
     * @param   array   $opts   Array of option value record IDs
     * @return  array       Array of status and message elements.
     */
    public function Validate($opts)
    {
        global $LANG_SHOP;

        $P = Product::getByID($this->item_id);
        if ($P->isNew) {
            $retval = array(
                'status'    => 9,
                'msg'       => 'Invalid',
            );
        } else {
            $retval = array(
                'status'    => 0,
                'msg'       => $this->onhand . ' ' . $LANG_SHOP['available'],
                'allowed'   => true,
            );
        }
        if ($P->getTrackOnhand()) {
            if ($this->onhand < $opts['quantity']) {
                if ($P->getOversell() == Product::OVERSELL_HIDE) {
                    $retval['status'] = 2;
                    $retval['msg'] = 'Not Available';
                    $retval['allowed'] = false;
                } else {
                    $retval['status'] = 1;
                    $retval['msg'] = 'Backordered';
                }
            }
        }
        return $retval;
    }


}

?>
