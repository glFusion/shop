<?php
/**
 * UPS shipper class.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.0.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Shippers;
// For getTracking() and getQuote()
use \SimpleXmlElement;
use \Exception;


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
    private $svc_codes = array(
        '01'    => 'Next Day Air',
        '02'    => '2nd Day Air',
        '03'    => 'Ground',
        '12'    => '3 Day Select',
        '13'    => 'UPS Next Day Air Saver',
        '14'    => 'UPS Next Day Air Early',
        '59'    => '2nd Day Air A.M.',
        '07'    => 'Worldwide Express',
        '08'    => 'Worldwide Expedited',
        '11'    => 'Worldwide Standard',
        '54'    => 'Worldwide Express Plus',
        '65'    => 'Worldwide Saver',
        '96'    => 'UPS Worldwide Express Freight',
        '71'    => 'UPS Worldwide Express Freight Midday',
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
        $this->cfgFields = array(
            'userid' => 'password',
            'passwd' => 'password',
            'access_key' => 'password',
            'test_mode' => 'checkbox',
        );
        parent::__construct($A);

        if ($this->getConfig('test_mode')) {
            $this->rate_url = $this->rate_url_test;
            $this->track_url = $this->track_url_test;
        } else {
            $this->rate_url = $this->rate_url_prod;
            $this->track_url = $this->track_url_prod;
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
    public function getTracking($tracking)
    {
        // Attempt to get from cache
        $Tracking = \Shop\Tracking::getCache($this->key, $tracking);
        if ($Tracking !== NULL) {
            return $Tracking;
        }

        $Tracking = new \Shop\Tracking;
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
                $resp = new SimpleXMLElement ( $response );
                $Tracking->addMeta($LANG_SHOP['tracking_num'], (string)$resp->Shipment->Package->TrackingNumber);
                $Tracking->addMeta($LANG_SHOP['carrier'], self::getCarrierName());
                if ((string)$resp->Response->ResponseStatusCode == '0') {
                    $Tracking->addError($resp->Response->Error->ErrorCode . ': ' . $resp->Response->Error->ErrorDescription);
                    return $Tracking;
                }
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
                /*echo $Tracking->getDisplay();
                echo $resp->Response->ResponseStatusDescription . "\n";*/
            }
        } catch ( Exception $ex ) {
            echo $ex;
        }
        $Tracking->setCache($this->key, $tracking);
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
        //echo $str;die;
        return substr($str, 0, 2) . ':' . substr($str, 2, 2) . ':' . substr($str, 4, 2);
    }


    /**
     * Get an array of rate quote information via API.
     *
     * @param   object  $Addr   Destination address object
     * @return  array       Array of quote data (service and cost)
     */
    public function getQuote($Addr)
    {
        if (!$this->hasValidConfig()) {
            return array(
                'id'        => $this->key,
                'title'     => $this->getCarrierName(),
                'quote'     => array(),
                'error'     => true,
            );
        }

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
            $shipper->addChild ( "Name", "Name" );
            $shipper->addChild ( "ShipperNumber", "" );
            $shipperddress = $shipper->addChild ( 'Address' );
            $shipperddress->addChild ( "AddressLine1", "Address Line" );
            $shipperddress->addChild ( "City", "Lancaster" );
            $shipperddress->addChild ( "PostalCode", "93536" );
            $shipperddress->addChild ( "CountryCode", "US" );

            $shipTo = $shipment->addChild ( 'ShipTo' );
            $shipTo->addChild ("CompanyName", $Addr->company);
            $shipToAddress = $shipTo->addChild ( 'Address' );
            $shipToAddress->addChild ( "AddressLine1", $Addr->address1);
            $shipToAddress->addChild ( "City",$Addr->city);
            $shipToAddress->addChild ( "PostalCode", $Addr->zip);
            $shipToAddress->addChild ( "CountryCode", $Addr->country );

            $shipFrom = $shipment->addChild ( 'ShipFrom' );
            $shipFrom->addChild ( "CompanyName", "Company Name" );
            $shipFromAddress = $shipFrom->addChild ( 'Address' );
            $shipFromAddress->addChild ( "AddressLine1", "Address Line" );
            $shipFromAddress->addChild ( "City", "Lancaster" );
            $shipFromAddress->addChild ( "StateProvinceCode", "CA" );
            $shipFromAddress->addChild ( "PostalCode", "93536" );
            $shipFromAddress->addChild ( "CountryCode", "US" );

        //    $service = $shipment->addChild ( 'Service' );
        //    $service->addChild ( "Code", "01,02" );
        //        $service->addChild ( "Description", "Next Day Air" );
        //    $service = $shipment->addChild ( 'Service' );
        //    $service->addChild ( "Code", "02" );
        //    $service->addChild ( "Description", "2nd Day Air" );

            $package = $shipment->addChild ( 'Package' );
            $packageType = $package->addChild ( 'PackagingType' );
            $packageType->addChild ( "Code", "02" );
            $packageType->addChild ( "Description", "UPS Package" );

            $packageWeight = $package->addChild ( 'PackageWeight' );
            $unitOfMeasurement = $packageWeight->addChild ( 'UnitOfMeasurement' );
            $unitOfMeasurement->addChild ( "Code", "LBS" );
            $packageWeight->addChild ( "Weight", "15.2" );

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
                $quote_data = array();
                foreach ($quotes as $quote) {
                    $classid = (string)$quote->Service->Code;
                    $key = $this->key . '.' . $classid;
                    $dscp = $this->svc_codes[$classid];
                    $cost = (float)$quote->TotalCharges->MonetaryValue;
                    $quote_data[$key] = array(
                        'id'        => $key,
                        'svc_code'  => $classid,
                        'title'     => $dscp,
                        'cost'      => $cost,
                    );
                }
                uasort($quote_data, array($this, 'sortQuotes'));
                $method_data = array(
                    'id'        => $this->key,
                    'title'     => $this->getCarrierName(),
                    'quote'     => $quote_data,
                    'error'     => FALSE
                );
            }
        } catch ( Exception $ex ) {
            echo $ex;
        }
        return $method_data;
    }

}

?>
