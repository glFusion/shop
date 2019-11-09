<?php
/**
 * US Postal Service shipper class to get shipping rates.
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

    /** Full path to WSDL file, required for API requests.
     * @var string */
    private $wsdl = __DIR__ . '/etc/TrackService_v18.wsdl';

    /** WSDL major version number.
     * @var string */
    private $major_ver = '18';

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


    /**
     * Set up local variables and call the parent constructor.
     */
    public function __construct()
    {
        $this->key = 'fedex';
        $this->implementsTrackingAPI = true;
        $this->cfgFields = array(
            'key' => 'password',
            'passwd' => 'password',
            'acct_num' => 'string',
            'meter_num' => 'string',
            'test_mode' => 'checkbox',
        );
        parent::__construct();
        if ($this->getConfig('test_mode')) {
            $this->track_url = $this->track_url_test;
        } else {
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
                'Key'       => $this->getConfig('key'),
                'Password'  => $this->getConfig('passwd'),
            )
        );
        //Build Client Detail
        $this->request['ClientDetail'] = array(
            'AccountNumber' => $this->getConfig('acct_num'),
            'MeterNumber'   => $this->getConfig('meter_num'),
        );

        // Build API Version info
        $this->request['Version'] = array(
            'ServiceId'     => $svc_id,
            'Major'         => $this->major_ver,
            'Intermediate'  => '0',
            'Minor'         => '0',
        );
        // Enable detailed scans
        $this->request['ProcessingOptions'] = 'INCLUDE_DETAILED_SCANS';
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
        $Tracking = \Shop\Tracking::getCache($this->key, $track_num);
        if ($Tracking !== NULL) {
            return $Tracking;
        }

        $Tracking = new \Shop\Tracking;
        $Tracking->addMeta('Tracking Number', $track_num);
        $Tracking->addMeta('Carrier', self::getCarrierName());

        if (!$this->hasValidConfig()) {
            $Tracking->addError('Invalid Configuration');
            return $Tracking;
        }

        // Will throw a SoapFault exception if wsdlPath is unreachable
        $_soapClient = new \SoapClient($this->wsdl, array('trace' => true));

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
        $req = $this->_buildSoapRequest('trck', $request);
        $response = $_soapClient->track($req);
        //var_dump($response);die;
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
                //var_dump($Event);die;
                /*if (isset($Event->Address->City)) {
                    $loc = $Event->Address->City . ' ';
                } else {
                    $loc = '';
                }
                $loc .= $Event->Address->CountryCode;*/
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
            COM_errorLog(print_r($response,true));
            $Tracking->addError('Non-successful response received.');
        }
        $Tracking->setCache($this->key, $track_num);
        return $Tracking;
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

?>
