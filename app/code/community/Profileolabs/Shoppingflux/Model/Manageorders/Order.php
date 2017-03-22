<?php

/**
 * Orders getted here
 * 
 * @category ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait, vincent enjalbert @ web-cooking
 *
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Order extends Varien_Object {

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Mage_Customer_Model_Customer
     */
    protected $_customer = null;

    /**
     * Config Data of Module Manageorders
     * @var Profileolabs_Shoppingflux_Model_Manageorders_Config
     */
    protected $_config = null;
    protected $_paymentMethod = 'shoppingflux_purchaseorder';
    protected $_shippingMethod = 'shoppingflux_shoppingflux';
    protected $_nb_orders_imported = 0;
    protected $_nb_orders_read = 0;
    protected $_ordersIdsImported = array();
    //protected $_orderIdsAlreadyImported = null;
    protected $_result;
    protected $_resultSendOrder = "";
    protected $_isUnderVersion14 = null;
    
    //security for some mal-configured magento, or old ones.
    protected $_excludeConfigurableAttributes = array(
        'weight', 
        'news_from_date', 
        'news_to_date', 
        'url_key', 
        'sku', 
        'description', 
        'short_description', 
        'meta_title', 
        'meta_description', 
        'meta_keyword', 
        'name', 
        'tax_class_id',
        'status',
        'price',
        'special_price',
        'special_from_date',
        'special_to_date',
        'cost',
        'image',
        'small_image',
        'thumbnail',
        'status',
        'visibility',
        'custom_design_from',
        'options_container',
        'msrp_enabled',
        'msrp_display_actual_price_type',
        'shoppingflux_default_category',
        'shoppingflux_product',
        'main_category'
        );

    /**
     * Product model
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_productModel;

    public function getResultSendOrder() {
        return $this->_resultSendOrder;
    }

    public function isUnderVersion14() {
        if (is_null($this->_isUnderVersion14)) {
            $this->_isUnderVersion14 = $this->getHelper()->isUnderVersion14();
        }
        return $this->_isUnderVersion14;
    }

    /**
     * Retrieve product model cache
     *
     * @return Mage_Catalog_Model_Product
     */
    public function getProductModel() {
        if (is_null($this->_productModel)) {
            $productModel = Mage::getModel('profileolabs_shoppingflux/manageorders_product');
            $this->_productModel = Mage::objects()->save($productModel);
        }
        return Mage::objects()->load($this->_productModel);
    }

    /**
     * 
     * @deprecated
     */
    public function getOrderIdsAlreadyImported() {
        if (is_null($this->_orderIdsAlreadyImported)) {
            $orders = Mage::getModel('sales/order')->getCollection()
                    ->addAttributeToFilter('from_shoppingflux', 1)
                    ->addAttributeToSelect('order_id_shoppingflux');

            $this->_orderIdsAlreadyImported = array();
            foreach ($orders as $order) {
                $this->_orderIdsAlreadyImported[] = $order->getOrderIdShoppingflux();
            }
        }

        return $this->_orderIdsAlreadyImported;
    }

   

    public function isAlreadyImported($idShoppingflux) {
        $orders = Mage::getModel('sales/order')->getCollection()
                ->addAttributeToFilter('from_shoppingflux', 1)
                ->addAttributeToFilter('order_id_shoppingflux', $idShoppingflux)
                ->addAttributeToSelect('increment_id');
        if($orders->count() > 0) {
           return $orders->getFirstItem();
        }
        
        /* Double vérification, pour gérer un appel simultané. (qui ne devrait pas arriver, mais au cas ou..) */
        $config = new Mage_Core_Model_Config();
        $flagPath = Mage::getStoreConfig('shoppingflux/order_flags/order_'.$idShoppingflux);
        if ($flagPath) { 
            $config->saveConfig('shoppingflux/order_flags/order_'.$idShoppingflux, 0);
            return true;
        }
        $config->saveConfig('shoppingflux/order_flags/order_'.$idShoppingflux, date('Y-m-d H:i:s'));
        /* end double check */

        return false;
    }

    public function getSession() {
        return Mage::getSingleton('checkout/session');
    }

    protected function _getQuote($storeId = null) {
        return $this->getSession()->getQuote();
    }

    /**
     * Retrieve config
     * @return Profileolabs_Shoppingflux_Model_Manageorders_Config
     */
    public function getConfig() {
        if (is_null($this->_config)) {
            $this->_config = Mage::getSingleton('profileolabs_shoppingflux/config');
        }

        return $this->_config;
    }

    /**
     * Get orders and create it
     */
    public function manageOrders() {
        //Set boolean shopping flux
        Mage::register('is_shoppingfeed_import', 1, true);
        
        
        //compatibility with AutoShipping extension
        Mage::app()->getStore()->setConfig('autoshipping/settings/enabled', "0");
        
        $stores = Mage::app()->getStores();

        $storeCode = Mage::app()->getStore()->getCode();
        $isAdmin = ($storeCode == 'admin');
        if (!$isAdmin) {
            Mage::app()->setCurrentStore('admin');
        }

        $apiKeyManaged = array();

        //old module version compliance. The goal is to use default store, as in previous versions, if api key is set in global scope.
        $defaultStoreId = Mage::app()->getDefaultStoreView()->getId();
        if (key($stores) != $defaultStoreId) {
            $tmpStores = array($defaultStoreId => $stores[$defaultStoreId]);
            foreach ($stores as $store) {
                if ($store->getId() != $defaultStoreId) {
                    $tmpStores[$store->getId()] = $store;
                }
            }
            $stores = $tmpStores;
        }
        //old module version compliance end


        foreach ($stores as $_store) {
            if(!$isAdmin && $storeCode != $_store->getCode()) {
                continue;
            }
            $storeId = $_store->getId();
            if ($this->getConfig()->isOrdersEnabled($storeId)) {
                $apiKey = $this->getConfig()->getApiKey($storeId);

                if (!$apiKey || in_array($apiKey, $apiKeyManaged))
                    continue;
                $apiKeyManaged[] = $apiKey;

                $wsUri = $this->getConfig()->getWsUri();

                //$isUnderVersion14 = $this->getHelper()->isUnderVersion14();	

                /* @var $service Profileolabs_Shoppingflux_Model_Service */
                $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
                ini_set("memory_limit", $this->getConfig()->getMemoryLimit() . "M");
                try {

                    /* @var $this->_result Varien_Simplexml_Element */
                    $this->_result = $service->getOrders();
                    
                    $this->_nb_orders_imported = 0;
                } catch (Exception $e) {
                    Mage::logException($e);
                    $message = Mage::helper('profileolabs_shoppingflux')->__('Order import error : %s', $e->getMessage());
                    $this->getHelper()->log($message);
                    //Mage::throwException($message);

                    Mage::throwException($e);
                }

                //We parse result
                //$nodes = current($this->_result->children());
                $nodes = $this->_result->children();
                if($nodes && count($nodes) > 0) {
                    foreach ($nodes as $childName => $child) {

                        $orderSf = $this->getHelper()->asArray($child);


                       if (($importedOrder = $this->isAlreadyImported($orderSf['IdOrder']))) {
                           $this->_ordersIdsImported[$orderSf['IdOrder']] = array(
                                'Marketplace' => $orderSf['Marketplace'], 
                                'MageOrderId' => is_object($importedOrder)?$importedOrder->getIncrementId():'', 
                                'ShippingMethod' => $orderSf['ShippingMethod'],
                                'ErrorOrder' => is_object($importedOrder)?false:true
                           );
                           continue;
                       }

                        $this->_nb_orders_read++;

                        $this->createAllForOrder($orderSf, $storeId);

                        if ($this->_nb_orders_imported == $this->getConfig()->getLimitOrders($storeId))
                            break;
                    }
                }
            
                try {
                    if ($this->_nb_orders_imported > 0 || count($this->_ordersIdsImported)) {

                        $result = $service->sendValidOrders($this->_ordersIdsImported);
                        foreach($this->_ordersIdsImported as $importedOrder) {
                            $shippingMethod = isset($importedOrder['ShippingMethod'])?$importedOrder['ShippingMethod']:'';
                            $marketplace = $importedOrder['Marketplace'];
                            try {
                                Mage::getModel('profileolabs_shoppingflux/manageorders_shipping_method')->saveShippingMethod($marketplace, $shippingMethod);
                            } catch(Exception $e) {
                                
                            }
                        }


                        if ($result) {
                            if ($result->error) {
                                Mage::throwException($result->error);
                            }

                            $this->_resultSendOrder = $result->status;
                        } else {
                            $this->getHelper()->log("Error in order ids validated");
                            Mage::throwException("Error in order ids validated");
                        }
                    }
                } catch (Exception $e) {
                    $this->getHelper()->log($e->getMessage());
                    Mage::throwException($e);
                }
            }
        }
        $this->clearOldOrderFlagFiles();
        return $this;
    }
    
    public function clearOldOrderFlagFiles() {
        $config = new Mage_Core_Model_Config();
        $orderFlags = Mage::getStoreConfig('shoppingflux/order_flags');
        if(!$orderFlags || empty($orderFlags)) {
            return;
        }
        foreach($orderFlags as $orderId => $importDate) {
            if(strtotime($importDate) < time()-3*60*60) {
                $config->deleteConfig('shoppingflux/order_flags/' . $orderId);//retro-compatibility.
                $config->deleteConfig('shoppingflux/order_flags/order_' . $orderId);
            }
        }
    }

    /**
     * Inititalize the quote with minimum requirement
     * @param array $orderSf
     */
    protected function _initQuote(array $orderSf, $storeId) {

        if (is_null($storeId)) {//just in case.. 
            $storeId = Mage::app()->getDefaultStoreView()->getId();
        }

        $this->_getQuote()->setStoreId($storeId);

        //Super mode is setted to bypass check item qty ;)
        $this->_getQuote()->setIsSuperMode(true);
       
        $this->_getQuote()->setCustomer($this->_customer);
    }

    /**
     * Create or Update customer with converter
     * @param array $data Data From ShoppingFlux
     */
    protected function _createCustomer(array $data, $storeId) {
        try {

            /* @var $convert_customer Profileolabs_Shoppingflux_Model_Manageorders_Convert_Customer */
            $convert_customer = Mage::getModel('profileolabs_shoppingflux/manageorders_convert_customer');

            $this->_customer = $convert_customer->toCustomer(current($data['BillingAddress']), $storeId);
            $billingAddress = $convert_customer->addresstoCustomer(current($data['BillingAddress']), $storeId, $this->_customer);

            $this->_customer->addAddress($billingAddress);

            $shippingAddress = $convert_customer->addresstoCustomer(current($data['ShippingAddress']), $storeId, $this->_customer, 'shipping');
            $this->_customer->addAddress($shippingAddress);
            $customerGroupId = $this->getConfig()->getCustomerGroupIdFor($data['Marketplace'], $storeId);
            if ($customerGroupId) {
                $this->_customer->setGroupId($customerGroupId);
            }
            $this->_customer->save();
        } catch (Exception $e) {
            Mage::throwException($e);
        }
    }

    public function createAllForOrder($orderSf, $storeId) {
        try {
            $orderIdShoppingFlux = (string) $orderSf['IdOrder'];
            
            $dataObj = new Varien_Object(array('entry' => $orderSf, 'store_id'=>$storeId));
            Mage::dispatchEvent('shoppingflux_before_import_order', array('order_sf' => $dataObj));
            $orderSf = $dataObj->getEntry();
            
            //Set array with shopping flux ids
            $this->_ordersIdsImported[$orderIdShoppingFlux] = array(
                'Marketplace' => $orderSf['Marketplace'], 
                'MageOrderId' => '', 
                'ShippingMethod' => $orderSf['ShippingMethod'],
                'ErrorOrder' => false
            );
            
            //$this->_quote = null;
            $this->_customer = null;


            //Create or Update customer with addresses
            $this->_createCustomer($orderSf, $storeId);

            $this->_initQuote($orderSf, $storeId);
            
            if(Mage::registry('current_order_sf')) {
                Mage::unregister('current_order_sf');
            }
            Mage::register('current_order_sf', $orderSf);
            
            if(Mage::registry('current_quote_sf')) {
                Mage::unregister('current_quote_sf');
            }
            Mage::register('current_quote_sf', $this->_getQuote());

            //Add products to quote with data from ShoppingFlux
            $this->_addProductsToQuote($orderSf, $storeId);

            $order = null;
            if (!$this->isUnderVersion14())
                $order = $this->_saveOrder($orderSf, $storeId);
            else
                $order = $this->_saveOrder13($orderSf, $storeId);

            if($order) {
                $this->getHelper()->log('Order ' . $orderSf['IdOrder'] . ' has been created (' . $order->getIncrementId() . ' / ' . Mage::app()->getStore()->getId() . ')');
                $this->_nb_orders_imported++;

                if (!is_null($order) && $order->getId()) {
                    $useMarketplaceDate = $this->getConfig()->getConfigFlag('shoppingflux_mo/manageorders/use_marketplace_date');
                    //$orderDate = date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time()));
                    if($useMarketplaceDate) {
                        $orderDate = $orderSf['OrderDate'];
                        $this->_changeDateCreatedAt($order, $orderDate);
                    }
                }

            }
            //Erase session for the next order
            $this->getSession()->clear();
        } catch (Exception $e) {
            $this->getHelper()->log($e->getMessage() . ' Trace : ' . $e->getTraceAsString(), $orderSf['IdOrder']);
            $this->_ordersIdsImported[$orderIdShoppingFlux]['ErrorOrder'] = $e->getMessage();
            //$this->clearOrderFlagFile($orderSf['IdOrder']);
            //Erase session for the next order
            $this->getSession()->clear();
        }
    }

    protected function _changeDateCreatedAt($order, $date) {
        try {
            $order->setCreatedAt($date);
            //$order->setUpdatedAt($date);
            $order->save();
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($message);
        }
    }

    /**
     * Add products to quote with data from ShoppinfFlux
     * @param array $orderSf
     */
    protected function _addProductsToQuote(array $orderSf, $storeId) {
        $totalAmount = $orderSf['TotalAmount'];
        $productsSf = current($orderSf['Products']);
        $productsToIterate = current($productsSf);




        if (!$this->_customer->getDefaultBilling() || !$this->_customer->getDefaultShipping() || (is_object($this->_customer->getDefaultShipping()) && !$this->_customer->getDefaultShipping()->getFirstname()))
            $this->_customer->load($this->_customer->getId());

        $customerAddressBillingId = $this->_customer->getDefaultBilling();
        $customerAddressShippingId = $this->_customer->getDefaultShipping();

        //Set billing Address
        $addressBilling = $this->_getQuote()->getBillingAddress();
        //Make sure addresses will be saved without validation errors
        $addressBilling->setShouldIgnoreValidation(true);
        $customerAddressBilling = Mage::getModel('customer/address')->load($customerAddressBillingId);
        $addressBilling->importCustomerAddress($customerAddressBilling)->setSaveInAddressBook(0);

        //Set shipping Address
        $addressShipping = $this->_getQuote()->getShippingAddress();
        //Make sure addresses will be saved without validation errors
        $addressShipping->setShouldIgnoreValidation(true);
        $customerAddressShipping = Mage::getModel('customer/address')->load($customerAddressShippingId);
        $addressShipping->importCustomerAddress($customerAddressShipping)->setSaveInAddressBook(0);
        $addressShipping->setSameAsBilling(0);


        

        //Set shipping Mehtod and collect shipping rates
        $addressShipping->setShippingMethod($this->_shippingMethod)->setCollectShippingRates(true);




        foreach ($productsToIterate as $key => $productSf) {

            $sku = $productSf['SKU'];
            $qtyIncrements = 1;
            if(preg_match('%^_SFQI_([0-9]+)_(.*)$%i', $sku, $pregResults)) {
                $sku = $pregResults[2];
                $qtyIncrements = $pregResults[1];
            }
            
            $useProductId = $this->getConfig()->getConfigData('shoppingflux_mo/manageorders/use_product_id', $storeId);
            
            if($useProductId) {
                $productId = $sku;
            } else {
                $productId = $this->getProductModel()->getResource()->getIdBySku($sku);
            }

            $product = Mage::getModel('profileolabs_shoppingflux/manageorders_product')->setStoreId($storeId)->load($productId);

            if ($product->getId()) {
                
                $request = new Varien_Object(array('qty' => $productSf['Quantity'] * $qtyIncrements));
               


                $item = $this->_getQuote()->addProduct($product, $request);

                if (!is_object($item)) {
                    $this->getSession()->clear();
                    Mage::throwException("le produit sku = " . $sku . " n'a pas pu être ajouté! Id = " . $product->getId() . " Item = " . (string) $item);
                }


                //Save the quote with the new product
                $this->_getQuote()->save();


                $unitPrice = $productSf['Price'] / $qtyIncrements;
                if($unitPrice <= 0) {
                    $this->getHelper()->log('Order '.$orderSf['IdOrder'].' has a product with 0 price : '.$productSf['SKU']);
                }
                if ($this->getConfig()->applyTax() && !Mage::helper('tax')->priceIncludesTax()) {
                    $taxClassId = $product->getTaxClassId();
                    if($taxClassId > 0) {
                        $request = Mage::getSingleton('tax/calculation')->getRateRequest($addressShipping, $addressBilling, null, null);
                        $request->setProductClassId($taxClassId);
                        $request->setCustomerClassId($this->_getQuote()->getCustomerTaxClassId());
                        $percent = Mage::getSingleton('tax/calculation')->getRate($request);
                        $unitPrice = $unitPrice / (1 + $percent / 100);
                         if($unitPrice <= 0) {
                            $this->getHelper()->log('Order '.$orderSf['IdOrder'].' has a product with 0 price after applying tax : '.$productSf['SKU']);
                        }
                    }
                }

                
               

                //Modify Item price
                $item->setCustomPrice($unitPrice);
                $item->setOriginalCustomPrice($unitPrice);
                
                
                //add configurable attributes informations
                $confAttributeValues = array();
                if($this->isUnderVersion14()) {
                    $configurableAttributesCollection =  Mage::getResourceModel('eav/entity_attribute_collection')
                        ->setEntityTypeFilter( Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId() );
                } else {
                    $configurableAttributesCollection = Mage::getResourceModel('catalog/product_attribute_collection');
                }
                $configurableAttributesCollection->addVisibleFilter()
                    ->addFieldToFilter('is_configurable', 1);
                

                foreach($configurableAttributesCollection as $confAttribute) {
                    if(!in_array($confAttribute->getAttributeCode(), $this->_excludeConfigurableAttributes) && $product->getData($confAttribute->getAttributeCode())) {
                        if($confAttribute->usesSource()) {
                            $confAttributeValue = $product->getAttributeText($confAttribute->getAttributeCode());
                        } else {
                            $confAttributeValue = $product->getData($confAttribute->getAttributeCode());
                        }
                       if(is_string($confAttributeValue)) {
                            $confAttributeValues[] = $confAttributeValue;
                       }
                    }
                }
                if(!empty($confAttributeValues)) {
                    $item->setDescription($item->getDescription() . implode(' - ', $confAttributeValues));
                }
                
                $item->save();

                if (is_object($parentItem = $item->getParentItem())) {
                    $parentItem->setCustomPrice($unitPrice);
                    $parentItem->setOriginalCustomPrice($unitPrice);
                    $parentItem->save();
                }

                //Mage::log(print_r($item->debug(),true),null,'debug_items.log');
            } else {

                $this->getSession()->clear();
                Mage::throwException("le produit sku = '" . $sku . "' (ID= ".$productId.", Utilisation id = ".($useProductId?'Oui':'Non').") n'existe plus en base!");
            }
        }

        try {
            $this->_getQuote()->collectTotals();
            $this->_getQuote()->save();
        } catch(Exception $e) {
            if($e->getMessage() == Mage::helper('sales')->__('Please specify a shipping method.')) {
                $this->_getQuote()->getShippingAddress()->setShippingMethod($this->_shippingMethod)->setCollectShippingRates(true);
                $this->_getQuote()->setTotalsCollectedFlag(false)->collectTotals();
                $this->_getQuote()->save();
            } else {
                throw $e;
            }
        }

        //Set payment method
        /* @var $payment Mage_Sales_Quote_Payment */
        $this->_getQuote()->getShippingAddress()->setPaymentMethod($this->_paymentMethod);
        $payment = $this->_getQuote()->getPayment();
        $dataPayment = array('method' => $this->_paymentMethod, 'marketplace' => $orderSf['Marketplace']);
        $payment->importData($dataPayment);
        //$addressShipping->setShippingMethod($this->_shippingMethod)->setCollectShippingRates(true);

        try {
            $this->_getQuote()->collectTotals();
            $this->_getQuote()->save();
        } catch(Exception $e) {
            if($e->getMessage() == Mage::helper('sales')->__('Please specify a shipping method.')) {
                $this->_getQuote()->getShippingAddress()->setShippingMethod($this->_shippingMethod)->setCollectShippingRates(true);
                
                $this->_getQuote()->getShippingAddress()->setPaymentMethod($this->_paymentMethod);
                $payment = $this->_getQuote()->getPayment();
                $dataPayment = array('method' => $this->_paymentMethod, 'marketplace' => $orderSf['Marketplace']);
                $payment->importData($dataPayment);
                
                $this->_getQuote()->setTotalsCollectedFlag(false)->collectTotals();
                $this->_getQuote()->save();
            } else {
                throw $e;
            }
        }
    }

 
    /**
     * Save the new order with the quote
     * @param array $orderSf
     */
    protected function _saveOrder(array $orderSf, $storeId) {
        $orderIdShoppingFlux = (string) $orderSf['IdOrder'];
        $additionalData = array("from_shoppingflux" => 1,
            "marketplace_shoppingflux" => $orderSf['Marketplace'],
            "fees_shoppingflux" => (float) (isset($orderSf['TotalFees']) ? $orderSf['TotalFees'] : 0),
            "other_shoppingflux" => $orderSf['Other'],
            "order_id_shoppingflux" => $orderIdShoppingFlux,
            "grand_total" => $orderSf['TotalAmount'],
            "base_grand_total" => $orderSf['TotalAmount'],
            "order_currency_code" => isset($orderSf['Currency'])?$orderSf['Currency']:'EUR',
            "base_currency_code" => isset($orderSf['Currency'])?$orderSf['Currency']:'EUR',
            "store_currency_code" => isset($orderSf['Currency'])?$orderSf['Currency']:'EUR',
        
            );
        
        if(isset($orderSf['ShippingAddress'][0]['RelayID']) && $orderSf['ShippingAddress'][0]['RelayID']) {
            if($additionalData['other_shoppingflux']) {
                $additionalData['other_shoppingflux'] .= '<br/>';
            }
            $additionalData['other_shoppingflux'] .= 'Relay ID : ' . $orderSf['ShippingAddress'][0]['RelayID'];
        }

        $shippingMethod = $this->getConfig()->getShippingMethodFor($orderSf['Marketplace'], $orderSf['ShippingMethod'], $storeId);
        if($shippingMethod) {
            $additionalData['shipping_method'] = $shippingMethod;
            $additionalData['shipping_description'] = "Frais de port de la place de marché (" . $shippingMethod . ")";
        }
        /* @var $service Mage_Sales_Model_Service_Quote */
        $quote = $this->_getQuote();
        $service = Mage::getModel('sales/service_quote', $this->_getQuote());
        $service->setOrderData($additionalData);
        $order = false;
        
        ini_set('display_errors', 1);
        error_reporting(-1);
        
        try {
            if (method_exists($service, "submitAll")) {

                $service->submitAll();
                $order = $service->getOrder();
            } else {

                $order = $service->submit();
            }
        } catch(Exception $e) {
            throw $e;
        }
        try {
            $quote->setIsActive(0)->setUpdatedAt(date('Y-m-d H:i:s', strtotime('-1 year')))->save();
        } catch (Exception $ex) {

        }
        
        if ($order) {
            
            $newStatus = $this->getConfig()->getConfigData('shoppingflux_mo/manageorders/new_order_status', $order->getStoreId());
            if ($newStatus) {
                $order->setStatus($newStatus);
                $order->save();
            }



            $this->_saveInvoice($order);

       
            $processingStatus = $this->getConfig()->getConfigData('shoppingflux_mo/manageorders/processing_order_status', $order->getStoreId());
            if ($processingStatus && $order->getState() == 'processing') {
                $order->setStatus($processingStatus);
                $order->save();
            }
            
            foreach($order->getAllItems() as $orderItem) {
                if($orderItem->getWeeeTaxAppliedRowAmount()) {
                    /*Mage::log('------order : ' . $order->getIncrementId(). '--------START', null, 'sf.debug.weee.log');
                    Mage::log('------order : ' . $order->getIncrementId(). '---BEFORE', null, 'sf.debug.weee.log');
                    Mage::log('row_total_incl_tax : ' . $orderItem->getData('row_total_incl_tax'), null, 'sf.debug.weee.log');
                    Mage::log('base_row_total_incl_tax : ' . $orderItem->getData('base_row_total_incl_tax'), null, 'sf.debug.weee.log');
                    Mage::log('row_total : ' . $orderItem->getData('row_total'), null, 'sf.debug.weee.log');
                    Mage::log('base_row_total : ' . $orderItem->getData('base_row_total'), null, 'sf.debug.weee.log');
                    Mage::log('price : ' . $orderItem->getData('price'), null, 'sf.debug.weee.log');
                    Mage::log('base_price : ' . $orderItem->getData('base_price'), null, 'sf.debug.weee.log');
                    Mage::log('weee_tax_applied_row_amount : ' . $orderItem->getData('weee_tax_applied_row_amount'), null, 'sf.debug.weee.log');
                    Mage::log('base_weee_tax_applied_row_amnt : ' . $orderItem->getData('base_weee_tax_applied_row_amnt'), null, 'sf.debug.weee.log');
                    Mage::log('weee_tax_applied_amount : ' . $orderItem->getData('weee_tax_applied_amount'), null, 'sf.debug.weee.log');
                    Mage::log('base_weee_tax_applied_amount : ' . $orderItem->getData('base_weee_tax_applied_amount'), null, 'sf.debug.weee.log');*/
                    if($orderItem->getData('row_total_incl_tax')) {
                        $orderItem->setData('row_total_incl_tax', $orderItem->getData('row_total_incl_tax') - $orderItem->getWeeeTaxAppliedRowAmount());
                    }
                    if($orderItem->getData('base_row_total_incl_tax')) {
                        $orderItem->setData('base_row_total_incl_tax', $orderItem->getData('base_row_total_incl_tax') - $orderItem->getBaseWeeeTaxAppliedRowAmnt());
                    }
                    $orderItem->setData('row_total', $orderItem->getData('row_total') - $orderItem->getWeeeTaxAppliedRowAmount());
                    $orderItem->setData('base_row_total', $orderItem->getData('base_row_total') - $orderItem->getBaseWeeeTaxAppliedRowAmnt());
                    $orderItem->setData('price', $orderItem->getData('price') - $orderItem->getWeeeTaxAppliedAmount());
                    $orderItem->setData('base_price', $orderItem->getData('base_price') - $orderItem->getBaseWeeeTaxAppliedAmount());
                    $orderItem->save();
                    
                    /*Mage::log('------order : ' . $order->getIncrementId(). '---AFTER', null, 'sf.debug.weee.log');
                    Mage::log('row_total_incl_tax : ' . $orderItem->getData('row_total_incl_tax'), null, 'sf.debug.weee.log');
                    Mage::log('base_row_total_incl_tax : ' . $orderItem->getData('base_row_total_incl_tax'), null, 'sf.debug.weee.log');
                    Mage::log('row_total : ' . $orderItem->getData('row_total'), null, 'sf.debug.weee.log');
                    Mage::log('base_row_total : ' . $orderItem->getData('base_row_total'), null, 'sf.debug.weee.log');
                    Mage::log('price : ' . $orderItem->getData('price'), null, 'sf.debug.weee.log');
                    Mage::log('base_price : ' . $orderItem->getData('base_price'), null, 'sf.debug.weee.log');
                    Mage::log('------order : ' . $order->getIncrementId(). '--------STOP', null, 'sf.debug.weee.log');*/
                    
                }
            }

            
            //$this->_orderIdsAlreadyImported[] = $orderIdShoppingFlux;
            $this->_ordersIdsImported[$orderIdShoppingFlux]['MageOrderId'] = $order->getIncrementId();
            
            //if(Mage::helper('sales')->canSendNewOrderEmail()) {
                //$order->sendNewOrderEmail();
            //}
            
            return $order;
        }

        return null;
    }

    protected function _saveOrder13(array $orderSf, $storeId) {
        $orderIdShoppingFlux = (string) $orderSf['IdOrder'];
        $additionalData = array("from_shoppingflux" => 1,
            "marketplace_shoppingflux" => $orderSf['Marketplace'],
            "fees_shoppingflux" => (float) (isset($orderSf['Fees']) ? $orderSf['Fees'] : 0.0),
            "other_shoppingflux" => $orderSf['Other'],
            "order_id_shoppingflux" => $orderIdShoppingFlux);


        $billing = $this->_getQuote()->getBillingAddress();
        $shipping = $this->_getQuote()->getShippingAddress();

        $this->_getQuote()->reserveOrderId();
        $convertQuote = Mage::getModel('sales/convert_quote');
        /* @var $convertQuote Mage_Sales_Model_Convert_Quote */

        $order = $convertQuote->addressToOrder($shipping);

        $order->addData($additionalData);

        /* @var $order Mage_Sales_Model_Order */
        $order->setBillingAddress($convertQuote->addressToOrderAddress($billing));
        $order->setShippingAddress($convertQuote->addressToOrderAddress($shipping));

        $order->setPayment($convertQuote->paymentToOrderPayment($this->_getQuote()->getPayment()));

        foreach ($this->_getQuote()->getAllItems() as $item) {
            $orderItem = $convertQuote->itemToOrderItem($item);
            if ($item->getParentItem()) {
                $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
            }
            $order->addItem($orderItem);
        }

        /**
         * We can use configuration data for declare new order status
         */
        Mage::dispatchEvent('checkout_type_onepage_save_order', array('order' => $order, 'quote' => $this->getQuote()));
        //Mage::throwException(print_r($order->getData(),true));
        //die("<pre> DIE = ".print_r($order->getData()));
        $order->place();

        $order->setCustomerId($this->_getQuote()->getCustomer()->getId());

        $order->setEmailSent(false);
        $order->save();

        Mage::dispatchEvent('checkout_type_onepage_save_order_after', array('order' => $order, 'quote' => $this->getQuote()));

        $this->_getQuote()->setIsActive(false);
        $this->_getQuote()->save();

        ///////////////////////////////////////////////////////////////////////////

        if ($order) {

            $this->_saveInvoice($order);

            //Set array with shopping flux ids
            $this->_ordersIdsImported[$orderIdShoppingFlux] = array('Marketplace' => $orderSf['Marketplace'], 'MageOrderId' => $order->getIncrementId());

            return $order;
        }

        return null;
    }

    /**
     * Create and Save invoice for the new order
     * @param Mage_Sales_Model_Order $order
     */
    protected function _saveInvoice($order) {
        Mage::dispatchEvent('checkout_type_onepage_save_order_after', array('order' => $order, 'quote' => $this->_getQuote()));

        if (!$this->getConfig()->createInvoice($order->getStoreId())) {
            return $this;
        }

        //Prepare invoice and save it
        $path = Mage::getBaseDir() . "/app/code/core/Mage/Sales/Model/Service/Order.php";
        $invoice = false;
        if (file_exists($path)) {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        } else {
            $invoice = $this->_initInvoice($order);
        }

        if ($invoice) {
            $invoice->setBaseGrandTotal($order->getBaseGrandTotal());
            $invoice->setGrandTotal($order->getGrandTotal());
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);


            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
            $transactionSave->save();
        }
    }

    /**
     * Initialize invoice
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice $invoice
     */
    protected function _initInvoice($order) {

        $convertor = Mage::getModel('sales/convert_order');
        $invoice = $convertor->toInvoice($order);
        $update = false;
        $savedQtys = array();
        $itemsToInvoice = 0;
        /* @var $orderItem Mage_Sales_Model_Order_Item */
        foreach ($order->getAllItems() as $orderItem) {

            if (!$orderItem->isDummy() && !$orderItem->getQtyToInvoice() && $orderItem->getLockedDoInvoice()) {
                continue;
            }

            if ($order->getForcedDoShipmentWithInvoice() && $orderItem->getLockedDoShip()) {
                continue;
            }

            if (!$update && $orderItem->isDummy() && !empty($savedQtys) && !$this->_needToAddDummy($orderItem, $savedQtys)) {
                continue;
            }
            $item = $convertor->itemToInvoiceItem($orderItem);

            if (isset($savedQtys[$orderItem->getId()])) {
                $qty = $savedQtys[$orderItem->getId()];
            } else {
                if ($orderItem->isDummy()) {
                    $qty = 1;
                } else {
                    $qty = $orderItem->getQtyToInvoice();
                }
            }
            $itemsToInvoice += floatval($qty);
            $item->setQty($qty);
            $invoice->addItem($item);

            if ($itemsToInvoice <= 0) {
                Mage::throwException($this->__('Invoice could not be created (no items).'));
            }
        }


        $invoice->collectTotals();

        return $invoice;
    }

    /**
     * Get Helper
     * @return Profileolabs_Shoppingflux_Model_Manageorders_Helper_Data
     */
    public function getHelper() {
        return Mage::helper('profileolabs_shoppingflux');
    }

    public function getNbOrdersImported() {
        return $this->_nb_orders_imported;
    }

}