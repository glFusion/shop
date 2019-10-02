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

class usps extends \Shop\Shipper
{
    public function getQuote($address)
    {
        if ($this->config->get('usps_status')) {
              $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('usps_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
        
              if (!$this->config->get('usps_geo_zone_id')) {
                $status = TRUE;
              } elseif ($query->num_rows) {
                $status = TRUE;
              } else {
                $status = FALSE;
              }
        } else {
            $status = FALSE;
        }
      
        $method_data = array();
    
        if ($status) {
            $this->load->model('localisation/country');

            $quote_data = array();
            
            $weight = $this->weight->convert($this->cart->getWeight(), $this->config->get('config_weight_class'), $this->config->get('usps_weight_class'));
            
            $weight = ($weight < 0.1 ? 0.1 : $weight);
            $pounds = floor($weight);
            $ounces = round(16 * ($weight - floor($weight)));
            
            $postcode = str_replace(' ', '', $address['postcode']);
            
            if ($address['iso_code_2'] == 'US') { 
                $xml  = '<RateV3Request USERID="' . $this->config->get('usps_user_id') . '" PASSWORD="' . $this->config->get('usps_password') . '">';
                $xml .= '    <Package ID="1">';
                $xml .=    '        <Service>ALL</Service>';
                $xml .=    '        <ZipOrigination>' . substr($this->config->get('usps_postcode'), 0, 5) . '</ZipOrigination>';
                $xml .=    '        <ZipDestination>' . substr($postcode, 0, 5) . '</ZipDestination>';
                $xml .=    '        <Pounds>' . $pounds . '</Pounds>';
                $xml .=    '        <Ounces>' . $ounces . '</Ounces>';                
                
                if ($this->config->get('usps_size') == 'LARGE') {
                    $xml .=    '    <Container>' . $this->config->get('usps_container') . '</Container>';
                    $xml .=    '    <Size>' . $this->config->get('usps_size') . '</Size>';
                    $xml .= '    <Width>' . $this->config->get('usps_width') . '</Width>';
                    $xml .= '    <Length>' . $this->config->get('usps_length') . '</Length>';
                    $xml .= '    <Height>' . $this->config->get('usps_height') . '</Height>';
                    $xml .= '    <Girth>' . $this->config->get('usps_girth') . '</Girth>';
                } else {
                    $xml .=    '    <Container>' . $this->config->get('usps_container') . '</Container>';
                    $xml .=    '    <Size>' . $this->config->get('usps_size') . '</Size>';
                }
                
                $xml .=    '        <Machinable>' . ($this->config->get('usps_machinable') ? 'True' : 'False') . '</Machinable>';
                $xml .=    '    </Package>';
                $xml .= '</RateV3Request>';
        
                $request = 'API=RateV3&XML=' . urlencode($xml);
            } else {
                $countryname = \Shop\Address::getCountryName($address['iso_code_2']);
                if (!$countryname) {
                    $xml  = '<IntlRateRequest USERID="' . $this->config->get('usps_user_id') . '" PASSWORD="' . $this->config->get('usps_password') . '">';
                    $xml .=    '    <Package ID="0">';
                    $xml .=    '        <Pounds>' . $pounds . '</Pounds>';
                    $xml .=    '        <Ounces>' . $ounces . '</Ounces>';
                    $xml .=    '        <MailType>Package</MailType>';
                    $xml .=    '        <Country>' . $countryname . '</Country>';
                    $xml .=    '    </Package>';
                    $xml .=    '</IntlRateRequest>';
        
                    $request = 'API=IntlRate&XML=' . urlencode($xml);
                } else {
                    $status = FALSE;    
                }
            }    
            
            if ($status) {
                $ch = curl_init();
                
                curl_setopt($ch, CURLOPT_URL, 'production.shippingapis.com/ShippingAPI.dll?' . $request);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
                $result = curl_exec($ch);
                
                curl_close($ch);  
                
                if ($result) {
                    $dom = new DOMDocument('1.0', 'UTF-8');
                    $dom->loadXml($result);    
                    
                    $rate_v3_response = $dom->getElementsByTagName('RateV3Response')->item(0);
                    $intl_rate_response = $dom->getElementsByTagName('IntlRateResponse')->item(0);
                    $error = $dom->getElementsByTagName('Error')->item(0);
                    
                    if ($rate_v3_response || $intl_rate_response) {
                        if ($address['iso_code_2'] == 'US') { 
                            $allowed = array(0, 1, 2, 3, 4, 5, 6, 7, 12, 13, 16, 17, 18, 19, 22, 23, 25, 27, 28);
                            
                            $package = $rate_v3_response->getElementsByTagName('Package')->item(0);
                            
                            $postages = $package->getElementsByTagName('Postage');
                            
                            foreach ($postages as $postage) {
                                $classid = $postage->getAttribute('CLASSID');
                                
                                if (in_array($classid, $allowed) && $this->config->get('usps_domestic_' . $classid)) {
            
                                    $cost = $postage->getElementsByTagName('Rate')->item(0)->nodeValue;
                                    
                                    $quote_data[$classid] = array(
                                        'id'           => 'usps.' . $classid,
                                        'title'        => $postage->getElementsByTagName('MailService')->item(0)->nodeValue,
                                        'cost'         => $this->currency->convert($cost, 'USD', $this->currency->getCode()),
                                        'tax_class_id' => 0,
                                        'text'         => $this->currency->format($this->tax->calculate($this->currency->convert($cost, 'USD', $this->currency->getCode()), $this->config->get('ups_tax_class_id'), $this->config->get('config_tax')))
                                    );                            
                                }
                            } 
                        } else {
                            $allowed = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 21);
                            
                            $package = $intl_rate_response->getElementsByTagName('Package')->item(0);
                            
                            $services = $package->getElementsByTagName('Service');
                            
                            foreach ($services as $service) {
                                $id = $service->getAttribute('ID');
                                
                                if (in_array($id, $allowed) && $this->config->get('usps_international_' . $id)) {
                                    $title = $service->getElementsByTagName('SvcDescription')->item(0)->nodeValue;
                                    
                                    if ($this->config->get('usps_display_weight')) {      
                                        $title .= ' (' . $this->language->get('text_weight') . ' ' . $this->weight->format($weight, $this->config->get('config_weight_class')) . ')';
                                    }
                        
                                    if ($this->config->get('usps_display_time')) {      
                                        $title .= ' (' . $this->language->get('text_eta') . ' ' . $service->getElementsByTagName('SvcCommitments')->item(0)->nodeValue . ')';
                                    }
                                    
                                    $cost = $service->getElementsByTagName('Postage')->item(0)->nodeValue;
                                    
                                    $quote_data[$id] = array(
                                        'id'           => 'usps.' . $id,
                                        'title'        => $title,
                                        'cost'         => $this->currency->convert($cost, 'USD', $this->currency->getCode()),
                                        'tax_class_id' => $this->config->get('usps_tax_class_id'),
                                        'text'         => $this->currency->format($this->tax->calculate($this->currency->convert($cost, 'USD', $this->currency->getCode()), $this->config->get('ups_tax_class_id'), $this->config->get('config_tax')))
                                    );                            
                                }
                            }
                        }
                    } elseif ($error) {
                        $method_data = array(
                            'id'         => 'usps',
                            'title'      => $this->language->get('text_title'),
                            'quote'      => $quote_data,
                            'sort_order' => $this->config->get('usps_sort_order'),
                            'error'      => $error->getElementsByTagName('Description')->item(0)->nodeValue
                        );                    
                    }
                }
            }
            
              if ($quote_data) {
                
                $title = $this->language->get('text_title');
                                    
                if ($this->config->get('usps_display_weight')) {      
                    $title .= ' (' . $this->language->get('text_weight') . ' ' . $this->weight->format($weight, $this->config->get('config_weight_class')) . ')';
                }        
            
                  $method_data = array(
                    'id'         => 'usps',
                    'title'      => $title,
                    'quote'      => $quote_data,
                    'sort_order' => $this->config->get('usps_sort_order'),
                    'error'      => FALSE
                  );
            }
        }
    
        return $method_data;
    }    


    /**
     * Get the package tracking URL for a given tracking number.
     *
     * @return  string  Package tracing URL
     */
    public function getTrackingUrl($track_num)
    {
        return 'https://tools.usps.com/go/TrackConfirmAction_input?strOrigTrackNum=' . urlencode($track_num);
    }

}
?>
