<?php

/**
 * Class Packpin_Pptrack_Helper_Data
 *
 * API calls helpers
 * see https://packpin.com/docs for more documentation
 */
class PackpinAPI
{
    /**
     * Backend
     */
    const API_BACKEND = 'https://api.packpin.com/v2/';

    /**
     * Api routes
     */
    const API_PATH_CARRIERS = 'carriers';
    const API_PATH_TRACKINGS = 'trackings';
    const API_PATH_TRACKINGS_BATCH = 'trackings/batch';
    const API_PATH_TRACKING_INFO = 'trackings/%s/%s';
    const API_PATH_TEST = 'test/1';

    /**
     * Packpin API key
     *
     * @var string
     */
    protected $_apiKey;

    /**
     * Last API call status code
     *
     * @var integer
     */
    protected $_lastStatusCode;

    /**
     * Need to init key from a var
     *
     * @var integer
     */
    public function __construct($apiKey = "")
    {
        if (!empty($apiKey))
            $this->_apiKey = $apiKey;
    }

    protected function _getApiKey()
    {
        if ($this->_apiKey === null) {
            $opts = get_option('packpin_tracking_settings');
            $this->_apiKey = $opts['api_key'];
        }

        return $this->_apiKey;
    }

    /**
     * Make API request
     *
     * @param string $route
     * @param string $method
     * @param array $body
     *
     * @return bool|array
     */
    protected function _apiRequest($route, $method = 'GET', $body = array())
    {
        $body['plugin_type'] = 'woocommerce';
        $body['plugin_version'] = $this->getExtensionVersion();
        $body['plugin_shop_version'] = get_bloginfo('version');
        $body['plugin_user'] = get_bloginfo('name');
        $body['plugin_email'] = get_bloginfo('admin_email');
        $body['plugin_url'] = get_bloginfo('url');

        $url = self::API_BACKEND . $route;

        $ch = curl_init($url);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        } elseif ($method == 'PUT') {
            curl_setopt($ch, CURLOPT_PUT, true);
        } elseif ($method != 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        //timeouts
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $headers = array(
            'packpin-api-key: ' . $this->_getApiKey(),
            'Content-Type: application/json',
        );
        if ($body) {
            $dataString = json_encode($body);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
            $headers[] = 'Content-Length: ' . strlen($dataString);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        syslog(5, '$route ' . $route);
        syslog(5, '$response ' . print_r($response, true));
        $this->_lastStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $this->_throwFatalError(curl_error($ch));
        }
        curl_close($ch);
        unset($ch);
        return $response;
    }

    /**
     * Get info about single tracking object
     *
     * @param string $carrierCode
     * @param string $trackingCode
     *
     * @return array
     */
    public function getTrackingInfo($carrierCode, $trackingCode)
    {
        $url = sprintf(self::API_PATH_TRACKING_INFO, $carrierCode, $trackingCode);

        $hash = $carrierCode . $trackingCode;
        $cacheKey = 'packpin_tracking_' . $hash;

        if (false === ($info = get_transient($cacheKey))) {
            $res = $this->_apiRequest($url, 'GET');
            if ($res) {
                $info = json_decode($res, true);
            }
            set_transient($cacheKey, $info, 10 * MINUTE_IN_SECONDS);
        }

        return $info;
    }

    /**
     * Get list of available carriers
     *
     * @return array
     */
    public function getCarrierList()
    {
        $url = self::API_PATH_CARRIERS;

        if (false === ($info = get_transient('packpin_carrier_list'))) {
            $res = $this->_apiRequest($url, 'GET');
            if ($res) {
                $info = json_decode($res, true);
            }
            set_transient('packpin_carrier_list', $info, 6 * HOUR_IN_SECONDS);
        }

        return $info;
    }

    /**
     * Add new tracking code
     *
     * @param string $carrierCode
     * @param string $trackingCode
     * @param string|null $description
     * @param string|null $postalCode
     * @param string|null $destinationCountry
     * @param string|null $shipDate
     *
     * @return array
     */
    public function addTrackingCode($carrierCode, $trackingCode, $description = null, $postalCode = null, $destinationCountry = null, $shipDate = null)
    {
        $info = array();

        $url = self::API_PATH_TRACKINGS;
        $body = array(
            'code' => $trackingCode,
            'carrier' => $carrierCode,
            'description' => $description,
            'track_postal_code' => $postalCode,
            'track_ship_date' => $shipDate,
            'track_destination_country' => $destinationCountry,
        );

        $res = $this->_apiRequest($url, 'POST', $body);
        if ($res) {
            $info = json_decode($res, true);
        }

        return $info;
    }

    public function removeTrackingCode($carrierCode, $trackingCode)
    {
        $info = array();

        $url = sprintf(self::API_PATH_TRACKING_INFO, $carrierCode, $trackingCode);

        $res = $this->_apiRequest($url, 'DELETE');

        if ($res) {
            $info = json_decode($res, true);
        } else {
            $info = array(
                "statusCode" => $this->_lastStatusCode
            );
        }

        return $info;
    }

    public function testApiKey()
    {
        $info = array();

        $url = self::API_PATH_TEST;
        $res = $this->_apiRequest($url, 'GET');
        if ($res) {
            $info = json_decode($res, true);
        }

        return $info;
    }

    public function getExtensionVersion()
    {
        return PPWSI_VERSION;
    }

    protected function _throwFatalError($msg)
    {
        wp_die('A fatal error occured while accessing Packpin API:<br/><b>' . $msg . '</b><br/> Contact info@packpin.com for assistance!<br/><br/><a href="javascript:window.history.back();">Go back</a>', 'Fatal Packpin API Error!');
    }
}