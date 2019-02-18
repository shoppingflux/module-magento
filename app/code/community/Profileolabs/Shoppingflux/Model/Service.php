<?php

class Profileolabs_Shoppingflux_Model_Service extends Varien_Object
{
    const ORDER_STATUS_SHIPPED = 'Shipped';
    const ORDER_STATUS_CANCELED = 'Canceled';

    const METHOD_GET_ORDERS = 'GetOrders';
    const METHOD_VALIDATE_ORDERS = 'ValidOrders';
    const METHOD_UPDATE_ORDERS = 'UpdateOrders';
    const METHOD_UPDATE_PRODUCT = 'UpdateProduct';
    const METHOD_LOGIN = 'getLogin';
    const METHOD_IS_CLIENT = 'IsClient';
    const METHOD_GET_MARKETPLACES = 'GetMarketplaces';

    /**
     * @var Zend_Http_Client|null
     */
    protected $_client = null;

    /**
     * @var SimpleXMLElement|null
     */
    protected $_xml = null;

    /**
     * @var string|null
     */
    protected $_apiKey = null;

    /**
     * @var string|null
     */
    protected $_wsUri = null;

    public function __construct($apiKey, $wsUri)
    {
        $this->_apiKey = $apiKey;
        $this->_wsUri = $wsUri;
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @return string
     */
    protected function _getApiKey()
    {
        return $this->_apiKey;
    }

    /**
     * @return Zend_Http_Client
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $config = array(
                'curloptions' => array(
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 100
                ),
            );

            try {
                $adapter = new Profileolabs_Shoppingflux_Model_Service_Http_Client_Adapter_Curl();
                $this->_client = new Zend_Http_Client($this->_wsUri);
                $this->_client->setAdapter($adapter);
                $adapter->setConfig($config);
            } catch (Exception $e) {
                Mage::throwException($e);
            }
        }

        return $this->_client;
    }

    /**
     * @param string $apiKey
     * @param string $method
     * @param string $request
     * @return Varien_Simplexml_Element
     */
    protected function _connect($apiKey, $method, $request = '')
    {
        $helper = $this->_getHelper();

        if (empty($apiKey)) {
            $helper->generateTokens();
            Mage::throwException('API Key (Token) is empty');
        }

        /** @var Profileolabs_Shoppingflux_Model_Config $config */
        $config = Mage::getSingleton('profileolabs_shoppingflux/config');

        $data = array(
            'CALL' => $method,
            'TOKEN' => $apiKey,
            'MODE' => $config->isSandbox() ? 'Sandbox' : 'Production',
            'REQUEST' => $request
        );

        foreach ($data as $key => $value) {
            $this->getClient()->setParameterPost($key, $value);
        }

        $response = $this->getClient()->request(Zend_Http_Client::POST);
        $responseText = $response->getBody();
        $this->_xml = simplexml_load_string($responseText, 'Varien_Simplexml_Element', LIBXML_NOCDATA);

        if (!$this->_xml instanceof Varien_Simplexml_Element) {
            Mage::log($responseText, null, 'shoppingflux.log');
            Mage::throwException($helper->__('Result is not Varien_Simplexml_Element'));
        } elseif ($this->_xml->error) {
            Mage::throwException($helper->__('ShoppingFlux API key (Token) is not valid'));
        }

        return $this->_xml;
    }

    /**
     * @return array
     */
    public function getMarketplaces()
    {
        $data = $this->_connect($this->_getApiKey(), self::METHOD_GET_MARKETPLACES);
        return $data->Response->Marketplaces->Marketplace;
    }

    /**
     * @return array
     */
    public function getOrders()
    {
        $data = $this->_connect($this->_getApiKey(), self::METHOD_GET_ORDERS);
        return $data->Response->Orders;
    }

    /**
     * @param string $eventName
     * @param string $xml
     * @return string
     */
    protected function _dispatchApiCallEvent($eventName, $xml)
    {
        $dataObject = new Varien_Object(array('xml' => $xml));
        Mage::dispatchEvent($eventName, array('data_object' => $dataObject));
        return (string) $dataObject->getData('xml');
    }

    /**
     * @param array $orderIds
     * @return Varien_Simplexml_Element
     */
    public function sendValidOrders(array $orderIds)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<ValidOrders>';

        foreach ($orderIds as $orderId => $orderInfo) {
            if (isset($orderInfo['ErrorOrder']) && ($orderInfo['ErrorOrder'] !== false) && !$orderInfo['ErrorOrder']) {
                $orderInfo['ErrorOrder'] = 'Une erreur s\'est produite';
            }

            $xml .= '<Order>';
            $xml .= '<IdOrder>' . $orderId . '</IdOrder>';
            $xml .= '<Marketplace>' . $orderInfo['Marketplace'] . '</Marketplace>';
            $xml .= '<MerchantIdOrder>' . $orderInfo['MageOrderId'] . '</MerchantIdOrder>';

            if (isset($orderInfo['ErrorOrder']) && $orderInfo['ErrorOrder']) {
                $xml .= '<ErrorOrder>' . $orderInfo['ErrorOrder'] . '</ErrorOrder>';
            }

            $xml .= '</Order>';
        }

        $xml .= '</ValidOrders>';

        return $this->_connect(
            $this->_getApiKey(),
            self::METHOD_VALIDATE_ORDERS,
            $this->_dispatchApiCallEvent('shoppingflux_send_valid_orders', $xml)
        );
    }

    /**
     * @param string $orderId
     * @param string $marketplace
     * @param string $status
     * @param string $trackingNumber
     * @param string $trackingCarrier
     * @param string $trackingUrl
     * @return Varien_Simplexml_Element
     */
    public function updateShippedOrder(
        $orderId,
        $marketplace,
        $status,
        $trackingNumber = '',
        $trackingCarrier = '',
        $trackingUrl = ''
    ) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<UpdateOrders>';
        $xml .= '<Order>';
        $xml .= '<IdOrder>' . $orderId . '</IdOrder>';
        $xml .= '<Marketplace>' . $marketplace . '</Marketplace>';
        $xml .= '<Status>' . $status . '</Status>';

        if ($trackingNumber && !preg_match('%^http%i', $trackingNumber)) {
            $xml .= '<TrackingNumber><![CDATA[' . $trackingNumber . ']]></TrackingNumber>';
        }

        if ($trackingUrl) {
            $xml .= '<TrackingUrl><![CDATA[' . $trackingUrl . ']]></TrackingUrl>';
        } else if ($trackingNumber && preg_match('%^http%i', $trackingNumber)) {
            $xml .= '<TrackingUrl><![CDATA[' . $trackingNumber . ']]></TrackingUrl>';
        }

        if ($trackingCarrier) {
            $xml .= '<CarrierName><![CDATA[' . $trackingCarrier . ']]></CarrierName>';
        }

        $xml .= '</Order>';
        $xml .= '</UpdateOrders>';

        return $this->_connect(
            $this->_getApiKey(),
            self::METHOD_UPDATE_ORDERS,
            $this->_dispatchApiCallEvent('shoppingflux_update_shipped_orders', $xml)
        );
    }

    /**
     * @param string $orderId
     * @param string $marketplace
     * @param string $status
     * @return Varien_Simplexml_Element
     */
    public function updateCanceledOrder($orderId, $marketplace, $status)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<UpdateOrders>';
        $xml .= '<Order>';
        $xml .= '<IdOrder>' . $orderId . '</IdOrder>';
        $xml .= '<Marketplace>' . $marketplace . '</Marketplace>';
        $xml .= '<Status>' . $status . '</Status>';
        $xml .= '</Order>';
        $xml .= '</UpdateOrders>';

        return $this->_connect(
            $this->_getApiKey(),
            self::METHOD_UPDATE_ORDERS,
            $this->_dispatchApiCallEvent('shoppingflux_update_canceled_orders', $xml)
        );
    }

    /**
     * @param array|Varien_Data_Collection $updates
     */
    public function updateProducts($updates)
    {
        if ($updates->getSize() <= 0) {
            return;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<UpdateProduct>';

        foreach ($updates as $update) {
            $xml .= '<Product>';
            $xml .= '<SKU>' . $update->getProductSku() . '</SKU>';
            $xml .= '<Quantity>' . $update->getStockValue() . '</Quantity>';
            $xml .= '<Price>' . $update->getPriceValue() . '</Price>';
            $xml .= '<OldPrice>' . $update->getOldPriceValue() . '</OldPrice>';
            $xml .= '</Product>';
        }

        $xml .= '</UpdateProduct>';
        $this->_connect($this->_getApiKey(), self::METHOD_UPDATE_PRODUCT, $xml);
    }

    /**
     * @param string|null $apiKey
     * @return Varien_Simplexml_Element
     */
    public function getLogin($apiKey = null)
    {
        if ($apiKey === null) {
            $apiKey = $this->_getApiKey();
        }

        return $this->_connect($apiKey, self::METHOD_LOGIN);
    }

    /**
     * @param string $apiKey
     * @return bool
     */
    public function checkApiKey($apiKey)
    {
        $login = $this->getLogin($apiKey);
        return !isset($login->error) || !$login->error;
    }

    public function isClient()
    {
        try {
            $result = $this->_connect($this->_getApiKey(), self::METHOD_IS_CLIENT);
            $status = (string) $result->Response->Status;

            if ($status === 'Client') {
                if (!Mage::getStoreConfigFlag('shoppingflux/configuration/has_registered')) {
                    /** @var Mage_Core_Model_Config $config */
                    $config = Mage::getSingleton('core/config');
                    $config->saveConfig('shoppingflux/configuration/has_registered', 1);
                }

                return true;
            }
        } catch (Exception $e) {
            return ($e->getMessage() === 'API Key (Token) is empty');
        }

        return false;
    }
}
