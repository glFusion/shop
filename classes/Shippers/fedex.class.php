<?php
/**
 * US Postal Service shipper class to get shipping rates.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2019-2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v1.3.0
 * @since       v1.0.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Shop\Shippers;
use Shop\Company;
use Shop\Package;
use Shop\Currency;
use Shop\Tracking;
use Shop\Models\ShippingQuote;
use Shop\Order;


/**
 * Class to manage FedEx as a carrier
 * @package shop
 */
class fedex extends \Shop\Shipper
{
    /*
     * Test Tracking Numbers:
        49044304137821 = Shipment information sent to FedEx
        149331877648230 = Tendered
        020207021381215 = Picked Up
        403934084723025 = Arrived at FedEx location
        920241085725456 = At local FedEx facility
        568838414941 = At destination sort facility
        039813852990618 = Departed FedEx location
        231300687629630 = On FedEx vehicle for delivery
        797806677146 = International shipment release
        377101283611590 = Customer not available or business closed
        852426136339213 = Local Delivery Restriction
        797615467620 = Incorrect Address
        957794015041323 = Unable to Deliver
        076288115212522 = Returned to Sender/Shipper
        581190049992 = International Clearance delay
        122816215025810 = Delivered
        843119172384577 = Hold at Location
        070358180009382 = Shipment Canceled
        111111111111    = Delivered (working)
    */

    private $client_key = '';
    private $passwd = '';
    private $meter_num = '';
    private $acct_num = '';
    private $req_info = array(
        'trck' => array(
            'wsdl'  => __DIR__ . '/etc/fedex/TrackService_v18.wsdl',
            'major_ver' => '18',
            'minor_ver' => '0',
        ),
        'crs' => array(
            'wsdl'  => __DIR__ . '/etc/fedex/RateService_v28.wsdl',
            'major_ver' => '28',
            'minor_ver' => '0',
        ),
    );

    /** SOAP request.
     * @var object */
    private $request;

    /** Tracking URL to use.
     * @var string */
    private $track_url;

    /** Test Tracking URL.
     * @var string */
    private $track_url_test = 'https://wsbeta.fedex.com:443/web-services';

    /** Production Tracking URL.
     * @var string */
    private $track_url_prod = 'https://ws.fedex.com:443/web-services';

    protected $svc_codes = array(
        '_na' => '-- Not Available--',
        '_fixed' => '-- Fixed Rate --',
        'STANDARD_OVERNIGHT' => 'STANDARD_OVERNIGHT',
        'PRIORITY_OVERNIGHT' => 'PRIORITY_OVERNIGHT',
        'FEDEX_GROUND' => 'FEDEX_GROUND',
        'GROUND_HOME_DELIVERY' => 'GROUND_HOME_DELIVERY',
    );

    protected $pkg_codes = array(
        'FEDEX_BOX' => 'FEDEX_BOX',
        'FEDEX_PAK' => 'FEDEX_PAK',
        'FEDEX_TUBE' => 'FEDEX_TUBE',
        'YOUR_PACKAGING' => 'YOUR_PACKAGING',
    );


    /**
     * Set up local variables and call the parent constructor.
     *
     * @param   mixed   $A      Optional data array or shipper ID
     */
    public function __construct($A = array())
    {
        $this->key = 'fedex';
        $this->implementsTrackingAPI = true;
        $this->implementsQuoteAPI = true;
        $this->cfgFields = array(
            'test_key' => 'password',
            'test_passwd' => 'password',
            'test_acct_num' => 'text',
            'test_meter_num' => 'text',
            'test_mode' => 'checkbox',
        );
        parent::__construct($A);
        if ($this->getConfig('test_mode')) {
            $this->track_url = $this->track_url_test;
            $this->client_key = $this->getConfig('test_key');
            $this->acct_num = $this->getConfig('test_acct_num');
            $this->meter_num = $this->getConfig('test_meter_num');
            $this->passwd = $this->getConfig('test_passwd');
        } else {
            $this->track_url = $this->track_url_prod;
            $this->client_key = $this->getConfig('prod_key');
            $this->acct_num = $this->getConfig('prod_acct_num');
            $this->meter_num = $this->getConfig('prod_meter_num');
            $this->passwd = $this->getConfig('prod_passwd');
        }
    }


    /**
     * Get the shipper's name for display.
     *
     * @return  string  Shipper name
     */
    public static function getCarrierName()
    {
        return 'FedEx';
    }


    /**
     * Get the package tracking URL for a given tracking number.
     *
     * @param   string  $track_num  Tracking number
     * @return  string  Package tracking URL
     */
    public function _getTrackingUrl($track_num)
    {
        return 'https://www.fedex.com/apps/fedextrack/index.html?trackingnumbers=' . urlencode($track_num);
    }


    /**
     * Builds and returns the basic request array.
     * Common to Tracking and Quote requests.
     *
     * Takes an optional parameter, $addReq. This parameter is
     * used to set the additonal request details. These details
     * are determined by the particular service being called and
     * are passed by the extended service classes.
     *
     * @param   string  $svc_id     Service ID
     * @param   array   $addReq     Additonal request details
     * @return  object      SOAP request array
     */
    private function _buildSoapRequest($svc_id, $addReq = NULL)
    {
        $this->requiest = array();
        // Build Authentication
        $this->request['WebAuthenticationDetail'] = array(
            'UserCredential'=> array(
                'Key'       => $this->client_key,
                'Password'  => $this->passwd,
            )
        );
        //Build Client Detail
        $this->request['ClientDetail'] = array(
            'AccountNumber' => $this->acct_num,
            'MeterNumber'   => $this->meter_num,
            'SoftwareId'    => 'glFusion Shop Plugin',
        );

        // Build API Version info
        $this->request['Version'] = array(
            'ServiceId'     => $svc_id,
            'Major'         => $this->req_info[$svc_id]['major_ver'],
            'Intermediate'  => '0',
            'Minor'         => $this->req_info[$svc_id]['minor_ver'],
        );
        if (is_array($addReq)) {
            $this->request = array_merge($this->request, $addReq);
        }
        return $this->request;
    }


    /**
     * Get tracking data via API and return a Tracking object.
     *
     * @param   string  $track_num  Single tracking number
     * @return  object      Tracking object
     */
    public function getTracking($track_num)
    {
        //$track_num = '111111111111';     // testing override
        // Attempt to get from cache
        $Tracking = Tracking::getCache($this->key, $track_num);
        if ($Tracking !== NULL) {
            return $Tracking;
        }

        $Tracking = new Tracking;
        $Tracking->addMeta('Tracking Number', $track_num);
        $Tracking->addMeta('Carrier', self::getCarrierName());

        if (!$this->hasValidConfig()) {
            $Tracking->addError('Invalid Configuration');
            return $Tracking;
        }

        // Will throw a SoapFault exception if wsdlPath is unreachable
        $_soapClient = new \SoapClient($this->req_info['trck']['wsdl'], array('trace' => true));

        // Set the endpoint
        $_soapClient->__setLocation($this->track_url);

        // Initialize request to an empty array
        $request = array();

        // Build Customer Transaction Id
        $request['TransactionDetail'] = array(
            'CustomerTransactionId' => 'Tracking request via PHP',
        );

        $request['SelectionDetails'] = array(
            'PackageIdentifier' => array(
                'Type' => 'TRACKING_NUMBER_OR_DOORTAG',
                'Value' => $track_num,  // Tracking ID to track
            )
        );
        // Enable detailed scans
        $request['ProcessingOptions'] = 'INCLUDE_DETAILED_SCANS';

        $req = $this->_buildSoapRequest('trck', $request);
        $response = $_soapClient->track($req);
        if ($response === false) {
            $Tracking->addError('Unknown Error Response');
            return $Tracking;
        } elseif ($response->HighestSeverity == 'SUCCESS') {
            $TrackDetails = $response->CompletedTrackDetails->TrackDetails;
            if ($TrackDetails->Service) {
                $Tracking->addMeta(
                    'Service',
                    $TrackDetails->Service->Description
                );
            }
            $StatusDetail = $TrackDetails->StatusDetail;
            $Tracking->addMeta('Status', $StatusDetail->Description);
            if ($StatusDetail->Code == 'DL') {   // Delivered
                $Tracking->addMeta('Delivered to', $TrackDetails->DeliveryLocationDescription);
                $Tracking->addMeta('Signed By', $TrackDetails->DeliverySignatureName);
            }
            $Events = $TrackDetails->Events;
            foreach ($Events as $Event) {
                $loc = $this->_makeTrackLocation($Event->Address);
                $Tracking->addStep(
                    array(
                        'location' => $loc,
                        //'datetime' => $Event->Timestamp,
                        'date'  => substr($Event->Timestamp, 0, 10),
                        'time'  => substr($Event->Timestamp, 11),
                        'message' => (string)$Event->EventDescription,
                    )
                );
            }
        } else {
            SHOP_log(
                __CLASS__ . '::' . __FUNCTION__ .
                '- Error getting tracking info: ' .
                print_r($response,true)
            );
            $Tracking->addError('Non-successful response received.');
        }
        $Tracking->setCache($this->key, $track_num);
        return $Tracking;
    }


    /**
     * Get rate quotes via API.
     *
     * @param   object  $Order  Order to be shipped
     * @return  array       Array of ShippingQuote objects
     */
    protected function _getQuote($Order)
    {
        global $_SHOP_CONF;

        $Addr = $Order->getShipto();
        $Packages = Package::packOrder($Order, $this);
        $fixed_cost = 0;    // Cost for all containers set to "fixed"
        $fixed_pkgs = 0;    // Number of fixed-cost containers
        $retval = array();

        // Will throw a SoapFault exception if wsdlPath is unreachable
        $_soapClient = new \SoapClient($this->req_info['crs']['wsdl'], array('trace' => true));

        // Set the endpoint
        $_soapClient->__setLocation($this->track_url);

        // Initialize request to an empty array
        $request = array();

        // Build Customer Transaction Id
        $request['TransactionDetail'] = array(
            'CustomerTransactionId' => 'Rate Quote Request via PHP',
        );

        $request['ReturnTransitAndCommit'] = true;
        $request['RequestedShipment']['ShipTimestamp'] = date('c');

        // valid values REGULAR_PICKUP, REQUEST_COURIER, ...
        // TODO: Global Carrier Config item
        $request['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP';

        // valid values STANDARD_OVERNIGHT, PRIORITY_OVERNIGHT, FEDEX_GROUND, ...
        $request['RequestedShipment']['ServiceType'] = 'GROUND_HOME_DELIVERY';

        // valid values FEDEX_BOX, FEDEX_PAK, FEDEX_TUBE, YOUR_PACKAGING, ...
        $request['RequestedShipment']['PackagingType'] = 'YOUR_PACKAGING';
        
        $request['RequestedShipment']['TotalInsuredValue']=array(
            'Ammount'=> $Order->getGrossItems(),
            'Currency'=> Currency::getInstance()->getCode(),
        );

        $Company = new Company;
        $request['RequestedShipment']['Shipper'] = array(
            'Address' => array(
                'StreetLines' => array(
                    $Company->getAddress1(),
                ),
                'City' => $Company->getCity(),
                'StateOrProvinceCode' => $Company->getState(),
                'PostalCode' => $Company->getPostal(),
                'CountryCode' => $Company->getCountry(),
                'Residential' => true,
            ),
        );
        $request['RequestedShipment']['Recipient'] = array(
            'Address' => array(
                'StreetLines' => array(
                    $Addr->getAddress1(),
                    $Addr->getAddress2(),
                ),
                'City' => $Addr->getCity(),
                'StateOrProvinceCode' => $Addr->getState(),
                'PostalCode' => $Addr->getPostal(),
                'CountryCode' => $Addr->getCountry(),
                'Residential' => true,
            ),
        );
        $request['RequestedShipment']['PackageCount'] = count($Packages);
        $request['RequestedShipment']['ShippingChargesPayment'] = array(
            'PaymentType' => 'SENDER',  // valid values RECIPIENT, SENDER and THIRD_PARTY
            'Payor' => array(
                'ResponsibleParty' => array(
                    'AccountNumber' => $this->acct_num,
                        'CountryCode' => $Company->getCountry(),
                ),
            ),
        );

        $seq_no = 0;
        foreach ($Packages as $Package) {
            $container = $Package->getContainer($this->module_code);
            if ($container['service'] == '_fixed') {
                $fixed_cost += (float)$container['rate'];
                $fixed_pkgs++;
                continue;
            }
            $request['RequestedShipment']['RequestedPackageLineItems'] = array(
                'SequenceNumber'=> ++$seq_no,
                'GroupPackageCount'=> 1,
                'Weight' => array(
                        'Value' => $Package->getWeight(),
                        'Units' => $this->getWeightUOM(),
                ),
                'Dimensions' => array(
                        'Length' => $Package->getLength(),
                        'Width' => $Package->getWidth(),
                        'Height' => $Package->getHeight(),
                        'Units' => $this->getSizeUOM(),
                ),
            );
        }
        if ($fixed_pkgs < count($Packages)) {
            $req = $this->_buildSoapRequest('crs', $request);
            $response = $_soapClient->getRates($req);
            if (
                $response->HighestSeverity != 'FAILURE' &&
                $response->HighestSeverity != 'ERROR'
            ) {
                $RateReply = $response->RateReplyDetails;
                $svc_dscp = $RateReply->ServiceDescription->Description;
                $svc_id = 'HD';     // home delivery default
                foreach ($RateReply->ServiceDescription->Names as $Name) {
                    if ($Name->Type == 'abbrv' && $Name->Encoding == 'ascii') {
                        $svc_id = $Name->Value;
                    }
                }
                $svc_code = $this->key . '.' . $svc_id;
                $cost = (float)$RateReply
                    ->RatedShipmentDetails
                    ->ShipmentRateDetail
                    ->TotalNetCharge
                    ->Amount;
                $retval[] = (new ShippingQuote)
                    ->setID($this->id)
                    ->setShipperID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode($svc_code)
                    ->setServiceID($svc_id)
                    ->setServiceTitle($svc_dscp)
                    ->setCost($cost + $fixed_cost)
                    ->setPackageCount(count($Packages));
            } else {
                SHOP_log(
                    __CLASS__ . '::' . __FUNCTION__ .
                    " Error getting Fedex quote for order {$Order->getOrderID()} " .
                    print_r($response,true)
                );
            }
        } else {
            $retval[] = (new ShippingQuote)
                ->setID($this->id)
                ->setShipperID($this->id)
                ->setCarrierCode($this->key)
                ->setCarrierTitle($this->getCarrierName())
                ->setServiceCode('_fixed')
                ->setServiceID('_fixed')
                ->setServiceTitle($this->getCarrierName())
                ->setCost($fixed_cost)
                ->setPackageCount(count($Packages));
        }
        return $retval;
    }


    /**
     * Make a single location string from the separate response fields.
     * Empty fields are skipped.
     *
     * @param   object  $obj    Response Object Snippet
     * @return  string      Location string, empty if none included in $obj
     */
    private function _makeTrackLocation($obj)
    {
        $parts = array();
        foreach (array('City', 'StateOrProvinceCode', 'CountryCode') as $var) {
            if (!empty($obj->$var)) {
                $parts[] = $obj->$var;
            }
        }
        if (!empty($parts)) {
            return implode(', ', $parts);
        } else {
            return '';
        }
    }

}
