<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait, vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Service extends Varien_Object {
    /**
     * Order status
     */

    const ORDER_STATUS_SHIPPED = 'Shipped';
    const ORDER_STATUS_CANCELED = 'Canceled';

    /**
     * Method's names 
     */
    const METHOD_GET_ORDERS = "GetOrders";
    const METHOD_VALIDATE_ORDERS = "ValidOrders";
    const METHOD_UPDATE_ORDERS = "UpdateOrders";
    const METHOD_UPDATE_PRODUCT = "UpdateProduct";
    const METHOD_LOGIN = "getLogin";
    const METHOD_IS_CLIENT = "IsClient";
    const METHOD_GET_MARKETPLACES = 'GetMarketplaces';

    /**
     * 
     * @var Zend_Http_Client
     */
    protected $_client = null;

    /**
     * 
     * @var SimpleXMLElement
     */
    protected $_xml = null;
    protected $_apiKey = null;
    protected $_wsUri = null;

    public function __construct($apiKey, $wsUri) {
        $this->_apiKey = $apiKey;
        $this->_wsUri = $wsUri;
    }

    protected function _getApiKey() {
        return $this->_apiKey;
    }

    /**
     * Get client HTTP
     * @return Zend_Http_Client
     */
    public function getClient() {
        if (is_null($this->_client)) {
            //adapter options
            $config = array('curloptions' => array(/* CURLOPT_FOLLOWLOCATION => true, */
                    //CURLOPT_POST=>true,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_HEADER => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 100),
            );
            try {

                //innitialize http lcient and adapter curl
                //$adapter = new Zend_Http_Client_Adapter_Curl();
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
     * Connect to Shopping Flux and Call method
     * 
     * @param string $apiKey
     * @param string $method
     */
    protected function _connect($apiKey, $method, $request='') {
        if (empty($apiKey)) {
            Mage::helper('profileolabs_shoppingflux')->generateTokens();
            Mage::throwException("API Key (Token) is empty");
        }
        $mode = Mage::getSingleton('profileolabs_shoppingflux/config')->isSandbox() ? 'Sandbox' : 'Production';
        $data = array("CALL" => $method, "TOKEN" => $apiKey, "MODE" => $mode, "REQUEST" => $request);
        /* @var $response Zend_Http_Response */
        //set Post Params
        foreach ($data as $key => $val)
            $this->getClient()->setParameterPost($key, $val);
        //send the request
        $response = $this->getClient()->request(Zend_Http_Client::POST);
        //die($response->getBody());
        //load response at xml (SimpleXMLElement)
        
        
        $responseText = $response->getBody();
               
        /* $responseText = '<?xml version="1.0" encoding="utf-8"?>
<Result>
            <Request>
            <Date>2015-06-02T11:08:42+01:00</Date><Call>GetOrders</Call><Token>bezeezra17ee07d2a87e444827c18a77e01d</Token><Mode>Sandbox</Mode><Version>2</Version>
            </Request>
            <Response>
            <Orders>
                 <Order>
                   <IdOrder>402-81an7840e085-1568300</IdOrder>
                   <Marketplace>Amazon</Marketplace>
                   <Currency>EUR</Currency>
                   <TotalAmount>299.00</TotalAmount>
                   <TotalProducts>299</TotalProducts>
                   <TotalShipping>0.00</TotalShipping>
                   <TotalFees>0.49</TotalFees>
                   <NumberOfProducts>1</NumberOfProducts>
                   <OrderDate>2015-11-05T13:09:41+01:00</OrderDate>
                   <Other></Other>
                   <ShippingMethod>Nouvelle Shipping method</ShippingMethod>
                   <BillingAddress><LastName>moon chan kim</LastName><FirstName></FirstName><Phone>0698750828</Phone><PhoneMobile></PhoneMobile><Street><![CDATA[121 rue du faubourg du temple etg 2, porte droit]]></Street><Street1><![CDATA[121 rue du faubourg du temple]]></Street1><Street2><![CDATA[etg 2, porte droit]]></Street2><Company><![CDATA[]]></Company><PostalCode>75010</PostalCode><Town><![CDATA[paris]]></Town><Country>FR</Country><Email>vbdels5s4sfpc98p7@marketplace.amazon.fr</Email></BillingAddress>
                   <ShippingAddress><RelayID>1234</RelayID><LastName>moon chan kim</LastName><FirstName></FirstName><Phone>0698750828</Phone><PhoneMobile></PhoneMobile><Street><![CDATA[121 rue du faubourg du temple etg 2, porte droit]]></Street><Street1><![CDATA[121 rue du faubourg du temple]]></Street1><Street2><![CDATA[etg 2, porte droit]]></Street2><Company><![CDATA[]]></Company><PostalCode>75010</PostalCode><Town><![CDATA[paris]]></Town><Country>FR</Country><Email>vbldes5s4sfpc98p7@marketplace.amazon.fr</Email></ShippingAddress>
                   <Products>
                   <Product>
                       <SKU>CLAVIER</SKU>
                       <Quantity>1</Quantity>
                       <Price>8.000000</Price>
                       <Ecotax>0</Ecotax>
                   </Product>
                   </Products>
                 </Order>
            </Orders>
            </Response>
            </Result>';
          */
        
        
        $this->_xml = simplexml_load_string($responseText, 'Varien_Simplexml_Element', LIBXML_NOCDATA);

        
        
        //Mage::log($this->_xml,null,"flux_order.log");
        //Mage::throwException(Mage::helper('profileolabs_shoppingflux')->__('TEST KASSIM'));	


        if (!($this->_xml instanceof Varien_Simplexml_Element)) {
            Mage::log($responseText, null, 'shoppingflux.log');
            Mage::throwException(Mage::helper('profileolabs_shoppingflux')->__("Result is not Varien_Simplexml_Element"));
        } elseif ($this->_xml->error) {
            Mage::throwException(Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux API key (Token) is not valid'));
        }

        /* 	} catch (Exception $e) {
          Mage::throwException($e);
          } */

        return $this->_xml;
    }

    
    public function getMarketplaces() {
        $data = $this->_connect($this->_getApiKey(), self::METHOD_GET_MARKETPLACES);
        return $data->Response->Marketplaces->Marketplace;
    }
    
    /**
     * Retrieve orders
     * 
     */
    public function getOrders() {
        $data = $this->_connect($this->_getApiKey(), self::METHOD_GET_ORDERS);
        return $data->Response->Orders;
    }

    /**
     * Send orders ids imported
     * @param array $orderIds 
     */
    public function sendValidOrders(array $orderIds) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<ValidOrders>';
        foreach($orderIds as $orderId => $orderInfo) {
            if(isset($orderInfo['ErrorOrder']) && $orderInfo['ErrorOrder'] !== false && !$orderInfo['ErrorOrder']) {
                $orderInfo['ErrorOrder'] = 'Une erreur s\'est produite';
            }
            $xml .= '<Order>';
            $xml .= '<IdOrder>' . $orderId . '</IdOrder>';
            $xml .= '<Marketplace>' . $orderInfo['Marketplace'] . '</Marketplace>';
            $xml .= '<MerchantIdOrder>' . $orderInfo['MageOrderId'] . '</MerchantIdOrder>';
            if(isset($orderInfo['ErrorOrder']) && $orderInfo['ErrorOrder']) {
                $xml .= '<ErrorOrder>' . $orderInfo['ErrorOrder'] . '</ErrorOrder>';
            }
            $xml .= '</Order>';
        }
        $xml .= '</ValidOrders>';
        
        
        $dataObj = new Varien_Object(array('xml' => $xml));
        Mage::dispatchEvent('shoppingflux_send_valid_orders', array('data_obj' => $dataObj));
        $xml = $dataObj->getXml();
        return $this->_connect($this->_getApiKey(), self::METHOD_VALIDATE_ORDERS, $xml);
    }

    /**
     * Update orders id shipped
     * @param string $orderId
     */
    public function updateShippedOrder($orderId, $marketplace, $status, $trackNum = '', $trackCarrier = '', $trackUrl = '') {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<UpdateOrders>';
        $xml .= '<Order>';
        $xml .= '<IdOrder>' . $orderId . '</IdOrder>';
        $xml .= '<Marketplace>' . $marketplace . '</Marketplace>';
        $xml .= '<Status>' . $status . '</Status>';
        if ($trackNum && !preg_match('%^http%i', $trackNum)) {
            $xml .= '<TrackingNumber><![CDATA[' . $trackNum . ']]></TrackingNumber>';
        }
        if ($trackUrl) {
            $xml .= '<TrackingUrl><![CDATA[' . $trackUrl . ']]></TrackingUrl>';
        } else if ($trackNum && preg_match('%^http%i', $trackNum)) {
            $xml .= '<TrackingUrl><![CDATA[' . $trackNum . ']]></TrackingUrl>';
        }
        if ($trackCarrier) {
            $xml .= '<CarrierName><![CDATA[' . $trackCarrier . ']]></CarrierName>';
        }
        $xml .= '</Order>';
        $xml .= '</UpdateOrders>';
        $dataObj = new Varien_Object(array('xml' => $xml));
        Mage::dispatchEvent('shoppingflux_update_shipped_orders', array('data_obj' => $dataObj));
        $xml = $dataObj->getXml();
        return $this->_connect(
                $this->_getApiKey(), self::METHOD_UPDATE_ORDERS, $xml
        );
    }
    
    public function updateCanceledOrder($orderId, $marketplace, $status) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<UpdateOrders>';
        $xml .= '<Order>';
        $xml .= '<IdOrder>' . $orderId . '</IdOrder>';
        $xml .= '<Marketplace>' . $marketplace . '</Marketplace>';
        $xml .= '<Status>' . $status . '</Status>';
        $xml .= '</Order>';
        $xml .= '</UpdateOrders>';
        $dataObj = new Varien_Object(array('xml' => $xml));
        Mage::dispatchEvent('shoppingflux_update_canceled_orders', array('data_obj' => $dataObj));
        $xml = $dataObj->getXml();
        return $this->_connect(
                $this->_getApiKey(), self::METHOD_UPDATE_ORDERS, $xml
        );
    }
    
    
    public function updateProducts($updates) {
        if($updates->getSize() <=0) return;
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<UpdateProduct>';
        foreach($updates as $update) {
            $xml .= '<Product>';
            $xml .= '<SKU>' . $update->getProductSku() . '</SKU>';
            $xml .= '<Quantity>' . $update->getStockValue() . '</Quantity>';
            $xml .= '<Price>' . $update->getPriceValue() . '</Price>';
            $xml .= '<OldPrice>' . $update->getOldPriceValue() . '</OldPrice>';
            $xml .= '</Product>';
        }
        $xml .= '</UpdateProduct>';
        $this->_connect(
                $this->_getApiKey(), self::METHOD_UPDATE_PRODUCT, $xml
        );
    }

    /**
     * Retrieve login
     * @param string|null $apiKey
     */
    public function getLogin($apiKey = null) {
        if (is_null($apiKey))
            $apiKey = $this->_getApiKey();

        return $this->_connect($apiKey, self::METHOD_LOGIN);
    }

    /**
     * Check if ApiKey is valid
     * @param string $apiKey
     */
    public function checkApiKey($apiKey) {
        if ($this->getLogin($apiKey)->error)
            return false;

        return true;
    }
    
    
    public function isClient() {
        try {
            $res = $this->_connect(
                    $this->_getApiKey(), self::METHOD_IS_CLIENT
            );
            $status = (string) $res->Response->Status;
            if($status == 'Client') {
                if(!Mage::getStoreConfigFlag('shoppingflux/configuration/has_registered')) {
                    $config = new Mage_Core_Model_Config();
                    $config->saveConfig('shoppingflux/configuration/has_registered', 1);
                }
                return true;
            }
        }catch(Exception $e) {
            if($e->getMessage() == 'API Key (Token) is empty') {
                return false;
            }
            return true;
        }
        return false;
    }

}