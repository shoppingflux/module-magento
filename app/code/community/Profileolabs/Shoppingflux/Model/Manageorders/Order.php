<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Order extends Varien_Object
{
    const LOCK_CODE = 'shopping_feed_order_import';

    /**
     * @var Mage_Sales_Model_Quote|null
     */
    protected $_quote = null;

    /**
     * @var Mage_Customer_Model_Customer|null
     */
    protected $_customer = null;

    /**
     * @var Profileolabs_Shoppingflux_Model_Config
     */
    protected $_config = null;

    /**
     * @var string
     */
    protected $_paymentMethod = 'shoppingflux_purchaseorder';

    /**
     * @var string
     */
    protected $_shippingMethod = 'shoppingflux_shoppingflux';

    /**
     * @var int
     */
    protected $_readOrderCount = 0;

    /**
     * @var int|null
     */
    protected $_importedOrderCount = null;

    /**
     * @var array
     */
    protected $_importedOrders = array();

    /**
     * @var string
     */
    protected $_sentOrdersResult = '';

    /**
     * @var string[]
     */
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
        'main_category',
    );

    /**
     * @var Mage_Catalog_Model_Product
     */
    protected $_productModel = null;

    /**
     * @return Profileolabs_Shoppingflux_Model_Config
     */
    public function getConfig()
    {
        if ($this->_config === null) {
            $this->_config = Mage::getSingleton('profileolabs_shoppingflux/config');
        }

        return $this->_config;
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @return int|null
     */
    public function getImportedOrderCount()
    {
        return $this->_importedOrderCount;
    }

    /**
     * @return string
     */
    public function getSentOrdersResult()
    {
        return $this->_sentOrdersResult;
    }

    /**
     * @return Profileolabs_Shoppingflux_Model_Manageorders_Product
     */
    public function getProductModel()
    {
        if ($this->_productModel === null) {
            $productModel = Mage::getModel('profileolabs_shoppingflux/manageorders_product');
            $this->_productModel = Mage::objects()->save($productModel);
        }

        return Mage::objects()->load($this->_productModel);
    }

    /**
     * @param string $shoppingfluxId
     * @return Mage_Sales_Model_Order|bool
     */
    public function isAlreadyImported($shoppingfluxId)
    {
        /** @var Mage_Sales_Model_Resource_Order_Collection $orders */
        $orders = Mage::getResourceModel('sales/order_collection');
        $orders->addAttributeToFilter('from_shoppingflux', 1);
        $orders->addAttributeToFilter('order_id_shoppingflux', $shoppingfluxId);
        $orders->addAttributeToSelect('increment_id');

        if ($orders->getSize() > 0) {
            $orders->setCurPage(1);
            $orders->setPageSize(1);
            return $orders->getFirstItem();
        }

        /** @var Mage_Core_Model_Config $config */
        $config = Mage::getModel('core/config');
        $flagPath = Mage::getStoreConfig('shoppingflux/order_flags/order_' . $shoppingfluxId);

        if ($flagPath) {
            $config->saveConfig('shoppingflux/order_flags/order_' . $shoppingfluxId, 0);
            return true;
        }

        $config->saveConfig('shoppingflux/order_flags/order_' . $shoppingfluxId, date('Y-m-d H:i:s'));
        return false;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return $this->getCheckoutSession()->getQuote();
    }

    public function manageOrders()
    {
        Mage::register('is_shoppingfeed_import', 1, true);
        // Compatibility with AutoShipping extension
        Mage::app()->getStore()->setConfig('autoshipping/settings/enabled', '0');

        $stores = Mage::app()->getStores();
        $storeCode = Mage::app()->getStore()->getCode();
        $isAdmin = ($storeCode === 'admin');

        if (!$isAdmin) {
            Mage::app()->setCurrentStore('admin');
        }

        $handledApiKeys = array();

        // Backwards compatibility with old module versions.
        $defaultStoreId = Mage::app()->getDefaultStoreView()->getId();

        if (key($stores) != $defaultStoreId) {
            $allStores = array($defaultStoreId => $stores[$defaultStoreId]);

            foreach ($stores as $store) {
                if ($store->getId() != $defaultStoreId) {
                    $allStores[$store->getId()] = $store;
                }
            }

            $stores = $allStores;
        }

        $importableStoreIds = array();

        foreach ($stores as $_store) {
            if ($isAdmin || ($storeCode === $_store->getCode())) {
                $importableStoreIds[] = $_store->getId();
            }
        }

        $isUsingLock = false;

        foreach ($importableStoreIds as $storeId) {
            if ($isUsingLock = $this->getConfig()->isOrderImportUsingLock($storeId)) {
                break;
            }
        }

        /** @var Profileolabs_Shoppingflux_Helper_Lock $lockHelper */
        $lockHelper = Mage::helper('profileolabs_shoppingflux/lock');

        if ($isUsingLock) {
            if ($lockHelper->isLocked(self::LOCK_CODE)) {
                return $this;
            }

            $lockHelper->lock(self::LOCK_CODE);
        }

        foreach ($importableStoreIds as $storeId) {
            $storeId = $_store->getId();

            if ($this->getConfig()->isOrdersEnabled($storeId)) {
                $apiKey = $this->getConfig()->getApiKey($storeId);

                if (!$apiKey || in_array($apiKey, $handledApiKeys)) {
                    continue;
                }

                $handledApiKeys[] = $apiKey;
                $wsUri = $this->getConfig()->getWsUri();

                /** @var $service Profileolabs_Shoppingflux_Model_Service */
                $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

                ini_set('memory_limit', $this->getConfig()->getMemoryLimit() . 'M');

                try {
                    $ordersResult = $service->getOrders();
                    $this->_importedOrderCount = 0;
                } catch (Exception $e) {
                    if ($isUsingLock) {
                        $lockHelper->unlock(self::LOCK_CODE);
                    }

                    Mage::logException($e);
                    $message = $this->getHelper()->__('Order import error : %s', $e->getMessage());
                    $this->getHelper()->log($message);
                    Mage::throwException($e);
                }

                $nodes = $ordersResult->children();

                if ($nodes && (count($nodes) > 0)) {
                    foreach ($nodes as $childName => $child) {
                        $sfOrder = $this->getHelper()->asArray($child);

                        if ($importedOrder = $this->isAlreadyImported($sfOrder['IdOrder'])) {
                            $this->_importedOrders[$sfOrder['IdOrder']] = array(
                                'Marketplace' => $sfOrder['Marketplace'],
                                'MageOrderId' => is_object($importedOrder) ? $importedOrder->getIncrementId() : '',
                                'ShippingMethod' => $sfOrder['ShippingMethod'],
                                'ErrorOrder' => is_object($importedOrder) ? false : true,
                            );

                            continue;
                        }

                        $this->_readOrderCount++;
                        $this->createAllForOrder($sfOrder, $storeId);

                        if ($this->_importedOrderCount == $this->getConfig()->getLimitOrders($storeId)) {
                            break;
                        }
                    }
                }

                try {
                    if (($this->_importedOrderCount > 0) || !empty($this->_importedOrders)) {
                        $result = $service->sendValidOrders($this->_importedOrders);

                        foreach ($this->_importedOrders as $importedOrder) {
                            $marketplace = $importedOrder['Marketplace'];

                            if (isset($importedOrder['ShippingMethod'])) {
                                $shippingMethod = $importedOrder['ShippingMethod'];
                            } else {
                                $shippingMethod = '';
                            }

                            try {
                                /** @var Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Method $methodModel */
                                $methodModel = Mage::getModel('profileolabs_shoppingflux/manageorders_shipping_method');
                                $methodModel->saveShippingMethod($marketplace, $shippingMethod);
                            } catch (Exception $e) {
                            }
                        }

                        if ($result) {
                            if ($result->error) {
                                Mage::throwException($result->error);
                            }

                            $this->_sentOrdersResult = $result->status;
                        } else {
                            $this->getHelper()->log('Error in order ids validated');
                            Mage::throwException('Error in order ids validated');
                        }
                    }
                } catch (Exception $e) {
                    if ($isUsingLock) {
                        $lockHelper->unlock(self::LOCK_CODE);
                    }

                    $this->getHelper()->log($e->getMessage());
                    Mage::throwException($e);
                }
            }
        }

        if ($isUsingLock) {
            $lockHelper->unlock(self::LOCK_CODE);
        }

        $this->clearOldOrderFlags();
        return $this;
    }

    public function clearOldOrderFlags()
    {
        $config = new Mage_Core_Model_Config();
        $orderFlags = Mage::getStoreConfig('shoppingflux/order_flags');

        if (!$orderFlags || empty($orderFlags)) {
            return;
        }

        foreach ($orderFlags as $orderId => $importDate) {
            if (strtotime($importDate) < time() - 3 * 60 * 60) {
                $config->deleteConfig('shoppingflux/order_flags/' . $orderId);
                $config->deleteConfig('shoppingflux/order_flags/order_' . $orderId);
            }
        }
    }

    /**
     * @param int $storeId
     */
    protected function _initQuote($storeId)
    {
        if ($storeId === null) {
            $storeId = Mage::app()->getDefaultStoreView()->getId();
        }

        $quote = $this->_getQuote();
        $quote->setStoreId($storeId);
        $quote->setIsSuperMode(true);
        $quote->setCustomer($this->_customer);
    }

    /**
     * @param array $data
     * @param int $storeId
     */
    protected function _createCustomer(array $data, $storeId)
    {
        try {
            /** @var Profileolabs_Shoppingflux_Model_Manageorders_Convert_Customer $converter */
            $converter = Mage::getModel('profileolabs_shoppingflux/manageorders_convert_customer');
            $this->_customer = $converter->toCustomer(current($data['BillingAddress']), $storeId);

            $billingAddress = $converter->addresstoCustomer(
                current($data['BillingAddress']),
                $storeId,
                $this->_customer
            );

            $this->_customer->addAddress($billingAddress);

            $shippingAddress = $converter->addresstoCustomer(
                current($data['ShippingAddress']),
                $storeId,
                $this->_customer,
                'shipping'
            );

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

    /**
     * @param array $sfOrder
     * @param int $storeId
     */
    public function createAllForOrder($sfOrder, $storeId)
    {
        try {
            $orderIdShoppingFlux = (string) $sfOrder['IdOrder'];

            $dataObject = new Varien_Object(array('entry' => $sfOrder, 'store_id' => $storeId));
            Mage::dispatchEvent('shoppingflux_before_import_order', array('sf_order' => $dataObject));
            $sfOrder = $dataObject->getData('entry');

            $this->_importedOrders[$orderIdShoppingFlux] = array(
                'Marketplace' => $sfOrder['Marketplace'],
                'MageOrderId' => '',
                'ShippingMethod' => $sfOrder['ShippingMethod'],
                'ErrorOrder' => false,
            );

            $this->_customer = null;
            $this->_createCustomer($sfOrder, $storeId);
            $this->_initQuote($storeId);

            if (Mage::registry('current_order_sf')) {
                Mage::unregister('current_order_sf');
            }

            if (Mage::registry('current_quote_sf')) {
                Mage::unregister('current_quote_sf');
            }

            Mage::register('current_order_sf', $sfOrder);
            Mage::register('current_quote_sf', $this->_getQuote());

            $this->_initQuoteAddresses();
            $this->_addProductsToQuote($sfOrder, $storeId);
            $order = null;

            if (!$this->getHelper()->isUnderVersion14()) {
                $order = $this->_saveOrder($sfOrder, $storeId);
            } else {
                $order = $this->_saveOrder13($sfOrder);
            }

            if ($order) {
                $this->getHelper()->log(
                    'Order ' . $sfOrder['IdOrder'] . ' has been created '
                    . '('
                    . $order->getIncrementId()
                    . ' / '
                    . Mage::app()->getStore()->getId()
                    . ')'
                );

                $this->_importedOrderCount++;

                if (($order !== null) && $order->getId()) {
                    $useMarketplaceDate = $this->getConfig()
                        ->getConfigFlag('shoppingflux_mo/manageorders/use_marketplace_date');

                    if ($useMarketplaceDate) {
                        $order->setCreatedAt($sfOrder['OrderDate']);
                        $order->save();
                    }
                }
            }

            $this->getCheckoutSession()->clear();
        } catch (Exception $e) {
            $this->getHelper()->log($e->getMessage() . ' Trace : ' . $e->getTraceAsString(), $sfOrder['IdOrder']);
            $this->_importedOrders[$orderIdShoppingFlux]['ErrorOrder'] = $e->getMessage();
            $this->getCheckoutSession()->clear();
        }
    }

    protected function _initQuoteAddresses()
    {
        if (!$this->_customer->getDefaultBilling()
            || !$this->_customer->getDefaultShipping()
            || (is_object($this->_customer->getDefaultShipping())
                && !$this->_customer->getDefaultShipping()->getFirstname())
        ) {
            $this->_customer->load($this->_customer->getId());
        }

        $billingAddressId = $this->_customer->getDefaultBilling();
        $shippingAddressId = $this->_customer->getDefaultShipping();

        $billingAddress = $this->_getQuote()->getBillingAddress();
        $billingAddress->setShouldIgnoreValidation(true);
        /** @var Mage_Customer_Model_Address $customerBillingAddress */
        $customerBillingAddress = Mage::getModel('customer/address');
        $customerBillingAddress->load($billingAddressId);
        $billingAddress->importCustomerAddress($customerBillingAddress)->setSaveInAddressBook(0);

        $shippingAddress = $this->_getQuote()->getShippingAddress();
        $shippingAddress->setShouldIgnoreValidation(true);
        /** @var Mage_Customer_Model_Address $customerShippingAddress */
        $customerShippingAddress = Mage::getModel('customer/address');
        $customerShippingAddress->load($shippingAddressId);
        $shippingAddress->importCustomerAddress($customerShippingAddress)->setSaveInAddressBook(0);
        $shippingAddress->setSameAsBilling(0);
        $shippingAddress->setShippingMethod($this->_shippingMethod)->setCollectShippingRates(true);
    }

    /**
     * @param array $sfOrder
     * @return bool
     */
    protected function _hasSfOrderItemPricesMissingEcotax(array $sfOrder)
    {
        return strtolower($sfOrder['Marketplace']) === 'laredoute';
    }

    /**
     * @param array $sfOrder
     * @param int $storeId
     */
    protected function _addProductsToQuote(array $sfOrder, $storeId)
    {
        /** @var Mage_Sales_Helper_Data $salesHelper */
        $salesHelper = Mage::helper('sales');
        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        /** @var Mage_Tax_Model_Calculation $taxCalculationModel */
        $taxCalculationModel = Mage::getSingleton('tax/calculation');

        $billingAddress = $this->_getQuote()->getBillingAddress();
        $shippingAddress = $this->_getQuote()->getShippingAddress();

        reset($sfOrder['Products']);
        $sfProducts = current($sfOrder['Products']);
        reset($sfProducts);
        $sfProducts = current($sfProducts);

        foreach ($sfProducts as $key => $sfProduct) {
            $sku = $sfProduct['SKU'];
            $qtyIncrements = 1;

            if (preg_match('%^_SFQI_([0-9]+)_(.*)$%i', $sku, $matches)) {
                $sku = $matches[2];
                $qtyIncrements = $matches[1];
            }

            $useProductId = $this->getConfig()->getConfigData('shoppingflux_mo/manageorders/use_product_id', $storeId);

            if ($useProductId) {
                $productId = null;

                if (!ctype_digit(trim($sku))) {
                    $productId = $this->getProductModel()->getResource()->getIdBySku($sku);
                }

                if (!$productId) {
                    $productId = $sku;
                }
            } else {
                $productId = $this->getProductModel()->getResource()->getIdBySku($sku);
            }

            /** @var Profileolabs_Shoppingflux_Model_Manageorders_Product $product */
            $product = Mage::getModel('profileolabs_shoppingflux/manageorders_product');
            $product->setStoreId($storeId);
            $product->load($productId);

            if ($product->getId()) {
                $request = new Varien_Object(array('qty' => $sfProduct['Quantity'] * $qtyIncrements));
                $item = $this->_getQuote()->addProduct($product, $request);

                if (!is_object($item)) {
                    $this->getCheckoutSession()->clear();

                    Mage::throwException(
                        'le produit sku = ' . $sku . ' n\'a pas pu être ajouté! Id = ' . $product->getId()
                        . ' Item = ' . (string) $item
                    );
                }

                $this->_getQuote()->save();

                $itemPrice = $sfProduct['Price'];

                if (
                    isset($sfProduct['Ecotax'])
                    && ($sfProduct['Ecotax'] > 0)
                    && $this->_hasSfOrderItemPricesMissingEcotax($sfOrder)
                ) {
                    $itemPrice += $sfProduct['Ecotax'];
                }

                $unitPrice = $itemPrice / $qtyIncrements;

                if ($unitPrice <= 0) {
                    $this->getHelper()
                        ->log('Order ' . $sfOrder['IdOrder'] . ' has a product with 0 price : ' . $sfProduct['SKU']);
                }

                if ($this->getConfig()->applyTax() && !$taxHelper->priceIncludesTax()) {
                    $taxClassId = $product->getTaxClassId();

                    if ($taxClassId > 0) {
                        $request = $taxCalculationModel->getRateRequest(
                            $shippingAddress,
                            $billingAddress,
                            null,
                            null
                        );

                        $request->setProductClassId($taxClassId);
                        $request->setCustomerClassId($this->_getQuote()->getCustomerTaxClassId());
                        $percent = $taxCalculationModel->getRate($request);
                        $unitPrice = $unitPrice / (1 + $percent / 100);

                        if ($unitPrice <= 0) {
                            $this->getHelper()
                                ->log(
                                    'Order ' . $sfOrder['IdOrder']
                                    . ' has a product with 0 price after applying tax : ' . $sfProduct['SKU']
                                );
                        }
                    }
                }

                $item->setCustomPrice($unitPrice);
                $item->setOriginalCustomPrice($unitPrice);

                $configurableValues = array();

                if ($this->getHelper()->isUnderVersion14()) {
                    $configurableAttributesCollection = Mage::getResourceModel('eav/entity_attribute_collection')
                        ->setEntityTypeFilter(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId());
                } else {
                    $configurableAttributesCollection = Mage::getResourceModel('catalog/product_attribute_collection');
                }

                $configurableAttributesCollection->addVisibleFilter()->addFieldToFilter('is_configurable', 1);

                /** @var Mage_Eav_Model_Entity_Attribute $configurableAttribute */
                foreach ($configurableAttributesCollection as $configurableAttribute) {
                    $attributeCode = $configurableAttribute->getAttributeCode();

                    if (!in_array($attributeCode, $this->_excludeConfigurableAttributes)
                        && $product->getData($attributeCode)
                    ) {
                        if ($configurableAttribute->usesSource()) {
                            $attributeValue = $product->getAttributeText($attributeCode);
                        } else {
                            $attributeValue = $product->getData($attributeCode);
                        }

                        if (is_string($attributeValue)) {
                            $configurableValues[] = $attributeValue;
                        }
                    }
                }

                if (!empty($configurableValues)) {
                    $item->setDescription($item->getDescription() . implode(' - ', $configurableValues));
                }

                $item->save();

                if (is_object($parentItem = $item->getParentItem())) {
                    $parentItem->setCustomPrice($unitPrice);
                    $parentItem->setOriginalCustomPrice($unitPrice);
                    $parentItem->save();
                }
            } else {
                $this->getCheckoutSession()->clear();

                Mage::throwException(
                    'le produit sku = "' . $sku
                    . ' (ID= ' . $productId . ','
                    . ' Utilisation id = ' . ($useProductId ? 'Oui' : 'Non') . ') n\'existe plus en base!'
                );
            }
        }

        try {
            $this->_getQuote()->collectTotals();
            $this->_getQuote()->save();
        } catch (Exception $e) {
            if ($e->getMessage() == $salesHelper->__('Please specify a shipping method.')) {
                $this->_getQuote()
                    ->getShippingAddress()
                    ->setShippingMethod($this->_shippingMethod)
                    ->setCollectShippingRates(true);

                $this->_getQuote()->setTotalsCollectedFlag(false)->collectTotals();
                $this->_getQuote()->save();
            } else {
                throw $e;
            }
        }

        $this->_getQuote()->getShippingAddress()->setPaymentMethod($this->_paymentMethod);
        $paymentMethod = $this->_getQuote()->getPayment();
        $paymentData = array('method' => $this->_paymentMethod, 'marketplace' => $sfOrder['Marketplace']);
        $paymentMethod->importData($paymentData);

        try {
            $this->_getQuote()->collectTotals();
            $this->_getQuote()->save();
        } catch (Exception $e) {
            if ($e->getMessage() == $salesHelper->__('Please specify a shipping method.')) {
                $this->_getQuote()
                    ->getShippingAddress()
                    ->setShippingMethod($this->_shippingMethod)
                    ->setCollectShippingRates(true);

                $this->_getQuote()->getShippingAddress()->setPaymentMethod($this->_paymentMethod);
                $paymentMethod = $this->_getQuote()->getPayment();
                $paymentData = array('method' => $this->_paymentMethod, 'marketplace' => $sfOrder['Marketplace']);
                $paymentMethod->importData($paymentData);

                $this->_getQuote()->setTotalsCollectedFlag(false)->collectTotals();
                $this->_getQuote()->save();
            } else {
                throw $e;
            }
        }
    }

    /**
     * @param array $sfOrder
     * @param int $storeId
     * @return Mage_Sales_Model_Order|null
     */
    protected function _saveOrder(array $sfOrder, $storeId)
    {
        $config = $this->getConfig();
        $orderIdShoppingFlux = (string) $sfOrder['IdOrder'];

        $additionalData = array(
            'from_shoppingflux' => 1,
            'marketplace_shoppingflux' => $sfOrder['Marketplace'],
            'fees_shoppingflux' => (float) (isset($sfOrder['TotalFees']) ? $sfOrder['TotalFees'] : 0),
            'other_shoppingflux' => $sfOrder['Other'],
            'order_id_shoppingflux' => $orderIdShoppingFlux,
            'grand_total' => $sfOrder['TotalAmount'],
            'base_grand_total' => $sfOrder['TotalAmount'],
            'order_currency_code' => isset($sfOrder['Currency']) ? $sfOrder['Currency'] : 'EUR',
            'base_currency_code' => isset($sfOrder['Currency']) ? $sfOrder['Currency'] : 'EUR',
            'store_currency_code' => isset($sfOrder['Currency']) ? $sfOrder['Currency'] : 'EUR',

        );

        if (isset($sfOrder['ShippingAddress'][0]['RelayID']) && $sfOrder['ShippingAddress'][0]['RelayID']) {
            if ($additionalData['other_shoppingflux']) {
                $additionalData['other_shoppingflux'] .= '<br/>';
            }

            $additionalData['other_shoppingflux'] .= 'Relay ID : ' . $sfOrder['ShippingAddress'][0]['RelayID'];
        }

        $shippingMethod = $config->getShippingMethodFor($sfOrder['Marketplace'], $sfOrder['ShippingMethod'], $storeId);

        if ($shippingMethod) {
            $additionalData['shipping_method'] = $shippingMethod;
            $additionalData['shipping_description'] = "Frais de port de la place de marché (" . $shippingMethod . ")";
        }

        $quote = $this->_getQuote();

        /** @var Profileolabs_Shoppingflux_Helper_Sales $salesHelper */
        $salesHelper = Mage::helper('profileolabs_shoppingflux/sales');
        $isFulfiledOrder = $salesHelper->isFulfilmentMarketplace($sfOrder['Marketplace']);

        if ($isFulfiledOrder) {
            $this->_getQuote()->setInventoryProcessed(true);
        }

        /** @var Mage_Sales_Model_Service_Quote $service */
        $service = Mage::getModel('sales/service_quote', $this->_getQuote());
        $service->setOrderData($additionalData);

        ini_set('display_errors', 1);
        error_reporting(-1);

        try {
            if (method_exists($service, 'submitAll')) {
                $service->submitAll();
                $order = $service->getOrder();
            } else {
                $order = $service->submit();
            }
        } catch (Exception $e) {
            throw $e;
        }

        try {
            $quote->setIsActive(0)->setUpdatedAt(date('Y-m-d H:i:s', strtotime('-1 year')))->save();
        } catch (Exception $e) {
        }

        if ($order) {
            $newStatus = $config->getConfigData('shoppingflux_mo/manageorders/new_order_status', $order->getStoreId());

            if ($newStatus) {
                $order->setStatus($newStatus);
                $order->save();
            }

            $this->_saveInvoice($order);

            $processingStatus = $config->getConfigData(
                'shoppingflux_mo/manageorders/processing_order_status',
                $order->getStoreId()
            );

            if (($order->getState() === 'processing') && $processingStatus) {
                $order->setStatus($processingStatus);
                $order->save();
            }

            foreach ($order->getAllItems() as $orderItem) {
                if ($weeeTaxRowAmount = $orderItem->getWeeeTaxAppliedRowAmount()) {
                    $baseWeeeTaxRowAmount = $orderItem->getBaseWeeeTaxAppliedRowAmnt();
                    $weeeTaxAmount = $orderItem->getWeeeTaxAppliedAmount();
                    $baseWeeeTaxAmount = $orderItem->getBaseWeeeTaxAppliedAmount();

                    if ($orderItem->getData('row_total_incl_tax')) {
                        $orderItem->setData(
                            'row_total_incl_tax',
                            $orderItem->getData('row_total_incl_tax') - $weeeTaxRowAmount
                        );
                    }

                    if ($orderItem->getData('base_row_total_incl_tax')) {
                        $orderItem->setData(
                            'base_row_total_incl_tax',
                            $orderItem->getData('base_row_total_incl_tax') - $baseWeeeTaxRowAmount
                        );
                    }

                    $orderItem->addData(
                        array(
                            'row_total' => $orderItem->getData('row_total') - $weeeTaxRowAmount,
                            'base_row_total' => $orderItem->getData('base_row_total') - $baseWeeeTaxRowAmount,
                            'price' => $orderItem->getData('price') - $weeeTaxAmount,
                            'base_price' => $orderItem->getData('base_price') - $baseWeeeTaxAmount,
                        )
                    );

                    $orderItem->save();
                }
            }

            if ($isFulfiledOrder) {
                $this->_saveFulfilmentShipment($order);

                $processingStatus = $config->getConfigData(
                    'shoppingflux_mo/manageorders/fulfilment_processing_order_status',
                    $order->getStoreId()
                );

                $completeStatus = $config->getConfigData(
                    'shoppingflux_mo/manageorders/fulfilment_complete_order_status',
                    $order->getStoreId()
                );

                if ($order->getState() === 'complete') {
                    if ($completeStatus) {
                        $order->setStatus($completeStatus);
                        $order->save();
                    }
                } elseif ($order->getState() === 'processing') {
                    if ($processingStatus) {
                        $order->setStatus($processingStatus);
                        $order->save();
                    }
                }
            }

            $this->_importedOrders[$orderIdShoppingFlux]['MageOrderId'] = $order->getIncrementId();
            return $order;
        }

        return null;
    }

    /**
     * @param array $sfOrder
     * @return Mage_Sales_Model_Order|null
     */
    protected function _saveOrder13(array $sfOrder)
    {
        $orderIdShoppingFlux = (string) $sfOrder['IdOrder'];

        $additionalData = array(
            'from_shoppingflux' => 1,
            'marketplace_shoppingflux' => $sfOrder['Marketplace'],
            'fees_shoppingflux' => (float) (isset($sfOrder['Fees']) ? $sfOrder['Fees'] : 0.0),
            'other_shoppingflux' => $sfOrder['Other'],
            'order_id_shoppingflux' => $orderIdShoppingFlux,
        );

        $billingAddress = $this->_getQuote()->getBillingAddress();
        $shippingAddress = $this->_getQuote()->getShippingAddress();

        $this->_getQuote()->reserveOrderId();

        /** @var Mage_Sales_Model_Convert_Quote $quoteConverter */
        $quoteConverter = Mage::getModel('sales/convert_quote');
        $order = $quoteConverter->addressToOrder($shippingAddress);
        $order->addData($additionalData);
        $order->setBillingAddress($quoteConverter->addressToOrderAddress($billingAddress));
        $order->setShippingAddress($quoteConverter->addressToOrderAddress($shippingAddress));
        $order->setPayment($quoteConverter->paymentToOrderPayment($this->_getQuote()->getPayment()));

        foreach ($this->_getQuote()->getAllItems() as $item) {
            $orderItem = $quoteConverter->itemToOrderItem($item);

            if ($item->getParentItem()) {
                $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
            }

            $order->addItem($orderItem);
        }


        Mage::dispatchEvent(
            'checkout_type_onepage_save_order',
            array('order' => $order, 'quote' => $this->getQuote())
        );

        $order->place();
        $order->setCustomerId($this->_getQuote()->getCustomer()->getId());
        $order->setEmailSent(false);
        $order->save();

        Mage::dispatchEvent(
            'checkout_type_onepage_save_order_after',
            array('order' => $order, 'quote' => $this->getQuote())
        );

        $this->_getQuote()->setIsActive(false);
        $this->_getQuote()->save();

        if ($order) {
            $this->_saveInvoice($order);

            $this->_importedOrders[$orderIdShoppingFlux] = array(
                'Marketplace' => $sfOrder['Marketplace'],
                'MageOrderId' => $order->getIncrementId(),
            );

            return $order;
        }

        return null;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     */
    protected function _saveInvoice($order)
    {
        Mage::dispatchEvent(
            'checkout_type_onepage_save_order_after',
            array('order' => $order, 'quote' => $this->_getQuote())
        );

        if (!$this->getConfig()->createInvoice($order->getStoreId())) {
            return;
        }

        /** @var MAge_Sales_Model_Service_Order $orderService */
        $orderService = Mage::getModel('sales/service_order', $order);

        if ($orderService) {
            $invoice = $orderService->prepareInvoice();
        } else {
            $invoice = $this->_initInvoice($order);
        }

        if ($invoice) {
            $invoice->setBaseGrandTotal($order->getBaseGrandTotal());
            $invoice->setGrandTotal($order->getGrandTotal());
            $invoice->register();
            $invoice->getOrder()->setCustomerNoteNotify(false);
            $invoice->getOrder()->setIsInProcess(true);

            /** @var Mage_Core_Model_Resource_Transaction $transaction */
            $transaction = Mage::getModel('core/resource_transaction');
            $transaction->addObject($invoice);
            $transaction->addObject($invoice->getOrder());
            $transaction->save();
        }
    }

    /**
     * @param Mage_Sales_Model_Order_Item $item
     * @param array $qtys
     * @return bool
     */
    protected function _needToAddDummy($item, $qtys)
    {
        if ($item->getHasChildren()) {
            foreach ($item->getChildrenItems() as $child) {
                if (isset($qtys[$child->getId()]) && $qtys[$child->getId()] > 0) {
                    return true;
                }
            }
        } elseif ($item->getParentItem()) {
            if (isset($qtys[$item->getParentItem()->getId()]) && $qtys[$item->getParentItem()->getId()] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function _initInvoice($order)
    {
        /** @var Mage_Sales_Model_Convert_Order $converter */
        $converter = Mage::getModel('sales/convert_order');
        $invoice = $converter->toInvoice($order);
        $savedQtys = array();
        $itemsToInvoice = 0;

        /** @var Mage_Sales_Model_Order_Item $orderItem */
        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->isDummy() && !$orderItem->getQtyToInvoice() && $orderItem->getLockedDoInvoice()) {
                continue;
            }

            if ($order->getForcedDoShipmentWithInvoice() && $orderItem->getLockedDoShip()) {
                continue;
            }

            if ($orderItem->isDummy() && !empty($savedQtys) && !$this->_needToAddDummy($orderItem, $savedQtys)) {
                continue;
            }

            $item = $converter->itemToInvoiceItem($orderItem);

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
                Mage::throwException($this->getHelper()->__('Invoice could not be created (no items).'));
            }
        }

        $invoice->collectTotals();
        return $invoice;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     */
    protected function _saveFulfilmentShipment($order)
    {
        if (!$order->canShip()) {
            $message = $this->getHelper()->__('The fulfiled order #%s is not shippable', $order->getIncrementId());
            $this->getHelper()->log($message);
            return;
        }

        /** @var Mage_Sales_Model_Convert_Order $orderConvertor */
        $orderConvertor = Mage::getModel('sales/convert_order');
        $shipment = $orderConvertor->toShipment($order);

        /** @var Mage_Sales_Model_Order_Item $orderItem */
        foreach ($order->getAllItems() as $orderItem) {
            if (!$orderItem->getQtyToShip()) {
                continue;
            }

            if ($orderItem->getIsVirtual()) {
                continue;
            }

            $shipmentItem = $orderConvertor->itemToShipmentItem($orderItem);
            $shippedQty = $orderItem->getQtyToShip();
            $shipmentItem->setQty($shippedQty);

            $shipment->addItem($shipmentItem);
        }

        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);

        try {
            /** @var Mage_Core_Model_Resource_Transaction $transaction */
            $transaction = Mage::getModel('core/resource_transaction');
            $transaction->addObject($shipment);
            $transaction->addObject($shipment->getOrder());
            $transaction->save();
        } catch (Exception $e) {
            Mage::logException($e);

            $message = $this->getHelper()
                ->__(
                    'A shipment could not automatically be created for the fulfiled order #%s: %s',
                    $order->getIncrementId(),
                    $e->getMessage()
                );

            $this->getHelper()->log($message);
        }
    }
}
