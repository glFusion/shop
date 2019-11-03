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
use \SimpleXMLElement;
use \Exception;


/**
 * Carrier class for USPS.
 * Enable tracking and rate quote retrieval.
 * @package shop
 */
class usps extends \Shop\Shipper
{
    // TODO: Move to config, parameters for quotes
    private $weight = 2.5;
    private $pkg_id = 7;
    private $usps_size = 'NORMAL';
    private $usps_container = 'VARIABLE';
    private $usps_width = 7.325;
    private $usps_length = 11.675;
    private $usps_height = 3.625;
    private $usps_machinable = true;

    /** Endpoint for rate and tracking info.
     * private $usps_endpoint = 'http://production.shippingapis.com/ShippingAPI.dll';
     * @var string */
    private $usps_endpoint = 'https://secure.shippingapis.com/ShippingAPI.dll';

    /** List of possible USPS Services.
     * @var array */
    private $usps_services = array(
        '0_FCLE'    => 'First-Class Mail Large Envelope',
        '0_FCL'     => 'First-Class Mail Letter',
        '0_FCP'     => 'First-Class Package Service - Retail',
        '0_FCPC'    => 'First-Class Mail Postcards',
        '1'         => 'Priority Mail',
        '2'         => 'Priority Mail Express Hold For Pickup',
        '3'         => 'Priority Mail Express',
        '4'         => 'Retail Ground',
        '6'         => 'Media Mail',
        '7'         => 'Library Mail',
        '13'        => 'Priority Mail Express Flat Rate Envelope',
        '15'        => 'First-Class Mail Large Postcards',
        '16'        => 'Priority Mail Flat Rate Envelope',
        '17'        => 'Priority Mail Medium Flat Rate Box',
        '22'        => 'Priority Mail Large Flat Rate Box',
        '23'        => 'Priority Mail Express Sunday/Holiday Delivery',
        '25'        => 'Priority Mail Express Sunday/Holiday Delivery Flat Rate Envelope',
        '27'        => 'Priority Mail Express Flat Rate Envelope Hold For Pickup',
        '28'        => 'Priority Mail Small Flat Rate Box',
        '29'        => 'Priority Mail Padded Flat Rate Envelope',
        '30'        => 'Priority Mail Express Legal Flat Rate Envelope',
        '31'        => 'Priority Mail Express Legal Flat Rate Envelope Hold For Pickup',
        '32'        => 'Priority Mail Express Sunday/Holiday Delivery Legal Flat Rate Envelope',
        '33'        => 'Priority Mail Hold For Pickup',
        '34'        => 'Priority Mail Large Flat Rate Box Hold For Pickup',
        '35'        => 'Priority Mail Medium Flat Rate Box Hold For Pickup',
        '36'        => 'Priority Mail Small Flat Rate Box Hold For Pickup',
        '37'        => 'Priority Mail Flat Rate Envelope Hold For Pickup',
        '38'        => 'Priority Mail Gift Card Flat Rate Envelope',
        '39'        => 'Priority Mail Gift Card Flat Rate Envelope Hold For Pickup',
        '40'        => 'Priority Mail Window Flat Rate Envelope',
        '41'        => 'Priority Mail Window Flat Rate Envelope Hold For Pickup',
        '42'        => 'Priority Mail Small Flat Rate Envelope',
        '43'        => 'Priority Mail Small Flat Rate Envelope Hold For Pickup',
        '44'        => 'Priority Mail Legal Flat Rate Envelope',
        '45'        => 'Priority Mail Legal Flat Rate Envelope Hold For Pickup',
        '46'        => 'Priority Mail Padded Flat Rate Envelope Hold For Pickup',
        '47'        => 'Priority Mail Regional Rate Box A',
        '48'        => 'Priority Mail Regional Rate Box A Hold For Pickup',
        '49'        => 'Priority Mail Regional Rate Box B',
        '50'        => 'Priority Mail Regional Rate Box B Hold For Pickup',
        '53'        => 'First-Class Package Service Hold For Pickup',
        '57'        => 'Priority Mail Express Sunday/Holiday Delivery Flat Rate Boxes',
        '58'        => 'Priority Mail Regional Rate Box C',
        '59'        => 'Priority Mail Regional Rate Box C Hold For Pickup',
        '61'        => 'First-Class Package Service',
        '62'        => 'Priority Mail Express Padded Flat Rate Envelope',
        '63'        => 'Priority Mail Express Padded Flat Rate Envelope Hold For Pickup',
        '64'        => 'Priority Mail Express Sunday/Holiday Delivery Padded Flat Rate Envelope',
        'INT_1'     => 'Priority Mail Express International',
        'INT_2'     => 'Priority Mail International',
        'INT_4'     => 'Global Express Guaranteed (GXG)',
        'INT_5'     => 'Global Express Guaranteed Document',
        'INT_6'     => 'Global Express Guaranteed Non-Document Rectangular',
        'INT_7'     => 'Global Express Guaranteed Non-Document Non-Rectangular',
        'INT_8'     => 'Priority Mail International Flat Rate Envelope',
        'INT_9'     => 'Priority Mail International Medium Flat Rate Box',
        'INT_10'    => 'Priority Mail Express International Flat Rate Envelope',
        'INT_11'    => 'Priority Mail International Large Flat Rate Box',
        'INT_12'    => 'USPS GXG Envelopes',
        'INT_13'    => 'First-Class Mail International Letter',
        'INT_14'    => 'First-Class Mail International Large Envelope',
        'INT_15'    => 'First-Class Package International Service',
        'INT_16'    => 'Priority Mail International Small Flat Rate Box',
        'INT_17'    => 'Priority Mail Express International Legal Flat Rate Envelope',
        'INT_18'    => 'Priority Mail International Gift Card Flat Rate Envelope',
        'INT_19'    => 'Priority Mail International Window Flat Rate Envelope',
        'INT_20'    => 'Priority Mail International Small Flat Rate Envelope',
        'INT_21'    => 'First-Class Mail International Postcard',
        'INT_22'    => 'Priority Mail International Legal Flat Rate Envelope',
        'INT_23'    => 'Priority Mail International Padded Flat Rate Envelope',
        'INT_24'    => 'Priority Mail International DVD Flat Rate priced box',
        'INT_25'    => 'Priority Mail International Large Video Flat Rate priced box',
        'INT_27'    => 'Priority Mail Express International Padded Flat Rate Envelope',
    );

    /** Services enabled for domestic delivery.
     * Could move to a config selection.
     * @var array */
    private $domestic_enabled = array(
        '1', 3, 4,
        //6,
    );

    /** Services enabled for international delivery.
     * Could move to a config selection.
     * @var array */
    private $intl_enabled = array(
        1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 21,
    );


    /**
     * Set up local variables and call the parent constructor.
     */
    public function __construct()
    {
        $this->key = 'usps';
        $this->implementsTrackingAPI = true;
        $this->cfgFields = array(
            'user_id' => 'password',
            'password' => 'password',
            'origin_zip' => 'string',
        );
        parent::__construct();
    }


    /**
     * Get the shipper's name for display.
     *
     * @return  string  Shipper name
     */
    public static function getCarrierName()
    {
        return 'United States Postal Service';
    }


    /**
     * Get an array or rate quotes for a package.
     *
     * @param   object  $address    Destination Address object
     * @return  array       Array of quote data
     */
    public function getQuote($address)
    {
        if (!$this->hasQuoteAPI()) {
            return array (
                'id'         => $this->key,
                'title'      => 'USPS Rates',
                'quote'      => array(),
                'error'      => true
            );
        }

        $method_data = array();
        $quote_data = array();

        $weight = $this->weight;
        $weight = ($weight < 0.1 ? 0.1 : $weight);
        $pounds = floor($weight);
        $ounces = round(16 * ($weight - floor($weight)));
        $postcode = str_replace(' ', '', $address->zip);
        $status = true;

        if ($address->country == 'US') {
            $xml = new SimpleXMLElement(
                '<RateV4Request USERID="' . $this->getConfig('user_id') . '"></RateV4Request>'
            );
            $xml->addChild('Revision', '2');
            $pkg = $xml->addChild('Package');
            $pkg->addAttribute('ID', 1);
            $pkg->addChild('Service', 'ALL');
            $pkg->addChild('FirstClassMailType', 'PACKAGE');
            $pkg->addChild('ZipOrigination', substr($this->getConfig('origin_zip'), 0, 5));
            $pkg->addChild('ZipDestination', substr($postcode, 0, 5));
            $pkg->addChild('Pounds', $pounds);
            $pkg->addChild('Ounces', $ounces);
            $pkg->addChild('Container', $this->usps_container);
            $pkg->addChild('Size', $this->usps_size);
            if ($this->usps_size == 'LARGE') {
                $pkg->addChild('Width', $this-usps_width);
                $pkg->addChild('Length', $this->usps_length);
                $pkg->addChild('Height', $this-usps_height);
                //$pkg->addChild('Girth', $this->usps_girth);
                $pkg->addChild('Size', $this->usps_size);
            }
            $pkg->addChild('Machinable', $this->usps_machinable ? 'True' : 'False');
            $request = 'API=RateV4&XML=' . urlencode($xml->asXML());
        } else {
            $countryname = \Shop\Address::getCountryName($address->country);
            if ($countryname) {
                $xml = new SimpleXMLElement(
                    '<IntlRateV2Request USERID="' . $this->getConfig('user_id') . '"></IntlRateV2Request>'
                );
                $pkg = $xml->addChild('Package');
                $pkg->addAttribute('ID', '0');
                $pkg->addChild('Pounds', $pounds);
                $pkg->addChild('Ounces', $ounces);
                $pkg->addChild('MailType', 'Package');
                $pkg->addChild('ValueOfContents', 20);
                $pkg->addChild('Country', $countryname);
                $request = 'API=IntlRateV2&XML=' . urlencode($xml->asXML());
            } else {
                $status = FALSE;
            }
        }

        if ($status) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->usps_endpoint . '?' . $request);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            //var_dump($result);die;
            curl_close($ch);

            if ($result) {
                $xml = new SimpleXMLElement($result);
                $dom = new \DOMDocument('1.0', 'UTF-8');
                $dom->loadXml($result);

                //$rate_v4_response = $dom->getElementsByTagName('RateV4Response')->item(0);
                //$intl_rate_response = $dom->getElementsByTagName('IntlRateResponse')->item(0);
                $error = $xml->Error;

                if ($address->country == 'US') {
                    $allowed = array(0, 1, 2, 3, 4, 5, 6, 7, 12, 13, 16, 17, 18, 19, 22, 23, 25, 27, 28);
                    $package = $xml->Package;
                    //$package = $rate_v4_response->getElementsByTagName('Package')->item(0);
                    $postages = $package->Postage;

                    foreach ($postages as $postage) {
                        $classid = (string)$postage['CLASSID'];
                        if (
                            in_array($classid, $allowed) &&
                            in_array($classid, $this->domestic_enabled)
                        ) {
                            $cost = (float)$postage->Rate;
                            $title = html_entity_decode($postage->MailService);
                            $quote_data[$classid] = array(
                                'id'        => 'usps.' . $classid,
                                'svc_code'  => $classid,
                                'title'     => $title,
                                'cost'      => $cost,
                            );
                        }
                    }
                } else {
                    $allowed = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 21);
                    $package = $xml->Package;
                    $services = $package->Service;

                    foreach ($services as $service) {
                        $svc_err = $service->ServiceErrors;
                        if ($svc_err) {
                            continue;
                        }
                        $id = (string)$service['ID'];
                        $key = $this->key . '.' . $id;
                        if (
                            in_array($id, $allowed) &&
                            in_array($id, $this->intl_enabled)
                        ) {
                            $title = html_entity_decode($service->SvcDescription);
                            $cost = (float)$service->Postage;
                            $quote_data[$key] = array(
                                'id'        => $key,
                                'svc_code'  => $id,
                                'title'     => $title,
                                'cost'      => $cost,
                            );
                        }
                    }
                }
            }
        }

        if ($quote_data) {
            uasort($quote_data, array($this, 'sortQuotes'));
            $method_data = array(
                'id'        => $this->key,
                'title'     => $this->getCarrierName(),
                'quote'     => $quote_data,
                'error'     => FALSE
            );
        }
        return $method_data;
    }


    /**
     * Get the package tracking URL for a given tracking number.
     * This is used only if the tracking API is not used.
     *
     * @return  string  Package tracking URL
     */
    protected function _getTrackingUrl($track_num)
    {
        return 'https://tools.usps.com/go/TrackConfirmAction_input?strOrigTrackNum=' . urlencode($track_num);
    }


    /**
     * Get tracking data via API and return a Tracking object.
     *
     * @param   string  $tracking   Single tracking number
     * @return  object      Tracking object
     */
    public function getTracking($tracking)
    {
        if (!$this->hasTrackingAPI()) {
            return $false;
        }

        $Tracking = \Shop\Tracking::getCache($this->key, $tracking);
        if ($Tracking !== NULL) {
            return $Tracking;
        }
        $Tracking = new \Shop\Tracking;
        try {
            // Create AccessRequest XMl
            $RequestXML = new SimpleXMLElement(
                '<TrackFieldRequest USERID="' . $this->getConfig('user_id') . '"></TrackFieldRequest>'
            );
            $RequestXML->addChild('TrackID ID="' . $tracking . '"');
            $XML = $RequestXML->asXML ();
            $request = 'API=TrackV2&XML=' . urlencode($XML);

            // get request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->usps_endpoint . '?' . $request);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            curl_close($ch);

            /* Testing, use this instead of the above block
             * $response = file_get_contents(__DIR__ . '/../XOLTResult.xml');
             */

            if ($result == false) {
                //throw new Exception ( "Bad data." );
            } else {
                // get response status
                $resp = new SimpleXMLElement($result);
                $info = $resp->TrackInfo;
                $Tracking->addMeta('Tracking Number', $tracking);
                $Tracking->addMeta('Carrier', self::getCarrierName());

                if (isset($info->Error)) {
                    $Tracking->addError(html_entity_decode($info->Error->Description));
                } else {
                    $step = array(
                        'date'  => $resp->TrackInfo->TrackSummary->EventDate,
                        'time'  => $resp->TrackInfo->TrackSummary->EventTime,
                        'location' => $this->_makeTrackLocation($resp->TrackInfo->TrackSummary),
                        'message' => $resp->TrackInfo->TrackSummary->Event,
                    );
                    $Tracking->addStep($step);

                    foreach ($resp->TrackInfo->TrackDetail as $detail) {
                        $step = array(
                            'date'  => $detail->EventDate,
                            'time'  => $detail->EventTime,
                            'location' => $this->_makeTrackLocation($detail),
                            'message' => $detail->Event,
                        );
                        $Tracking->addStep($step);
                    }
                }
            }
        } catch ( Exception $ex ) {
            echo $ex;
        }
        $Tracking->setCache($this->key, $tracking);
        return $Tracking;
    }


    /**
     * Make a single location string from the separate XML fields.
     * Empty fields are skipped.
     *
     * @param   object  $xml    XML Object Snippet
     * @return  string      Location string, empty if none included in $xml
     */
    private function _makeTrackLocation($xml)
    {
        $parts = array();
        foreach (array('EventCity', 'EventState', 'EventPostalCode') as $var) {
            if (!empty($xml->$var)) {
                $parts[] = $xml->$var;
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
