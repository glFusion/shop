<?php
/**
 * UPS shipper class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2021 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.4.1
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Shippers;
// For getTracking() and getQuote()
use \SimpleXmlElement;
use \Exception;
use Shop\Company;
use Shop\Models\ShippingQuote;
use Shop\Package;
use Shop\Log;
use Shop\Tracking;


/**
 * Carrier class for UPS.
 * @package Shop
 */
class ups extends \Shop\Shipper
{
    /** Tracking URL. Set to production or test values in constructor.
     * @var string */
    private $track_url = '';

    /** Rate Quote URL. Set to production or test values in constructor.
     * @var string */
    private $rate_url = '';

    /** Production Tracking URL.
     * @var string */
    private $track_url_prod = 'https://onlinetools.ups.com/ups.app/xml/Track';

    /** Test Tracking URL.
     * @var string */
    private $track_url_test = 'https://wwwcie.ups.com/ups.app/xml/Track';

    /** Production Rate URL.
     * @var string */
    private $rate_url_prod = 'https://onlinetools.ups.com/ups.app/xml/Rate';

    /** Test Rate URL.
     * @var string */
    private $rate_url_test = 'https://wwwcie.ups.com/ups.app/xml/Rate';

    /** UPS service codes and descriptions.
     * @var array */
    protected $svc_codes = array(
        '_na' => '-- Not Available--',
        '_fixed' => '-- Fixed Rate --',
        '01'    => 'Next Day Air',
        '02'    => '2nd Day Air',
        '03'    => 'Ground',
        '12'    => '3 Day Select',
        '13'    => 'Next Day Air Saver',
        '14'    => 'Next Day Air Early',
        '59'    => '2nd Day Air A.M.',
        '07'    => 'Worldwide Express',
        '08'    => 'Worldwide Expedited',
        '11'    => 'Worldwide Standard',
        '54'    => 'Worldwide Express Plus',
        '65'    => 'Worldwide Saver',
        '96'    => 'Worldwide Express Freight',
        '71'    => 'Worldwide Express Freight Midday',
    );

    protected $pkg_codes = array(
        '00'    => 'UNKNOWN',
        '01'    => 'UPS Letter',
        '02'    => 'Package',
        '03'    => 'Tube',
        '04'    => 'Pak',
        '21'    => 'Express Box',
        '24'    => '25KG Box',
        '25'    => '10KG Box',
        '30'    => 'Pallet',
        '2a'    => 'Small Express Box',
        '2b'    => 'Medium Express Box',
        '2c'    => 'Large Express Box',
    );

    protected $uom = array(
        'IN'    => 'IN',
        'CM'    => 'CM',
        'lbs'   => 'LBS',
        'kgs'   => 'KGS',
    );

    /**
     * Set up local variables and call the parent constructor.
     *
     * @param   mixed   $A      Optional data array or shipper ID
     */
    public function __construct($A = array())
    {
        $this->key = 'ups';
        $this->implementsTrackingAPI = true;
        $this->implementsQuoteAPI = true;
        $this->cfgFields = array(
            'userid' => 'password',
            'passwd' => 'password',
            'access_key' => 'password',
            'test_mode' => 'checkbox',
//            'services' => 'array',
        );
        parent::__construct($A);

        $services = $this->getConfig('services');
        if (is_array($services)) {
            $this->supported_services = $services;
        }

        if ($this->getConfig('test_mode')) {
            $this->rate_url = $this->rate_url_test;
            $this->track_url = $this->track_url_test;
        } else {
            $this->rate_url = $this->rate_url_prod;
            $this->track_url = $this->track_url_prod;
        }
        if (!$this->getConfig('ena_quotes')) {
            $this->implementsQuoteAPI = false;
        }
        if (!$this->getConfig('ena_tracking')) {
            $this->implementsTrackingAPI = false;
        }
    }


    /**
     * Get the shipper's name for display.
     *
     * @return  string  Shipper name
     */
    public static function getCarrierName()
    {
        return 'United Parcel Service';
    }


    /**
     * Get the package tracking URL for a given tracking number.a
     * This is used if the Tracking API is not used.
     *
     * @param   string  $track_num  Tracking number
     * @return  string  Package tracking URL
     */
    protected function _getTrackingUrl($track_num)
    {
        return "https://www.ups.com/track?tracknum={$track_num}";
    }


    /**
     * Get tracking data and return a Tracking object.
     *
     * @param   string  $tracking   Single tracking number
     * @return  object      Tracking object
     */
    public function getTracking(string $tracking) : Tracking
    {
        global $_CONF, $LANG_SHOP;

        // Attempt to get from cache
        $Tracking = Tracking::getCache($this->key, $tracking);
        if ($Tracking !== NULL) {
            return $Tracking;
        }

        $Tracking = new Tracking;
        if (!$this->hasValidConfig()) {
            $Tracking->addError('Invalid Configuration');
            return $Tracking;
        }

        try {
            // Create AccessRequest XMl
            $accessRequestXML = new SimpleXmlElement('<AccessRequest></AccessRequest>');
            $accessRequestXML->addChild("AccessLicenseNumber", $this->getConfig('access_key'));
            $accessRequestXML->addChild("UserId", $this->getConfig('userid'));
            $accessRequestXML->addChild("Password", $this->getConfig('passwd'));

            // Create TrackRequest XMl
            $trackRequestXML = new SimpleXMLElement ( "<TrackRequest></TrackRequest  >" );
            $request = $trackRequestXML->addChild ( 'Request' );
            $request->addChild ( "RequestAction", "Track" );
            $request->addChild ( "RequestOption", "activity" );

            $trackRequestXML->addChild ("TrackingNumber", $tracking);
            $requestXML = $accessRequestXML->asXML () . $trackRequestXML->asXML ();

            $form = array (
                'http' => array (
                    'method' => 'POST',
                    'header' => 'Content-type: application/x-www-form-urlencoded',
                    'content' => $requestXML,
                )
            );

            // get request
            $request = stream_context_create ($form);
            $browser = fopen($this->track_url, 'rb', false, $request);
            if (!$browser) {
                throw new Exception ( "Connection failed." );
            }

            // get response
            $response = stream_get_contents ( $browser );
            fclose ( $browser );

            /* Testing, use this instead of the above block
             * $response = file_get_contents(__DIR__ . '/../XOLTResult.xml');
             */

            if ($response == false) {
                //throw new Exception ( "Bad data." );
            } else {
                // save request and response to file
                /*$fw = fopen ( $outputFileName, 'w' );
                fwrite ( $fw, "Request: \n" . $requestXML . "\n" );
                fwrite ( $fw, "Response: \n" . $response . "\n" );
                fclose ( $fw );*/

                // get response status
                $resp = new SimpleXMLElement ($response);
                if ((string)$resp->Response->ResponseStatusCode == '0') {
                    $Tracking->addError($resp->Response->Error->ErrorCode . ': ' . $resp->Response->Error->ErrorDescription);
                    return $Tracking;
                }
                // Response is OK, populate the display
                $Tracking->addMeta($LANG_SHOP['tracking_num'], (string)$resp->Shipment->Package->TrackingNumber);
                $Tracking->addMeta($LANG_SHOP['carrier'], self::getCarrierName());
                $Tracking->addMeta($LANG_SHOP['service'], $resp->Shipment->Service->Description);
                if ($resp->Shipment->Package->DeliveryIndicator == 'Y') {
                    $Tracking->addMeta($LANG_SHOP['expected_dely'], (string)$this->_formatDate($resp->Shipment->Package->DeliveryDate), 'date');
                }
                $Tracking->addMeta($LANG_SHOP['weight'], $resp->Shipment->ShipmentWeight->Weight . ' ' .
                    $resp->Shipment->ShipmentWeight->UnitOfMeasurement->Code);

                // These are found in the first (latest) activity, so up them only
                // if not already done.
                $deliver_to = '';
                $signed_by = '';
                foreach ($resp->Shipment->Package->Activity as $Activity) {
                    if (isset($Activity->ActivityLocation->Description) && $deliver_to == '') {
                        $deliver_to = 'done';
                        $Tracking->addMeta('Delivered To', $Activity->ActivityLocation->Description);
                    }
                    if (!empty($Activity->ActivityLocation->SignedForByName) && $signed_by == '') {
                        $signed_by = 'done';
                        $Tracking->addMeta('Signed By', $Activity->ActivityLocation->SignedForByName);
                    }
                    $Loc = $Activity->ActivityLocation->Address;
                    $addr = array();
                    foreach (array('City', 'StateProvinceCode', 'CountryCode') as $var) {
                        if (!empty($Loc->$var)) {
                            $addr[] = $Loc->$var[0];
                        }
                    }
                    $location = implode(', ', $addr);
                    $date = $this->_formatDate($Activity->Date);
                    $time = $this->_formatTime($Activity->Time);
                    $dt = new \Date($date . ' ' . $time, $_CONF['timezone']);
                    $data = array(
                        'location' => $location,
                        'datetime' => $dt,
                        //'date'  => (string)$Activity->Date,
                        //'time'  => (string)$Activity->Time,
                        'message' => (string)$Activity->Status->StatusType->Description,
                    );
                    $Tracking->addStep($data);
                }
            }
            $Tracking->setCache($this->key, $tracking);
        } catch ( Exception $ex ) {
            $Tracking->addError($LANG_SHOP['err_getting_info']);
            Log::system(Log::ERROR, 'Error getting tracking info: ' . print_r($ex,true)
            );
        }
        return $Tracking;
    }


    /**
     * Convert a date value from "YYYYMMDD" to "YYYY-MM-DD".
     *
     * @param   string  $str    UPS-style date value
     * @return  string          Standard date string
     */
    private function _formatDate($str)
    {
        global $_CONF;

        // Verify that the date string is valid. If not, return today.
        $str = (string)$str;
        if (!is_numeric($str) || strlen($str) != 8) {
            return $_CONF['_now']->format('Y-m-d');
        }
        return substr($str, 0, 4) . '-' . substr($str, 4, 2) . '-' . substr($str, 6, 2);
    }


    /**
     * Convert a time value from "HHMMSS" to "HH:MM:SS".
     *
     * @param   string  $str    UPS-style time value
     * @return  string          Standard time string
     */
    private function _formatTime($str)
    {
        // Check that the time string is valid, if not return midnight.
        $str = (string)$str;
        if (!is_numeric($str) || strlen($str) != 6) {
            return '00:00:00';
        }
        return substr($str, 0, 2) . ':' . substr($str, 2, 2) . ':' . substr($str, 4, 2);
    }


    /**
     * Get an array of rate quote information via API.
     *
     * @param   object  $Order  Order to be shipped
     * @return  array       Array of ShippingQuote objects
     */
    protected function _getQuote($Order)
    {
        $Addr = $Order->getShipto();
        $Packages = Package::packOrder($Order, $this);
        $fixed_cost = 0;    // Cost for all containers set to "fixed"
        $fixed_pkgs = 0;    // Number of fixed-cost containers
        $quote_data = array();

        if (
            $this->free_threshold > 0 &&
            $Order->getNetItems() > $this->free_threshold
        ) {
            $retval = array(
                (new ShippingQuote)
                    ->setID($this->id)
                    ->setShipperID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode('free')
                    ->setServiceID('free')
                    ->setServiceTitle(strtoupper($this->key) . ' Free Shipping')
                    ->setCost(0)
                    ->setPackageCount(count($Packages)),
            );
            return $retval;
        }

        if (!$this->hasValidConfig()) {
            return array();
                /*'id'        => $this->id,
                'title'     => $this->getCarrierName(),
                'quote'     => array(),
                'error'     => true,
            );*/
        }

        $Company = new Company;
        try {
            // create AccessRequest XML
            $accessRequestXML = new SimpleXMLElement ( "<AccessRequest></AccessRequest>" );
            $accessRequestXML->addChild ( "AccessLicenseNumber", $this->getConfig('access_key'));
            $accessRequestXML->addChild ( "UserId", $this->getConfig('userid'));
            $accessRequestXML->addChild ( "Password", $this->getConfig('passwd'));

            // create RateRequest XML
            $rateRequestXML = new SimpleXMLElement ( "<RatingServiceSelectionRequest></RatingServiceSelectionRequest>" );
            $request = $rateRequestXML->addChild ( 'Request' );
            $request->addChild ( "RequestAction", "Rate" );
            //$request->addChild ( "RequestOption", "Rate" );
            $request->addChild ( "RequestOption", "Shop" );

            $shipment = $rateRequestXML->addChild ( 'Shipment' );
            $shipper = $shipment->addChild ( 'Shipper' );
            $shipper->addChild('Name', $Company->getCompany());
            $shipper->addChild('ShipperNumber', "" );
            $shipperddress = $shipper->addChild('Address');
            $shipperddress->addChild('AddressLine1', $Company->getAddress1() );
            if ($Company->getAddress2() != '') {
                $shipFromAddress->addChild("AddressLine2", $Company->getAddress2());
            }
            $shipperddress->addChild('City', $Company->getCity());
            $shipperddress->addChild('PostalCode', $Company->getPostal());
            $shipperddress->addChild('StateProvinceCode', $Company->getState());
            $shipperddress->addChild('CountryCode', $Company->getCountry());

            $shipTo = $shipment->addChild('ShipTo');
            $shipTo->addChild("CompanyName", $Addr->getCompany());
            $shipToAddress = $shipTo->addChild ( 'Address' );
            $shipToAddress->addChild("AddressLine1", $Addr->getAddress1());
            $shipToAddress->addChild("City",$Addr->getCity());
            $shipToAddress->addChild("PostalCode", $Addr->getPostal());
            $shipToAddress->addChild("CountryCode", $Addr->getCountry());

            $Company = Company::getInstance();
            $shipFrom = $shipment->addChild('ShipFrom');
            $shipFrom->addChild("CompanyName", $Company->getCompany());
            $shipFromAddress = $shipFrom->addChild('Address');
            $shipFromAddress->addChild("AddressLine1", $Company->getAddress1());
            if ($Company->getAddress2() != '') {
                $shipFromAddress->addChild("AddressLine2", $Company->getAddress2());
            }
            $shipFromAddress->addChild("City", $Company->getCity());
            $shipFromAddress->addChild("StateProvinceCode", $Company->getState());
            $shipFromAddress->addChild("PostalCode", $Company->getPostal());
            $shipFromAddress->addChild("CountryCode", $Company->getCountry());

            $service = $shipment->addChild ( 'Service' );
            $service->addChild ( "Code", "01,02" );
            //$service->addChild ( "Description", "Next Day Air" );
            //$service = $shipment->addChild ( 'Service' );
            //$service->addChild ( "Code", "03" );
            //$service->addChild ( "Description", "Ground" );

            foreach ($Packages as $Package) {
                $container = $Package->getContainer($this->module_code);
                if ($container['service'] == '_fixed') {
                    $fixed_cost += (float)$container['rate'];
                    $fixed_pkgs++;
                    continue;
                }
                $weight = max($Package->getWeight(), 1);
                $package = $shipment->addChild('Package');
                $packageType = $package->addChild('PackagingType');
                $packageType->addChild("Code", "02");
                $packageType->addChild("Description", "UPS Package");

                $packageWeight = $package->addChild('PackageWeight');
                $unitOfMeasurement = $packageWeight->addChild ('UnitOfMeasurement');
                $unitOfMeasurement->addChild("Code", $this->getWeightUOM());
                $packageWeight->addChild("Weight", $weight);
            }
            if ($fixed_pkgs < count($Packages)) {
                $requestXML = $accessRequestXML->asXML () . $rateRequestXML->asXML ();
                // create Post request
                $form = array (
                    'http' => array (
                        'method'    => 'POST',
                        'header'    => 'Content-type: application/x-www-form-urlencoded',
                        'content'   => $requestXML
                    ),
                );
                $request = stream_context_create ( $form );
                $browser = fopen ( $this->rate_url, 'rb', false, $request );
                if (! $browser) {
                    throw new Exception ( "Connection failed." );
                }

                // get response
                $response = stream_get_contents ( $browser );
                fclose ( $browser );
                if ($response == false) {
                    throw new Exception ( "Bad data." );
                } else {
                    // get response status
                    $resp = new SimpleXMLElement ($response);
                    $quotes = $resp->RatedShipment;
                    foreach ($quotes as $quote) {
                        $classid = (string)$quote->Service->Code;
                        if (!$this->supportsService($classid)) {
                            continue;
                        }
                        $key = $this->key . '.' . $classid;
                        $dscp = $this->svc_codes[$classid];
                        $cost = (float)$quote->TotalCharges->MonetaryValue;
                        $quote_data[] = (new ShippingQuote)
                            ->setID($this->id)
                            ->setShipperID($this->id)
                            ->setCarrierCode($this->key)
                            ->setCarrierTitle($this->getCarrierName())
                            ->setServiceCode($key)
                            ->setServiceID($classid)
                            ->setServiceTitle(strtoupper($this->key) . ' ' . $dscp)
                            ->setCost($cost + $fixed_cost + $this->item_shipping['amount'])
                            ->setPackageCount(count($Packages));
                    }
                    uasort($quote_data, array($this, 'sortQuotes'));
                }
            } else {
                // All packages are fixed-rate
                $quote_data[] = (new ShippingQuote)
                    ->setID($this->id)
                    ->setShipperID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode($this->key . '.fixed')
                    ->setServiceID('_fixed')
                    ->setServiceTitle($this->getCarrierName())
                    ->setCost($fixed_cost + $this->item_shipping['amount'])
                    ->setPackageCount($fixed_pkgs);
            }
        } catch ( Exception $ex ) {
            Log::system(
                Log::ERROR,
                'Error getting quote for order ' . $Order->getOrderID() . print_r($ex,true)
            );
        }
        return $quote_data;
    }


    public function getAllServices()
    {
        return $this->svc_codes;
    }

}
