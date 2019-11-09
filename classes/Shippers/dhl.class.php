<?php
/**
 * DHL shipper class.
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
 * DHL Shipper class.
 * @package shop
 */
class dhl extends \Shop\Shipper
{
    /** DHL service prefixes used for message reference.
     */
    const SERVICE_PREFIX_QUOTE = 'QUOT';
    const SERVICE_PREFIX_SHIPVAL = 'SHIP';
    const SERVICE_PREFIX_TRACKING = 'TRCK';

    /** Tracking URL. Set to production or test values in constructor.
     * @var string */
    private $track_url = 'https://api-eu.dhl.com/track/shipments';


    /**
     * Set up local variables and call the parent constructor.
     */
    public function __construct()
    {
        $this->key = 'dhl';
        $this->implementsTrackingAPI = true;
        $this->cfgFields = array(
            'application' => 'string',
            'access_id' => 'password',
            'password' => 'password',
            'consumer_key'  => 'password',
            'consumer_secret' => 'password',
            //'test_mode' => 'checkbox',
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
        return 'DHL Worldwide';
    }


    /**
     * Get the package tracking URL for a given tracking number.
     *
     * @param   string  $track_num  Tracking number
     * @return  string  Package tracking URL
     */
    public function _getTrackingUrl($track_num)
    {
        return "https://www.logistics.dhl/us-en/home/tracking/tracking-freight.html?submit=1&tracking-id={$track_num}";
    }


    /**
     * Send request for tracking
     *
     * @param   string  $tracking   Tracking number
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

        //$result = file_get_contents('dhl2.txt');

        $tracking = urlencode($tracking);
        // get request
        $url = $this->track_url . '?trackingNumber=' . $tracking;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'DHL-API-Key:' . urlencode($this->getConfig('consumer_key')),
        ) );
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        $json = @json_decode($result);
        if (!$json) {   // error decoding
            $Tracking->addError('Invalid response received.');
        } elseif ($json->status) {    // Error code received
                $Tracking->addError($json->detail . ' (' . $json->status . ')');
        } else {
            foreach ($json->shipments as $shipment) {
                $Tracking->addMeta('Tracking Number', $tracking);
                $Tracking->addMeta('Origin', $shipment->origin->address->addressLocality);
                $Tracking->addMeta('Destination', $shipment->destination->address->addressLocality);

                if ($shipment->details->proofOfDelivery) {
                    $Tracking->addMeta(
                        'Proof of Delivery',
                        COM_createLink(
                            'Click Here',
                            $shipment->details->proofOfDelivery->signatureUrl,
                            array(
                                'target' => '_blank'
                            )
                        )
                    );
                }
                foreach ($shipment->events as $Event) {
                    $date_parts = explode('T', $Event->timestamp);
                    $Tracking->addStep(array(
                        'location' => $Event->location->address->addressLocality,
                        'date' => $date_parts[0],
                        'time' => $date_parts[1],
                        'message' => $Event->description,
                    ) );
                }
            }
        }
        $Tracking->setCache($this->key, $tracking);
        return $Tracking;
    }


    /**
     * Build the message timestamp required by DHL.
     *
     * @return  string      Timestamp in RFC3339 format
     */
    private function buildMessageTimestamp()
    {
        global $_CONF;

        return $_CONF['_now']->format(\DATE_RFC3339, true);
    }


    /**
     * Builds a string to be used as the MessageReference.
     *
     * @param   string  $servicePrefix  Service prefix
     * @return  string
     */
    private function buildMessageReference(string $servicePrefix): string
    {
        return str_replace('.', '', uniqid("GLFSHOP_{$servicePrefix}_", true));
    }

}

?>
