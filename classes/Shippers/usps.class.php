<?php
/**
 * US Postal Service shipper class to get shipping rates.
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
use \SimpleXMLElement;
use \Exception;
use Shop\Models\ShippingQuote;
use Shop\Package;
use Shop\Log;


/**
 * Carrier class for USPS.
 * Enable tracking and rate quote retrieval.
 * @package shop
 */
class usps extends \Shop\Shipper
{
    // TODO: Move to config, parameters for quotes
    /** Package size, normal or large.
     * @var string */
    private $usps_size = 'NORMAL';

    /** Container type.
     * @var string */
    private $usps_container = 'VARIABLE';

    /** Is the package machine-readable?
     * @var boolean */
    private $usps_machinable = true;

    /*private $usps_width = 7.325;
    private $usps_length = 11.675;
    private $usps_height = 3.625;*/

    /** Endpoint for rate and tracking info.
     * @var string */
    protected $usps_endpoint = 'https://secure.shippingapis.com/ShippingAPI.dll';

    /** List of possible Domestic USPS Services.
     * @var array */
    private $domestic = array(
        'DOM_0_FCLE'=> 'First-Class Mail Large Envelope',
        'DOM_0_FCL' => 'First-Class Mail Letter',
        'DOM_0_FCP' => 'First-Class Package Service - Retail',
        'DOM_0_FCPC'=> 'First-Class Mail Postcards',
        'DOM_0'     => 'First Class',
        'DOM_1'     => 'Priority Mail',
        'DOM_2'     => 'Priority Mail Express 1-Day',
        'DOM_3'     => 'Priority Mail Express 1-Day Hold For Pickup',
        'DOM_4'     => 'Retail Ground',
        'DOM_6'     => 'Media Mail',
        'DOM_7'     => 'Library Mail',
        'DOM_13'    => 'Priority Mail Express Flat Rate Envelope',
        'DOM_15'    => 'First-Class Mail Large Postcards',
        'DOM_16'    => 'Priority Mail Flat Rate Envelope',
        'DOM_17'    => 'Priority Mail 2-Day Medium Flat Rate Box',
        'DOM_22'    => 'Priority Mail Large Flat Rate Box',
        'DOM_23'    => 'Priority Mail Express Sunday/Holiday Delivery',
        'DOM_25'    => 'Priority Mail Express Sunday/Holiday Delivery Flat Rate Envelope',
        'DOM_27'    => 'Priority Mail Express Flat Rate Envelope Hold For Pickup',
        'DOM_28'    => 'Sm Flat Rate Box',
        'DOM_29'    => 'Priority Mail Padded Flat Rate Envelope',
        'DOM_30'    => 'Priority Mail Express Legal Flat Rate Envelope',
        'DOM_31'    => 'Priority Mail Express Legal Flat Rate Envelope Hold For Pickup',
        'DOM_32'    => 'Priority Mail Express Sunday/Holiday Delivery Legal Flat Rate Envelope',
        'DOM_33'    => 'Priority Mail Hold For Pickup',
        'DOM_34'    => 'Priority Mail Large Flat Rate Box Hold For Pickup',
        'DOM_35'    => 'Priority Mail Medium Flat Rate Box Hold For Pickup',
        'DOM_36'    => 'Priority Mail Small Flat Rate Box Hold For Pickup',
        'DOM_37'    => 'Priority Mail Flat Rate Envelope Hold For Pickup',
        'DOM_38'    => 'Priority Mail Gift Card Flat Rate Envelope',
        'DOM_39'    => 'Priority Mail Gift Card Flat Rate Envelope Hold For Pickup',
        'DOM_40'    => 'Priority Mail Window Flat Rate Envelope',
        'DOM_41'    => 'Priority Mail Window Flat Rate Envelope Hold For Pickup',
        'DOM_42'    => 'Priority Mail Small Flat Rate Envelope',
        'DOM_43'    => 'Priority Mail Small Flat Rate Envelope Hold For Pickup',
        'DOM_44'    => 'Priority Mail Legal Flat Rate Envelope',
        'DOM_45'    => 'Priority Mail Legal Flat Rate Envelope Hold For Pickup',
        'DOM_46'    => 'Priority Mail Padded Flat Rate Envelope Hold For Pickup',
        'DOM_47'    => 'Priority Mail Regional Rate Box A',
        'DOM_48'    => 'Priority Mail Regional Rate Box A Hold For Pickup',
        'DOM_49'    => 'Priority Mail Regional Rate Box B',
        'DOM_50'    => 'Priority Mail Regional Rate Box B Hold For Pickup',
        'DOM_53'    => 'First-Class Package Service Hold For Pickup',
        'DOM_57'    => 'Priority Mail Express Sunday/Holiday Delivery Flat Rate Boxes',
        'DOM_58'    => 'Priority Mail Regional Rate Box C',
        'DOM_59'    => 'Priority Mail Regional Rate Box C Hold For Pickup',
        'DOM_61'    => 'First-Class Package Service',
        'DOM_62'    => 'Priority Mail Express Padded Flat Rate Envelope',
        'DOM_63'    => 'Priority Mail Express Padded Flat Rate Envelope Hold For Pickup',
        'DOM_64'    => 'Priority Mail Express Sunday/Holiday Delivery Padded Flat Rate Envelope',
        'DOM_77'    => 'Parcel Select Ground',
    );

    /** List of possible International USPS Services.
     * @var array */
    private $international = array(
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

    /** USPS Service Type Codes.
     * @var array */
    protected $svc_codes = array(
        '_na' => '-- Not Available--',
        '_fixed' => '-- Fixed Rate --',
        'FC_LTR' => 'First Class / Letter',
        'FC_FLT' => 'First Class / Flat',
        'FC_PKG' => 'First Class / Package Service Retail',
        'FCC_PKG' => 'First Class Commercial / Package Service',
        'FCC_HFP' => 'First Class HFP Commercial / Package Service',
        'PCL_GND' => 'Parcel Select Ground',
        'RET_GND' => 'Retail Ground',
        'PRI' => 'Priority',
        'PRI_EXP' => 'Priority Mail Express',
        'PRI_COM' => 'Priority Commercial',
        'PRI_CPP' => 'Priority Cpp',
        'PRI_HFP_COM' => 'Priority HFP Commercial',
        'PRI_HFP_CPP' => 'Priority HFP CPP',
        'PRI_EXP_COM' => 'Priority Mail Express Commercial',
        'PRI_EXP_CPP' => 'Priority Mail Express CPP',
        'PRI_EXP_SH' => 'Priority Mail Express Sh',
        'PRI_EXP_SH_COM' => 'Priority Mail Express Sh Commercial',
        'PRI_EXP_HFP' => 'Priority Mail Express HFP',
        'PRI_EXP_HFP_COM' => 'Priority Mail Express HFP Commercial',
        'PRI_EXP_HFP_CPP' => 'Priority Mail Express HFP CPP',
        'PRI_CUB' => 'Priority Mail Cubic',
    );

    /** USPS package codes.
     * @var array */
    protected $pkg_codes = array(
        'VARIABLE' => 'VARIABLE',
        'FLAT RATE ENVELOPE' => 'FLAT RATE ENVELOPE',
        'PADDED FLAT RATE ENVELOPE' => 'PADDED FLAT RATE ENVELOPE',
        'LEGAL FLAT RATE ENVELOPE' => 'LEGAL FLAT RATE ENVELOPE',
        'SM FLAT RATE ENVELOPE' => 'SM FLAT RATE ENVELOPE',
        'GIFT CARD FLAT RATE ENVELOPE' => 'GIFT CARD FLAT RATE ENVELOPE',
        'SM FLAT RATE BOX' => 'SM FLAT RATE BOX',
        'MD FLAT RATE BOX' => 'MD FLAT RATE BOX',
        'LG FLAT RATE BOX' => 'LG FLAT RATE BOX',
        'REGIONALRATEBOXA' => 'REGIONALRATEBOXA',
        'REGIONALRATEBOXB' => 'REGIONALRATEBOXB',
    );


    /**
     * Set up local variables and call the parent constructor.
     *
     * @param   mixed   $A      Optional data array or shipper ID
     */
    public function __construct($A = array())
    {
        $this->key = 'usps';
        $this->implementsTrackingAPI = true;
        $this->implementsQuoteAPI = true;
        $this->cfgFields = array(
            'user_id' => 'password',
            'password' => 'password',
            'origin_zip' => 'text',     // future use for quotes
            'services' => 'array',
            //'intl_services' => 'array',
        );
        parent::__construct($A);
        $services = $this->getConfig('services');
        if (is_array($services)) {
            $this->supported_services = $services;
        }
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
     * @param   object  $Order  Order to be shipped
     * @return  array       Array of ShippingQuote objects
     */
    protected function _getQuote($Order)
    {
        global $_SHOP_CONF, $_CONF;

        $Addr = $Order->getShipto();
        $Packages = Package::packOrder($Order, $this);

        if (!$this->hasQuoteAPI()) {
            return parent::getUnitQuote($Order);
        }

        //$method_data = array();
        $quote_data = array();
        $fixed_cost = 0;    // Cost for all containers set to "fixed"
        $fixed_pkgs = 0;    // Number of fixed-cost containers

        $postcode = str_replace(' ', '', $Addr->getPostal());
        $status = true;

        if ($Addr->getCountry() == 'US') {
            $xml = new SimpleXMLElement(
                '<RateV4Request USERID="' . $this->getConfig('user_id') . '"></RateV4Request>'
            );
            $xml->addChild('Revision', '2');
            $pkgcount = 0;
            foreach ($Packages as $Package) {
                $container = $Package->getContainer('usps');
                if ($container['service'] == '_fixed') {
                    $fixed_cost += (float)$container['rate'];
                    $fixed_pkgs++;
                    continue;
                }

                $weight = $Package->convertWeight();
                $weight = ($weight < 0.1 ? 0.1 : $weight);
                $pounds = floor($weight);
                $ounces = round(16 * ($weight - floor($weight)));
                $pkg = $xml->addChild('Package');
                $pkg->addAttribute('ID', (string)++$pkgcount);
                if (
                    isset($container['service']) &&
                    isset($this->getServiceCodes()[$container['service']])
                ) {
                    $svc_code = strtoupper($this->getServiceCodes()[$container['service']]);
                    $svc = explode(' / ', $svc_code);
                    $pkg->addChild('Service', $svc[0]);
                    if (isset($svc[1])) {
                        if (substr($svc[0],0,11) == 'FIRST CLASS') {
                            $pkg->addChild('FirstClassMailType', $svc[1]);
                        }
                    }
                } else { 
                    $pkg->addChild('Service', 'ALL');
                }

                $pkg->addChild('ZipOrigination', substr($this->getConfig('origin_zip'), 0, 5));
                $pkg->addChild('ZipDestination', substr($postcode, 0, 5));
                $pkg->addChild('Pounds', (string)$pounds);
                $pkg->addChild('Ounces', (string)$ounces);
                if (isset($container['container'])) {
                    $pkg->addChild('Container', $container['container']);
                } else {
                    // Default to variable container type
                    $pkg->addChild('Container', 'VARIABLE');
                }
                if ($pkgcount < 1) {
                    $pkg->addChild('Width', (string)$Package->getWidth());
                    $pkg->addChild('Length', (string)$Package->getLength());
                    $pkg->addChild('Height', (string)$Package->getHeight());
                }
                //$pkg->addChild('Girth', $this->usps_girth);
                $pkg->addChild('Machinable', $this->usps_machinable ? 'True' : 'False');
            }
            $request = 'API=RateV4&XML=' . urlencode($xml->asXML());
        } else {
            $countryname = \Shop\Country::getInstance($Addr->getCountry())->getName();
            if ($countryname) {
                $xml = new SimpleXMLElement(
                    '<IntlRateV2Request USERID="' . $this->getConfig('user_id') . '"></IntlRateV2Request>'
                );
                $xml->addChild('Revision', '2');
                foreach ($Packages as $Package) {
                    $weight = $Package->convertWeight();
                    $weight = ($weight < 0.1 ? 0.1 : $weight);
                    $pounds = floor($weight);
                    $ounces = round(16 * ($weight - floor($weight)));
                    $pkg = $xml->addChild('Package');
                    $pkg->addAttribute('ID', ++$pkgcount);
                    $pkg->addChild('Pounds', $pounds);
                    $pkg->addChild('Ounces', $ounces);
                    $pkg->addChild('MailType', 'Package');
                    $pkg->addChild('ValueOfContents',$Package->getValue());
                    $pkg->addChild('Country', $countryname);
                    $pkg->addChild('Container', $this->usps_container);
                    $pkg->addChild('OriginZip', substr($this->getConfig('origin_zip'), 0, 5));
                    $pkg->addChild('AcceptanceDateTime', $_CONF['_now']->toISO8601(true));
                    $pkg->addChild('DestinationPostalCode', substr($postcode, 0, 5));
                }
                //echo $xml->asXML();
                $request = 'API=IntlRateV2&XML=' . urlencode($xml->asXML());
            } else {
                $status = FALSE;
            }
        }

        if ($pkgcount < 1 && $fixed_pkgs > 0) {
            $svc_code = $this->module_code . '.fixed';
            $quote_data = array(
                $svc_code => (new ShippingQuote)
                    ->setID($this->id)
                    ->setShipperID($this->id)
                    ->setCarrierCode($this->key)
                    ->setCarrierTitle($this->getCarrierName())
                    ->setServiceCode($svc_code)
                    ->setServiceID('_fixed')
                    ->setServiceTitle($this->getCarrierName())
                    ->setCost($fixed_cost + $this->item_shipping['amount'])
                    ->setPackageCount($fixed_pkgs),
            );
            $num_packages = $fixed_pkgs;
        } elseif ($status) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->usps_endpoint . '?' . $request);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result) {
                $json = json_encode(simplexml_load_string($result));
                $json = json_decode($json, true);
                if (isset($json['Error'])) {
                    $error = $json['Error'];
                } else {
                    $error = '';
                }

                if ($Addr->getCountry() == 'US') {
                    if (isset($json['Package'])) {
                        $packages = $json['Package'];
                    } else {
                        $packages = array();
                    }
                    if (!is_array($packages) || !isset($packages[1])) {
                        $packages = array($packages);
                    }
                    $num_packages = count($packages);
                    $pkgcount = 0;
                    foreach ($packages as $pkgid=>$package) {
                        if (!isset($package['Postage'])) {
                            continue;
                        }
                        $postages = $package['Postage'];
                        if (isset($postages['Rate'])) { // single rate returned
                            $postages = array($postages);
                        }
                        foreach ($postages as $postage) {
                            $classid = $postage['@attributes']['CLASSID'];
                            $svc_id = 'DOM_' . $classid;
                            if (
                                $this->supportsService($svc_id) &&
                                array_key_exists($svc_id, $this->_svcAllowedDomestic())
                            ) {
                                $cost = (float)$postage['Rate'];
                                $title = html_entity_decode($postage['MailService']);
                                $key = $this->key . '.' . $classid;
                                if (!isset($quote_data[$key])) {
                                    $quote_data[$key] = (new ShippingQuote)
                                        ->setID($this->id)
                                        ->setShipperID($this->id)
                                        ->setCarrierCode($this->key)
                                        ->setCarrierTitle($this->getCarrierName())
                                        ->setServiceCode($key)
                                        ->setServiceID($classid)
                                        ->setServiceTitle(strtoupper($this->key) . ' ' . $title)
                                        ->setCost($cost + $this->item_shipping['amount'])
                                        ->setPackageCount(1);
                                } else {
                                    $quote_data[$key]['cost'] += $cost;
                                    $quote_data[$key]['pkg_count']++;
                                    $quote_data[$key]['cost'] += $fixed_cost;
                                }
                            }
                        }
                    }
                } else {
                    $package = $json['Package'];
                    $services = $package['Service'];

                    foreach ($services as $service) {
                        $svc_err = $service['ServiceErrors'];
                        if ($svc_err) {
                            continue;
                        }
                        $svc_id = (string)$service['@attributes']['ID'];
                        $id = 'INT_' . $svc_id;
                        $key = $this->key . '.' . $id;
                        if (
                            $this->supportsService($id) &&
                            array_key_exists($id, $this->_svcAllowedIntl())
                        ) {
                            $title = html_entity_decode($service['SvcDescription']);
                            $cost = (float)$service['Postage'];
                            $key = $this->key . '.' . $svc_id;
                            if (!isset($quote_data[$key])) {
                                $quote_data[$key] = (new ShippingQuote)
                                    ->setID($this->id)
                                    ->setCarrierCode($this->key)
                                    ->setCarrierTitle($this->getCarrierName())
                                    ->setServiceCode($key)
                                    ->setServiceID($svc_id)
                                    ->setServiceTitle(strtoupper($this->key) . ' ' . $title)
                                    ->setCost($cost + $this->item_shipping['amount'])
                                    ->setPackageCount(1);
                            } else {
                                $quote_data[$key]['cost'] += $cost;
                                $quote_data[$key]['pkg_count']++;
                                $quote_data[$key]['cost'] += $fixed_cost;
                            }
                        }
                    }
                }
            }
        }

        if ($quote_data) {
            foreach ($quote_data as $id=>$quote) {
                // Remove any quotes that do not include all packages
                if ($quote['pkg_count'] < $num_packages) {
                    unset($quote_data[$id]);
                }
            }
            $x = uasort($quote_data, array(__CLASS__, 'sortQuotes'));
        }
        return $quote_data;
    }


    /**
     * Get the package tracking URL for a given tracking number.
     * This is used only if the tracking API is not used.
     *
     * @param   string  $track_num  Tracking number
     * @return  string  Package tracking URL
     */
    protected function _getTrackingUrl($track_num)
    {
        return 'https://tools.usps.com/go/TrackConfirmAction_input?strOrigTrackNum=' . urlencode($track_num);
    }


    /**
     * Get tracking data via API and return a Tracking object.
     *
     * @param   string  $track_num   Single tracking number
     * @return  object      Tracking object
     */
    public function getTracking($track_num)
    {
        global $LANG_SHOP;

        if (!$this->hasTrackingAPI()) {
            return $false;
        }

        $Tracking = \Shop\Tracking::getCache($this->key, $track_num);
        if ($Tracking !== NULL) {
            return $Tracking;
        }
        $Tracking = new \Shop\Tracking;
        try {
            // Create AccessRequest XMl
            $RequestXML = new SimpleXMLElement(
                '<TrackFieldRequest></TrackFieldRequest>'
            );
            $RequestXML->addAttribute('USERID', $this->getConfig('user_id'));
            $RequestXML->addChild('Revision', '1');
            $RequestXML->addChild('ClientIp', '127.0.0.1');
            $RequestXML->addChild('SourceId', 'glfusion-shop');
            $RequestXML
                ->addChild('TrackID')
                ->addAttribute('ID', $track_num);
            $XML = $RequestXML->asXML ();
            $request = 'API=TrackV2&XML=' . urlencode($XML);

            // get request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->usps_endpoint . '?' . $request);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            //var_dump($result);die;
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
                $Tracking->addMeta($LANG_SHOP['tracking_num'], $track_num);
                $Tracking->addMeta($LANG_SHOP['carrier'], self::getCarrierName());
                $Tracking->addMeta($LANG_SHOP['service'], $info->Class);

                $dest = array();
                foreach (array('DestinationCity', 'DestinationState', 'DestinationZip') as $elem) {
                    if ($info->$elem) {
                        $dest[] = $info->$elem;
                    }
                }
                if (!empty($dest)) {
                    $dest = implode(', ', $dest);
                    $Tracking->addMeta($LANG_SHOP['destination'], $dest);
                }
                if ($info->ExpectedDeliveryDate) {
                    $Tracking->addMeta($LANG_SHOP['expected_dely'], $info->ExpectedDeliveryDate);
                }
                if ($info->StatusSummary) {
                    $Tracking->addMeta($LANG_SHOP['status'], $info->StatusSummary);
                }
                $Tracking->AddMeta($LANG_SHOP['tracking_info'], COM_createLink(
                    $this->_getTrackingUrl($track_num),
                    $this->_getTrackingUrl($track_num),
                    array(
                        'target' => '_blank',
                    )
                ) );

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
            $Tracking->addError($LANG_SHOP['err_getting_info']);
            Log::write('shop_system', Log::ERROR, 
                'Error getting tracking info: ' . print_r($ex,true)
            );
        }
        $Tracking->setCache($this->key, $track_num);
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


    /**
     * Get the USPS web endpoint.
     *
     * @return   string     Endpoint URL
     */
    public function getEndpoint()
    {
        return $this->usps_endpoint;
    }


    private function _svcAllowedIntl()
    {
        return $this->international;
    }


    private function _svcAllowedDomestic()
    {
        return $this->domestic;
    }

    public function getAllServices()
    {
        return array_merge($this->domestic, $this->international);
    }

}
