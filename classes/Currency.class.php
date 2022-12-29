<?php
/**
 * Class to handle currency display.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2014-2022 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.6.1
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop;
use glFusion\Database\Database;


/**
 * Class to handle currencies.
 * @package shop
 */
class Currency
{
    /** Currency code.
     * @var string */
    private $code = '';

    /** Currency symbol.
     * @var string */
    private $symbol = '';

    /** Currency full name.
     * @var string */
    private $name = '';

    /** Symbol placement, either `before` or `after`.
     * @var string */
    private $symbol_placement = 'before';

    /** Space character between symbol and currency amount.
     * @var string */
    private $symbol_spacer = '';

    /** Code placement when shown, either `before` or `after`.
     * @var string */
    private $code_placement = 'after';

    /** Character used to separate thousands.
     * @var string */
    private $thousands_sep = ',';

    /** Character used as a decimal point. Some use a comma.
     * @var string */
    private $decimal_sep = '.';

    /** Major currency unit, e.g. `dollars`.
     * @var string */
    private $major_unit = '';

    /** Minor currency unit, e.g. `cents`.
     * @var string */
    private $minor_unit = '';

    /** Timestamp when currency was last converted.
     * Date-time string.
     * @var string */
    private $conversion_ts = '';

    /** ISO numeric currency code.
     * @var integer */
    private $numeric_code = 0;

    /** Number of decimal places.
     * @var integer */
    private $decimals = 2;

    /** Rounding of the minor unit with a currency specific step size.
     * For example, Swiss Francs are rounded using a step size of 0.05.
     * This means a price of 10.93 is converted to 10.95.
     * @var float */
    private $rounding_step = .01;

    /** Conversion_rate. Not used.
     * @var float */
    private $conversion_rate = 0;


    /**
     * Constructor. Loads a currency from the database.
     *
     * @param   mixed   $code   Currency code, or DB record as an array
     */
    public function __construct(?string $code = NULL)
    {
        global $_SHOP_CONF, $_TABLES;

        if (empty($code)) {
            $code = $_SHOP_CONF['currency'];
        }
        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['shop.currency']} WHERE code = ?",
                array($code),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars($row);
        }
    }


    /**
     * Create a Currency object from a raw array, e.g. DB record.
     *
     * @param   array   $A      Array of data
     * @return  object      Currency object
     */
    public static function fromArray(array $A) : self
    {
        $retval = new self;
        $retval->setVars($A);
        return $retval;
    }


    /**
     * Get an instance of a currency.
     * Caches in a static variable for quick repeated retrivals,
     * and also caches using glFusion caching if available.
     *
     * @param   string  $code   Currency Code
     * @return  object      Currency Object
     */
    public static function getInstance(?string $code=NULL) : self
    {
        global $_SHOP_CONF;
        static $currencies = array();
        if (empty($code)) {
            $code = $_SHOP_CONF['currency'];
        }

        if (!isset($currencies[$code])) {
            $key = 'currency_' . $code;
            $currencies[$code] = Cache::get($key);
            if (!$currencies[$code]) {
                $currencies[$code] = new self($code);
                Cache::set($key, $currencies[$code]);
            }
        }
        return $currencies[$code];
    }


    /**
     * Return the string value of this object.
     *
     * @return  string  String value (currency code)
     */
    public function __toString() : string
    {
        return $this->code;
    }


    /**
     * Set all the record values into properties.
     *
     * @param   array   $A      Array of key->value pairs
     * @return  object  $this
     */
    public function setVars(array $A) : self
    {
        $this->code = $A['code'];
        $this->symbol = $A['symbol'];
        $this->name = $A['name'];
        $this->numeric_code = (int)$A['numeric_code'];
        $this->symbol_placement = $A['symbol_placement'];
        $this->symbol_spacer = str_replace(' ', '&nbsp;', $A['symbol_spacer']);
        $this->code_placement = $A['code_placement'];
        $this->decimals = (int)$A['decimals'];
        $this->rounding_step = (float)$A['rounding_step'];
        $this->thousands_sep = $A['thousands_sep'];
        $this->decimal_sep = $A['decimal_sep'];
        $this->major_unit = $A['major_unit'];
        $this->minor_unit = $A['minor_unit'];
        $this->conversion_rate = $A['conversion_rate'];
        $this->conversion_ts = $A['conversion_ts'];
        return $this;
    }


    /**
     * Get the currency code value.
     *
     * @return  string      Currency code
     */
    public function getCode() : string
    {
        return $this->code;
    }


    /**
     * Get the currency name.
     *
     * @return  string      Full name of currency
     */
    public function getName() : string
    {
        return $this->name;
    }


    /**
     * Return the number of decimal places associated with a currency.
     *
     * @return  integer     Number of decimal places used for the currency
     */
    public function Decimals() : int
    {
        return (int)$this->decimals;
    }


    /**
     * Return the prefix, if any, for a currency.
     *
     * @return  string      Prefix, e.g. dollar sign
     */
    public function Pre() : string
    {
        static $prefixes = array();
        if (!isset($prefixes[$this->code])) {
            $prefix = '';
            if ($this->symbol_placement == 'before') {
                $prefix .= $this->symbol . $this->symbol_spacer;
            }
            if ($this->code_placement == 'before') {
                $prefix .= $this->code . $this->symbol_spacer;
            }
            $prefixes[$this->code] = $prefix;
        }
        return $prefixes[$this->code];
    }


    /**
     * Return the postfix, if any, for a currency.
     *
     * @return  string      Postfix, e.g. Euro sign
     */
    public function Post() : string
    {
        static $postfixes = array();
        if (!isset($postfixes[$this->code])) {
            $postfix = '';
            if ($this->symbol_placement == 'after') {
                $postfix .= $this->symbol . $this->symbol_spacer;
            }

            if ($this->code_placement == 'after') {
                $postfix .= $this->code . $this->symbol_spacer;
            }
            $postfixes[$this->code] = $postfix;
        }
        return $postfixes[$this->code];
    }


    /**
     * Get the formatted string for an amount, e.g. "$ 125.00".
     *
     * @param   float   $amount Dollar amount
     * @param   boolean $symbol True to format as "$1.00", False for "1.00 USD"
     * @return  string      Formatted string for display
     */
    public function Format($amount, bool $symbol = true) : string
    {
        $val = $this->_Format((float)$amount);
        if ($symbol) {
            return $val[0] . $val[1] . $val[2];
        } else {
            return $val[1] . $this->code;
        }
    }


    /**
     * Get just the numeric part of the formatted price, e.g. "125.00" for "125".
     *
     * @param   float   $amount Dollar amount
     * @return  string      Formatted numeric value
     */
    public function FormatValue(string $amount) : string
    {
        $val = $this->_Format((float)$amount);
        return $val[1];
    }


    /**
     * Formats a price for a particular currency.
     *
     * @param   float   $amount A numeric price amount value.
     * @return  array   Array of prefix, number, postfix
     */
    private function _Format(float $amount) : array
    {
        static $amounts = array();

        $key = $this->code . (string)$amount;
        $amount = (float)$amount;
        if (!array_key_exists($key, $amounts)) {
            // Format the price as a number.
            $price = number_format(
                $this->RoundVal(abs($amount)),
                $this->decimals,
                $this->decimal_sep,
                $this->thousands_sep
            );
            if ($amount < 0 && $price != 0) {
                $negative = '-';
            } else {
                $negative = '';
            }
            $formatted = array($this->Pre(), $negative.$price, $this->Post());
            $amounts[$key] = $formatted;
        }
        return $amounts[$key];
    }


    /**
     * Rounds a price amount for the specified currency.
     *
     * Rounding of the minor unit with a currency specific step size. For example,
     * Swiss Francs are rounded using a step size of 0.05. This means a price of
     * 10.93 is converted to 10.95.
     *
     * @param   float   $amount The numeric amount value of the price to be rounded.
     * @return  string          The rounded numeric amount value for the price.
     */
    public function RoundVal(float $amount) : float
    {
        if ($this->rounding_step < .01) {
            return round($amount, $this->decimals);
        }
        $modifier = 1 / $this->rounding_step;
        return round($amount * $modifier) / $modifier;
    }


    /**
     * Round a value to the correct number of decimals by always rounding up.
     * Used in the IPN processor to be conservative when giving credit for
     * discounts.
     *
     * @param   float   $amount Original value
     * @return  float       Rounded value
     */
    public function RoundUp(float $amount) : float
    {
        $fig = pow(10, $this->decimals);
        return (ceil($amount * $fig) / $fig);
    }


    /**
     * Converts a price amount from the current currency to the target currency.
     *
     * To convert an amount from one currency to another, we simply take the amount
     * value and multiply it by the current currency's conversion rate divided by
     * the target currency's conversion rate.
     *
     * @param   float   $amount         The numeric value to be converted
     * @param   string  $toCurrency     Target currency code
     * @param   string  $fromCurrency   Source currency override
     * @return  float       The converted amount
     */
    public function Convert(float $amount, string $toCurrency, string $fromCurrency='') : float
    {
        $retval = $amount * self::getConversionRate($toCurrency, $fromCurrency);
        return self::getInstance($toCurrency)->RoundVal($retval);
    }


    /**
     * Get the conversion rate between currencies.
     * If $from is not specified, uses the current default. $to must be given.
     *
     * @param   string  $toCurrency     Destination currency code
     * @param   string  $fromCurrency   Starting currency code
     * @return  float       Conversion rate to get $from to $to
     */
    public static function getConversionRate(string $toCurrency, string $fromCurrency='') : float
    {
        global $_SHOP_CONF;
        static $rates = array();

        if (empty($fromCurrency)) {
            $fromCurrency = $_SHOP_CONF['currency'];
        }
        if (!isset($rates[$fromCurrency])) $rates[$fromCurrency] = array();

        // check if this conversion has already been done this session
        if (isset($rates[$fromCurrency][$toCurrency])) {
            $rate = $rates[$fromCurrency][$toCurrency];
        } else {
            $fldname = "{$fromCurrency}_{$toCurrency}";
            $cache_key = 'curr_conv_' . $fldname;
            $rate = Cache::get($cache_key);
            $rate = null;
            if ($rate === NULL) {
                $rate = 1;      // default
                // It's faster to get the whole latest array
                //$url = "https://api.exchangerate.host/latest?base=$fromCurrency";
                // ... or maybe not
                $url = "https://api.exchangerate.host/convert?from=$fromCurrency&to=$toCurrency";
                $ch = curl_init();
                curl_setopt ($ch, CURLOPT_URL, $url);
                curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt ($ch, CURLOPT_USERAGENT,
                     "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
                curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 0);
                $data = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($data, true);
                if (isset($data['success']) && $data['success']) {
                    if (isset($data['info']) && isset($data['info']['rate'])) {
                        $rate = $data['info']['rate'];
                    }
                }
                /*
                $url = "https://free.currconv.com/api/v7/convert?q={$fldname}&compact=ultra&apiKey=" . Config::get('curconv_apikey');
                $ch = curl_init();
                curl_setopt ($ch, CURLOPT_URL, $url);
                curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt ($ch, CURLOPT_USERAGENT,
                     "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)");
                curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 0);
                $data = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($data);
                $rate = $data->$fldname;
*/
                // Cache for an hour
                Cache::set($cache_key, $rate, 'currency', 3600);
            }
            $rates[$fromCurrency][$toCurrency] = $rate;
        }
        return $rate;
    }


    /**
     * Get all currency info.
     * Used by the plugin configuration to create a dropdown list of currencies.
     *
     * @return  array   Array of all DB records
     */
    public static function getAll() : array
    {
        global $_TABLES;

        $currencies = Cache::get('shop.currencies');
        if ($currencies === NULL) {
            $currencies = array();
            try {
                $stmt = Database::getInstance()->conn->executeQuery(
                    "SELECT * FROM {$_TABLES['shop.currency']} ORDER BY code ASC"
                );
            } catch (\Throwable $e) {
                Log::system(Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $stmt = false;
            }
            if ($stmt) {
                while ($A = $stmt->fetchAssociative()) {
                    $currencies[$A['code']] = self::fromArray($A);
                }
                Cache::set('shop.currencies', $currencies, 'currency', 86400);
            }
        }
        return $currencies;
    }


    /**
     * Convert an amount to an integer based on the number of decimals.
     * Example: $1.95 US becomes 195, or 100 Yen remains 100.
     *
     * @param   float   $amount     Money amount to convert
     * @return  integer             Integer version of the amount
     */
    public function toInt(float $amount) : int
    {
        return round($amount * (10 ** $this->decimals), 0);
    }


    /**
     * Convert an amount to an integer based on the number of decimals.
     * Example: 195 USD becomes 1.95, and 100 Yen remains 100.
     *
     * @param   integer $intval     Integer version of the amount
     * @return  float               Money amount to convert
     */
    public function fromInt(int $intval) : float
    {
        return round($intval / (10 ** $this->decimals), $this->decimals);
    }


    /**
     * Create selection options for currency values.
     *
     * @param   string  $sel    Currently-selected currency code
     * @return  string      Option elements for a selection list
     */
    public static function optionList(string $sel = '') : string
    {
        $currencies = self::getAll();
        $retval = '';
        foreach ($currencies as $Cur) {
            $selected = $sel == $Cur->getCode() ? 'selected="selected"' : '';
            $retval .= "<option $selected value=\"{$Cur->getCode()}\">{$Cur->getCode()} - {$Cur->getName()}</option>";
        }
        return $retval;
    }


    /**
     * Format a money field using the default currency type.
     *
     * @param   float   $amt    Amount
     * @param   boolean $sign   True to show currency sign
     * @return  string  Formatted currency string
     */
    public static function formatMoney(float $amt, ?bool $sign=false) : string
    {
        static $Cur = NULL;
        if ($Cur === NULL) {
            $Cur = self::getInstance();
        }
        if ($sign) {
            return $Cur->Format((float)$amt);
        } else {
            return $Cur->FormatValue((float)$amt);
        }
    }

}

