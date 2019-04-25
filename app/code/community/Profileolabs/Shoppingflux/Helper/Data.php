<?php

class Profileolabs_Shoppingflux_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * @var Profileolabs_Shoppingflux_Model_Config|null
     */
    protected $_config = null;

    /**
     * @var array|null
     */
    protected $_categoriesWithParents = null;

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
     * @return bool
     */
    public function isUnderVersion14()
    {
        return (version_compare(Mage::getVersion(), '1.4.0') < 0);
    }

    /**
     * @param string $module
     * @return bool
     */
    public function isModuleInstalled($module)
    {
        $modules = (array) Mage::getConfig()->getNode('modules')->children();
        return isset($modules[$module]);
    }

    /**
     * @return string
     */
    public function getModuleVersion()
    {
        return Mage::getConfig()->getModuleConfig('Profileolabs_Shoppingflux')->version;
    }

    /**
     * @param int $storeId
     * @return string
     */
    public function getFeedUrlSecureKey($storeId)
    {
        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');
        return $coreHelper->encrypt($this->getConfig()->getApiKey($storeId));
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @param string $action
     * @return string
     */
    public function getFeedUrl($store, $action = 'index')
    {
        $params = array();

        if ($this->getConfig()->isApiKeyIncludedInFeedUrl($store->getId())) {
            $params['key'] = $this->getFeedUrlSecureKey($store->getId());
        }

        return preg_replace(
            '%^(.*)\?.*$%i',
            '$1',
            $store->getUrl('shoppingflux/export_flux/' . $action, $params)
        );
    }

    /**
     * @param string $prefix
     * @return string
     */
    public function generateToken($prefix = '0')
    {
        return md5($prefix . $_SERVER['SERVER_ADDR'] . time());
    }

    public function generateTokens()
    {
        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores() as $store) {
            $apiKey = Mage::getConfig()->getNode('stores/' . $store->getCode() . '/shoppingflux/configuration/api_key');

            if (!trim($apiKey)) {
                $apiKey = Mage::getStoreConfig('shoppingflux/configuration/api_key', 0);

                if (!$apiKey) {
                    $apiKey = $this->generateToken((string) $store->getId());
                }

                Mage::getConfig()->saveConfig(
                    'shoppingflux/configuration/api_key',
                    $apiKey,
                    'stores',
                    $store->getId()
                );

                Mage::getConfig()->cleanCache();
            }
        }

        Mage::getConfig()->saveConfig('shoppingflux/configuration/api_key', '', 'default');
    }

    /**
     * @param string $value
     * @return string
     */
    public function cleanString($value)
    {
        /** @var Mage_Core_Helper_String $stringHelper */
        $stringHelper = Mage::helper('core/string');

        if (is_callable(array($stringHelper, 'cleanString'))) {
            $value = $stringHelper->cleanString($value);
        }

        // Reject overly long 2 byte sequences, as well as characters above U+10000, and replace them with blanks.
        $value = preg_replace(
            '/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
            '|[\x00-\x7F][\x80-\xBF]+' .
            '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
            '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
            '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
            '',
            $value
        );

        // Reject overly long 3 byte sequences and UTF-16 surrogates, and replace them with blanks.
        $value = preg_replace(
            '/\xE0[\x80-\x9F][\x80-\xBF]' .
            '|\xED[\xA0-\xBF][\x80-\xBF]/S',
            '',
            $value
        );

        $value = str_replace(array(chr(28), chr(29), chr(30), chr(31)), '', $value);
        return $value;
    }

    /**
     * @param mixed $fees
     * @param string $marketplace
     * @return string
     */
    public function formatFeesDescription($fees, $marketplace)
    {
        return $this->__('%s fees', $marketplace);
    }

    /**
     * @param string $street
     * @param int $lineMaxLength
     * @param array $additional
     * @return array
     */
    public function truncateAddress($street, $lineMaxLength = 35, $additional = array())
    {
        $street = trim($street);

        if (!$street) {
            return array();
        }

        /** @var Mage_Core_Helper_String $stringHelper */
        $stringHelper = Mage::helper('core/string');

        if (preg_match('/^.{1,' . $lineMaxLength . '}(\s|$)/u', $street, $match)) {
            $line = trim($match[0]);
        } else {
            $line = $stringHelper->substr($street, 0, $lineMaxLength);
        }

        $street = trim($stringHelper->substr($street, $stringHelper->strlen($line)));
        return array_merge(array($line), $this->truncateAddress($street, $lineMaxLength), $additional);
    }

    /**
     * @param SimpleXMLElement $xml
     * @param bool $isCanonical
     * @return array|string
     */
    public function asArray(SimpleXMLElement $xml, $isCanonical = true)
    {
        $result = array();

        if (!$isCanonical) {
            foreach ($xml->attributes() as $attributeName => $attribute) {
                if ($attribute) {
                    $result['@'][$attributeName] = trim((string) $attribute);
                }
            }
        }

        if ($xml->hasChildren()) {
            foreach ($xml->children() as $childName => $child) {
                if (!$child->hasChildren()) {
                    $result[$childName] = $this->asArray($child, $isCanonical);
                } else {
                    $result[$childName][] = $this->asArray($child, $isCanonical);
                }
            }
        } else {
            if (empty($result)) {
                $result = trim((string) $xml);
            } else {
                $result[0] = trim((string) $xml);
            }
        }

        return $result;
    }

    /**
     * @param string $message
     * @param int|null $orderId
     * @return $this
     */
    public function log($message, $orderId = null)
    {
        /** @var Profileolabs_Shoppingflux_Model_Manageorders_Log $logModel */
        $logModel = Mage::getModel('profileolabs_shoppingflux/manageorders_log');
        $logModel->log($message, $orderId);
        return $this;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param string $countryCode
     * @return Mage_Shipping_Model_Rate_Request
     */
    protected function _getShippingRequest($product, $countryCode = 'FR')
    {
        /** @var Mage_Shipping_Model_Rate_Request $request */
        $request = Mage::getModel('shipping/rate_request');
        $storeId = $request->getStoreId();

        if (!$request->getOrig()) {
            $request->setCountryId($countryCode)
                ->setRegionId('')
                ->setCity('')
                ->setPostcode('');
        }

        /** @var Mage_Sales_Model_Quote_Item $item */
        $item = Mage::getModel('sales/quote_item');
        $item->setStoreId($storeId);
        $item->setOptions($product->getCustomOptions());
        $item->setProduct($product);

        $request->setAllItems(array($item));
        $request->setDestCountryId($countryCode);
        $request->setDestRegionId('');
        $request->setDestRegionCode('');
        $request->setDestPostcode('');
        $request->setPackageValue($product->getPrice());
        $request->setPackageValueWithDiscount($product->getFinalPrice());
        $request->setPackageWeight($product->getWeight());
        $request->setFreeMethodWeight(0);
        $request->setPackageQty(1);

        $store = Mage::app()->getStore();
        $request->setStoreId($store->getId());
        $request->setWebsiteId($store->getWebsiteId());
        $request->setBaseCurrency($store->getBaseCurrency());
        $request->setPackageCurrency($store->getCurrentCurrency());

        return $request;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param string $carrierValue
     * @param string $countryCode
     * @return float|false
     */
    public function getShippingPrice($product, $carrierValue, $countryCode = 'FR')
    {
        list($carrierCode,) = explode('_', $carrierValue);

        /** @var Mage_Shipping_Model_Shipping $shipping */
        $shipping = Mage::getModel('shipping/shipping');
        /** @var Mage_Shipping_Model_Carrier_Abstract $carrier */
        $carrier = $shipping->getCarrierByCode($carrierCode);

        if ($carrier) {
            if (is_object($result = $carrier->collectRates($this->_getShippingRequest($product, $countryCode)))) {
                if ($result->getError()) {
                    Mage::logException(new Exception($result->getError()));
                } else {
                    foreach ($result->getAllRates() as $rate) {
                        return $rate->getPrice();
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param Mage_Catalog_Model_Product|int $productId
     * @return mixed
     */
    public function getConfigurableAttributes($productId)
    {
        if (is_object($productId)) {
            $productId = $productId->getId();
        }

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('catalog_read');
        $superAttributeTable = $resource->getTableName('catalog/product_super_attribute');
        $eavAttributeTable = $resource->getTableName('eav/attribute');

        $select = $connection->select()
            ->from(array('main_table' => $superAttributeTable))
            ->join(
                array('attribute_table' => $eavAttributeTable),
                'main_table.attribute_id = attribute_table.attribute_id',
                array('attribute_code' => 'attribute_code')
            )
            ->where('product_id = ?', $productId);

        $result = $connection->fetchAll($select);
        return $result;
    }

    /**
     * @param string|false $key
     * @param int|null $storeId
     * @param bool $withInactive
     * @param bool $withNotInMenu
     * @return array
     */
    public function getCategoriesWithParents(
        $key = false,
        $storeId = null,
        $withInactive = false,
        $withNotInMenu = true
    ) {
        if ($this->_categoriesWithParents === null) {
            $mageCacheKey = 'shoppingflux_category_list'
                . (Mage::app()->getStore()->isAdmin() ? '_admin' : '')
                . '_' . (int) $storeId
                . ($withInactive ? '_inactive' : '_active')
                . ($withNotInMenu ? '_all' : '_inmenu');

            $cacheTags = array('shoppingflux');
            $this->_categoriesWithParents = unserialize(Mage::app()->loadCache($mageCacheKey));

            if (!$this->_categoriesWithParents) {
                $rootCategoryId = Mage::app()->getStore($storeId)->getRootCategoryId();
                $this->_categoriesWithParents = array('name' => array(), 'url' => array(), 'id' => array());

                /** @var Profileolabs_Shoppingflux_Model_Config $config */
                $config = Mage::getSingleton('profileolabs_shoppingflux/config');

                /** @var Mage_Catalog_Model_Resource_Category_Collection $categories */
                $categories = Mage::getResourceModel('catalog/category_collection');

                $isManaFslInstalled = $this->isModuleInstalled('ManaPro_FilterSeoLinks');

                if ($storeId) {
                    $categories->setStoreId($storeId);
                }

                $categories->addAttributeToSelect('name')
                    ->addAttributeToSelect('meta_title')
                    ->addAttributeToSelect('meta_description')
                    ->addAttributeToSelect('meta_keywords')
                    ->addAttributeToFilter(
                        'sf_exclude',
                        array(array('is' => new Zend_Db_Expr('NULL')), array('eq' => 0)),
                        'left'
                    )
                    ->addAttributeToFilter('entity_id', array('neq' => 1))
                    ->addAttributeToSort('path', 'asc')
                    ->addAttributeToSort('name', 'asc');

                if (!$withInactive) {
                    $categories->addFieldToFilter('is_active', array('eq' => '1'));
                }

                if (!$withNotInMenu) {
                    if (version_compare(Mage::getVersion(), '1.4.0') > 0) {
                        $categories->addFieldToFilter('include_in_menu', array('eq' => '1'));
                    }
                }

                if (!$config->getUseAllStoreCategories()) {
                    $categories->addAttributeToFilter('entity_id', array('neq' => $rootCategoryId));

                    if ($rootCategoryId != 0) {
                        $categories->addFieldToFilter('path', array('like' => '1/' . $rootCategoryId . '/%'));
                    }
                }

                foreach ($categories as $category) {
                    $parentId = $category->getParentId();

                    while ($parentId > 1) {
                        /** @var Mage_Catalog_Model_Category $parentCategory */
                        $parentCategory = Mage::getModel('catalog/category');
                        $parentCategory->setStoreId($storeId);
                        $parentCategory->load($parentId);

                        if (!$parentCategory->getSfExclude()) {
                            $category->setName(
                                $parentCategory->getName()
                                . ' > '
                                . $category->getName()
                            );

                            $category->setMetaTitle(
                                $parentCategory->getMetaTitle()
                                . ' > '
                                . $category->getMetaTitle()
                            );

                            $category->setMetaDescription(
                                $parentCategory->getMetaDescription()
                                . ' > '
                                . $category->getMetaDescription()
                            );

                            $category->setMetaKeywords(
                                $parentCategory->getMetaKeywords()
                                . ' > '
                                . $category->getMetaKeywords()
                            );

                            $category->setIds(
                                $parentCategory->getId()
                                . ' > '
                                . ($category->getIds() ? $category->getIds() : $category->getId())
                            );

                            if (!Mage::app()->getStore()->isAdmin() || !$isManaFslInstalled) {
                                // Prevent some incompatibilities with third-party extensions
                                $category->setUrl($parentCategory->getUrl() . ' > ' . $category->getUrl());
                            }
                        }

                        $parentId = $parentCategory->getParentId();
                    }
                }

                foreach ($categories as $category) {
                    $categoryId = $category->getId();
                    $this->_categoriesWithParents['name'][$categoryId] = $category->getName();
                    $this->_categoriesWithParents['meta_title'][$categoryId] = $category->getMetaTitle();
                    $this->_categoriesWithParents['meta_description'][$categoryId] = $category->getMetaDescription();
                    $this->_categoriesWithParents['meta_keywords'][$categoryId] = $category->getMetaKeywords();

                    if ($this->isModuleInstalled('ManaPro_FilterSeoLinks') && Mage::app()->getStore()->isAdmin()) {
                        $this->_categoriesWithParents['url'][$categoryId] = '';
                    } else {
                        $this->_categoriesWithParents['url'][$categoryId] = $category->getUrl();
                    }

                    $this->_categoriesWithParents['id'][$categoryId] = $category->getIds();
                }

                Mage::app()->saveCache(serialize($this->_categoriesWithParents), $mageCacheKey, $cacheTags);
                unset($categories);
            }
        }

        return ($key && isset($this->_categoriesWithParents[$key]))
            ? $this->_categoriesWithParents[$key]
            : $this->_categoriesWithParents;
    }

    /**
     * @param string $message
     */
    public function notifyError($message)
    {
        /** @var Mage_Core_Model_Email_Template $emailTemplate */
        $emailTemplate = Mage::getModel('core/email_template');
        $emailTemplate->loadDefault('shoppingflux_alert');
        $emailTemplate->setSenderName('Magento/ShoppingFlux');
        $emailTemplate->setSenderEmail('no-reply@magento-shoppingflux.com');

        $emailTemplate->send(
            Mage::getStoreConfig('shoppingflux/configuration/alert_email'),
            Mage::getStoreConfig('shoppingflux/configuration/alert_email'),
            array('message' => $message)
        );

        try {
            /** @var Mage_Core_Model_Date $dateModel */
            $dateModel = Mage::getModel('core/date');
            /** @var Mage_AdminNotification_Model_Inbox $notification */
            $notification = Mage::getModel('adminnotification/inbox');
            $notification->setSeverity(Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL);
            $notification->setTitle($this->__('Shoppingflux alert'));
            $notification->setDateAdded(date('Y-m-d H:i:s', $dateModel->timestamp(time())));
            $notification->setDescription($this->__('Shoppingflux alert : <br/> %s', $message));
            $notification->save();
        } catch (Exception $e) {
        }
    }

    public function isRegistered()
    {
        if (Mage::getStoreConfigFlag('shoppingflux/configuration/has_registered')) {
            return true;
        } else {
            $lastDelay = time() - (int) Mage::getStoreConfig('shoppingflux/configuration/registration_last_check');

            if ($lastDelay > 24 * 60 * 60) {
                foreach (Mage::app()->getStores() as $store) {
                    $apiKey = $this->getConfig()->getApiKey($store->getId());
                    $wsUri = $this->getConfig()->getWsUri();
                    $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

                    if ($service->isClient()) {
                        return true;
                    }
                }

                /** @var Mage_Core_Model_Config $config */
                $config = Mage::getSingleton('core/config');
                $config->saveConfig('shoppingflux/configuration/registration_last_check', time());
            }
        }

        return false;
    }

    public function newInstallation()
    {
        try {
            $sendTo = array('olivier@shopping-feed.com', 'andy@shopping-feed.com');
            $mailContent = array();

            /** @var MAge_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            $mailContent['Magento URL'] = $adminHelper->getUrl();

            if (!preg_match('%http%i', $mailContent['Magento URL'])) {
                $mailContent['Magento URL'] = implode(
                    '/',
                    array_filter(
                        array(
                            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '',
                            isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                            isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '',
                        )
                    )
                );
            }

            $mailContent['Module Version'] = $this->getModuleVersion();
            $mailContent['Email'] = Mage::getStoreConfig('trans_email/ident_general/email');
            $mailContent['Config Country'] = Mage::getStoreConfig('shipping/origin/country_id');

            $mailContent['Config Address'] = implode(
                ' ',
                array(
                    Mage::getStoreConfig('shipping/origin/street_line1'),
                    Mage::getStoreConfig('shipping/origin/street_line2'),
                    Mage::getStoreConfig('shipping/origin/postcode'),
                    Mage::getStoreConfig('shipping/origin/city'),
                )
            );

            $stores = Mage::app()->getStores();
            $defaultStoreId = (($defaultStore = Mage::app()->getDefaultStoreView()) ? $defaultStore->getId() : false);

            if (count($stores) == 0) {
                Mage::app()->reinitStores();
                $stores = Mage::app()->getStores();
            }

            /** @var Mage_Core_Model_Store $store */
            foreach ($stores as $store) {
                $storeId = $store->getId();

                $mailContent['Store #' . $storeId . ' Name '] = implode(
                    ' > ',
                    array(
                        $store->getWebsite()->getName(),
                        $store->getGroup()->getName(),
                        $store->getName(),
                    )
                );

                $mailContent['Store #' . $storeId . ' Url '] = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                $mailContent['Store #' . $storeId . ' Feed '] = $this->getFeedUrl($store);
                $mailContent['Store #' . $storeId . ' Refresh Url '] = $this->getFeedUrl($store, 'refreshEverything');
                $mailContent['Store #' . $storeId . ' Status Url '] = $this->getFeedUrl($store, 'status');
                $mailContent['Store #' . $storeId . ' is default ?'] = ($defaultStoreId == $storeId ? 'Yes' : 'No');
            }

            /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $mailContent['Products count'] = $productCollection->getSize();

            /** @var Mage_Catalog_Model_Product_Status $statusModel */
            $statusModel = Mage::getSingleton('catalog/product_status');
            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $statusModel->addVisibleFilterToCollection($productCollection);
            $mailContent['Active products count'] = $productCollection->getSize();

            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $productCollection->addAttributeToFilter('type_id', 'configurable');
            $mailContent['Configurable products count'] = $productCollection->getSize();


            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $productCollection->addAttributeToFilter('type_id', 'simple');
            $mailContent['Simple products count'] = $productCollection->getSize();


            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $productCollection->addAttributeToFilter('type_id', 'virtual');
            $mailContent['Virtual products count'] = $productCollection->getSize();


            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $productCollection->addAttributeToFilter('type_id', 'downloadable');
            $mailContent['Downloadable products count'] = $productCollection->getSize();


            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $productCollection->addAttributeToFilter('type_id', 'grouped');
            $mailContent['Grouped products count'] = $productCollection->getSize();


            $productCollection = Mage::getResourceModel('catalog/product_collection');
            $productCollection->addAttributeToFilter('type_id', 'bundle');
            $mailContent['Bundle products count'] = $productCollection->getSize();

            $mailLines = array();

            foreach ($mailContent as $key => $value) {
                $mailLines[] = '<strong>' . $key . ' : </strong>' . $value;
            }

            $mailContent = implode('<br>', $mailLines);

            $mail = new Zend_Mail();
            $mail->setBodyHtml($mailContent);
            $mail->setFrom('no-reply@shopping-feed.com', 'Shopping Feed Magento Extension');

            foreach ($sendTo as $email) {
                $mail->addTo($email, $email);
            }

            $mail->setSubject('ShoppingFeed installation on Magento');
            $mail->send();
        } catch (Exception $e) {
        }
    }
}
