<?php

/**
 * Shopping Flux Helper
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait / Vincent Enjalbert
 */
class Profileolabs_Shoppingflux_Helper_Data extends Mage_Core_Helper_Abstract {

    protected $_config = null;
    
    public function getFeedUrl($store, $action = 'index') {
        return preg_replace('%^(.*)\?.*$%i', '$1', $store->getUrl('shoppingflux/export_flux/'.$action));
    }
    
    public function generateTokens() {
        foreach(Mage::app()->getStores() as $store) {
            if(!trim(Mage::getConfig()->getNode('stores/'.$store->getCode().'/shoppingflux/configuration/api_key'))) {
                $shoppingFluxToken = Mage::getStoreConfig('shoppingflux/configuration/api_key', 0);
                if(!$shoppingFluxToken) {
                    $shoppingFluxToken = Mage::helper('profileolabs_shoppingflux')->generateToken($store->getId());
                }
                Mage::getConfig()->saveConfig('shoppingflux/configuration/api_key', $shoppingFluxToken, 'stores', $store->getId());
                Mage::getConfig()->cleanCache();
            }
        }
        Mage::getConfig()->saveConfig('shoppingflux/configuration/api_key', '', 'default');
    }
    
    public function generateToken($prefix='0') {
       return md5($prefix.$_SERVER['SERVER_ADDR'].time());
    }
    
    public function formatFeesDescription($fees, $marketplace) {
        return $this->__('%s fees', $marketplace);
    }
    
    public function truncateAddress($street, $lineMaxLength=35, $res = array()) {
        $street = trim($street);
        if(!$street) return array();
        if(preg_match('/^.{1,'.$lineMaxLength.'}(\s|$)/u', $street, $match)) {
            $line = trim($match[0]);
        } else {
            $line = mb_substr($street, 0 , $lineMaxLength);
        }
        $street = trim(mb_substr($street, strlen($line)));
        return array_merge(array($line), $this->truncateAddress($street, $lineMaxLength), $res);
    }
    
    /**
     * Returns the node and children as an array
     * 	values ares trimed
     *
     * @param bool $isCanonical - whether to ignore attributes
     * @return array|string
     */
    public function asArray(SimpleXMLElement $xml, $isCanonical = true) {
        $result = array();
        if (!$isCanonical) {
            // add attributes
            foreach ($xml->attributes() as $attributeName => $attribute) {
                if ($attribute) {
                    $result['@'][$attributeName] = trim((string) $attribute);
                }
            }
        }
        // add children values
        if ($xml->hasChildren()) {
            foreach ($xml->children() as $childName => $child) {
                if (!$child->hasChildren())
                    $result[$childName] = $this->asArray($child, $isCanonical);
                else
                    $result[$childName][] = $this->asArray($child, $isCanonical);
            }
        } else {
            if (empty($result)) {
                // return as string, if nothing was found
                $result = trim((string) $xml);
            } else {
                // value has zero key element
                $result[0] = trim((string) $xml);
            }
        }
        return $result;
    }

    public function log($message, $orderId = null) {
        $modelLog = Mage::getModel('profileolabs_shoppingflux/manageorders_log');

        $modelLog->log($message, $orderId);

        return $this;
    }

    public function isUnderVersion14() {
        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.4.0') < 0)
            return true;

        return false;
    }

    public function getShippingPrice($product, $carrierValue, $countryCode = "FR") {
        $carrierTab = explode('_', $carrierValue);
        list($carrierCode, $methodCode) = $carrierTab;
        $shipping = Mage::getModel('shipping/shipping');
        $methodModel = $shipping->getCarrierByCode($carrierCode);
        if ($methodModel) {
            if (is_object($result = $methodModel->collectRates($this->getRequest($product, $countryCode = "FR")))) {
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

    protected function getRequest($product, $countryCode = "FR") {
        /** @var $request Mage_Shipping_Model_Rate_Request */
        $request = Mage::getModel('shipping/rate_request');
        $storeId = $request->getStoreId();
        if (!$request->getOrig()) {
            $request
                    ->setCountryId($countryCode)
                    ->setRegionId("")
                    ->setCity("")
                    ->setPostcode("")
            ;
        }

        $item = Mage::getModel('sales/quote_item');
        $item->setStoreId($storeId);
        $item->setOptions($product->getCustomOptions())
                ->setProduct($product);

        $request->setAllItems(array($item));

        $request->setDestCountryId($countryCode);
        $request->setDestRegionId("");
        $request->setDestRegionCode("");
        $request->setDestPostcode("");
        $request->setPackageValue($product->getPrice());

        $request->setPackageValueWithDiscount($product->getFinalPrice());
        $request->setPackageWeight($product->getWeight());
        $request->setFreeMethodWeight(0);
        $request->setPackageQty(1);

        $request->setStoreId(Mage::app()->getStore()->getId());
        $request->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        $request->setBaseCurrency(Mage::app()->getStore()->getBaseCurrency());
        $request->setPackageCurrency(Mage::app()->getStore()->getCurrentCurrency());

        //$request->setLimitCarrier($limitCarrier);

        return $request;
    }

    public function getFilesGenerated() {
        $dirStores = array();
        $links = array();
        if (!is_dir(Mage::getBaseDir('media') . DS . 'shoppingflux')) {
            mkdir(Mage::getBaseDir('media') . DS . 'shoppingflux');
        }
        if ($handle = opendir(Mage::getBaseDir('media') . DS . 'shoppingflux' . DS)) {

            /* This is the correct way to loop over the directory. */
            while (false !== ($file = readdir($handle))) {
                if ($file == "." || $file == "..")
                    continue;
                $dirStores[] = $file;
            }

            closedir($handle);
        }

        foreach ($dirStores as $store) {
            $links[] = Mage::getBaseUrl('media') . "shoppingflux/" . $store . "/flow.xml";
        }
        return $links;
    }

    /**
     * Clean None utf-8 characters
     * @param string $value
     * @return string $value
     */
    public function cleanNotUtf8($value) {
        if (method_exists(Mage::helper('core/string'), "cleanString"))
            $value = Mage::helper('core/string')->cleanString($value);


        //reject overly long 2 byte sequences, as well as characters above U+10000 and replace with blank
        $value = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
                '|[\x00-\x7F][\x80-\xBF]+' .
                '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
                '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
                '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S', '', $value);

        //reject overly long 3 byte sequences and UTF-16 surrogates and replace with blank
        $value = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]' .
                '|\xED[\xA0-\xBF][\x80-\xBF]/S', '', $value);

        /* $value = preg_replace("/([\x80-\xFF])/e",
          "chr(0xC0|ord('\\1')>>6).chr(0x80|ord('\\1')&0x3F)",
          $value); */


        $value = str_replace(chr(31), "", $value);
        $value = str_replace(chr(30), "", $value);
        $value = str_replace(chr(29), "", $value);
        $value = str_replace(chr(28), "", $value);


        //$value = iconv("UTF-8","UTF-8//IGNORE",$value);


        return $value;
    }

    function _convert($content) {
        if (!mb_check_encoding($content, 'UTF-8') OR !($content === mb_convert_encoding(mb_convert_encoding($content, 'UTF-32', 'UTF-8'), 'UTF-8', 'UTF-32'))) {

            $content = mb_convert_encoding($content, 'UTF-8');

            if (mb_check_encoding($content, 'UTF-8')) {
                // log('Converted to UTF-8');
            } else {
                // log('Could not converted to UTF-8');
            }
        }
        return $content;
    }

    public function getAttributesConfigurable($product) {

        if (is_object($product))
            $product = $product->getId();

        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('catalog_read');

        $superAttributeTable = $resource->getTableName('catalog_product_super_attribute');
        $eavAttributeTable = $resource->getTableName('eav/attribute');

        $select = $read->select('attribute_id')
                ->from($superAttributeTable)
                ->join(array("att" => $eavAttributeTable), $superAttributeTable . '.attribute_id=att.attribute_id', array("attribute_code" => "attribute_code"))
                ->where("product_id = " . $product);

        $result = $read->fetchAll($select);
        return $result;
    }

    protected $_categoriesWithParents = null;
    public function getCategoriesWithParents($key = false, $storeId=null, $withInactive=false, $withNotInMenu=true) {
        

        if(is_null($this->_categoriesWithParents)) {
            $mageCacheKey = 'shoppingflux_category_list' . (Mage::app()->getStore()->isAdmin() ? '_admin'.intval($storeId) : '_' . intval($storeId)) ;
            $mageCacheKey .= $withInactive?'_inactive_':'_active_';
            $mageCacheKey .= $withNotInMenu?'all':'inmenu';
            $cacheTags = array(/*Mage_Catalog_Model_Category::CACHE_TAG,If On, cause this cache to be invalidated on product duplication :( Commenting this will maybe cause un-updated category list, but will improve performances*/ 'shoppingflux');
            $this->_categoriesWithParents = unserialize(Mage::app()->loadCache($mageCacheKey));
            if (!$this->_categoriesWithParents) {

                $rootCategoryId = Mage::app()->getStore($storeId)->getRootCategoryId();
                $this->_categoriesWithParents = array('name' => array(), 'url' => array(), 'id' => array());


                $categories = Mage::getResourceModel('catalog/category_collection');
                if($storeId) {
                    $categories->setStoreId($storeId);
                }
                $categories->addAttributeToSelect('name')
                        ->addAttributeToSelect('meta_title')
                        ->addAttributeToSelect('meta_description')
                        ->addAttributeToSelect('meta_keywords')
                        ->addAttributeToFilter('sf_exclude', array(array('is'=>new Zend_Db_Expr('NULL')),array('eq' => 0)), 'left')
                        ->addAttributeToFilter('entity_id', array('neq' => 1))
                        ->addAttributeToSort('path', 'ASC')
                        ->addAttributeToSort('name', 'ASC');
                
                //echo $categories->getSelect().'';
                if(!$withInactive) {
                  $categories->addFieldToFilter('is_active', array('eq'=>'1'));
                }
                if(!$withNotInMenu) {
                    if(version_compare($currentVersion, '1.4.0') > 0) {
                        $categories->addFieldToFilter('include_in_menu', array('eq'=>'1'));
                    }
                }
                
                if(!Mage::getSingleton('profileolabs_shoppingflux/config')->getUseAllStoreCategories()) {
                    $categories
                        ->addAttributeToFilter('entity_id', array('neq' => $rootCategoryId));
                    if($rootCategoryId!=0) {
                        $categories->addFieldToFilter('path', array('like' => "1/{$rootCategoryId}/%"));
                    }
                }

                foreach ($categories as $category) {
                    $parent = $category->getParentId();
                    while ($parent > 1) {
                        $parentCategory = Mage::getModel('catalog/category')->setStoreId($storeId)->load($parent);
                        if(!$parentCategory->getSfExclude()) {
                            $category->setName($parentCategory->getName() . " > " . $category->getName());
                            $category->setMetaTitle($parentCategory->getMetaTitle() . " > " . $category->getMetaTitle());
                            $category->setMetaDescription($parentCategory->getMetaDescription() . " > " . $category->getMetaDescription());
                            $category->setMetaKeywords($parentCategory->getMetaKeywords() . " > " . $category->getMetaKeywords());
                            if (!Mage::app()->getStore()->isAdmin()) {
                                //To avoid exception launched by third part module : ManaPro_FilterSeoLinks
                                $category->setUrl($parentCategory->getUrl() . " > " . $category->getUrl());
                            }
                            $category->setIds($parentCategory->getId() . " > " . $category->getIds() ? $category->getIds() : $category->getId());
                        }
                        $parent = $parentCategory->getParentId();
                    }
                }

                foreach ($categories as $_category) {
                    $this->_categoriesWithParents['name'][$_category->getId()] = $_category->getName();
                    $this->_categoriesWithParents['meta_title'][$_category->getId()] = $_category->getMetaTitle();
                    $this->_categoriesWithParents['meta_description'][$_category->getId()] = $_category->getMetaDescription();
                    $this->_categoriesWithParents['meta_keywords'][$_category->getId()] = $_category->getMetaKeywords();
                    if ($this->isModuleInstalled('ManaPro_FilterSeoLinks') && Mage::app()->getStore()->isAdmin()) {
                        $this->_categoriesWithParents['url'][$_category->getId()] = '';
                    } else {
                        $this->_categoriesWithParents['url'][$_category->getId()] = $_category->getUrl();
                    } 
                    $this->_categoriesWithParents['id'][$_category->getId()] = $_category->getIds();
                }
                Mage::app()->saveCache(serialize($this->_categoriesWithParents), $mageCacheKey, $cacheTags);

                unset($categories);
            }
        }

        if ($key && isset($this->_categoriesWithParents[$key]))
            return $this->_categoriesWithParents[$key];
        return $this->_categoriesWithParents;
    }

    public function isModuleInstalled($module) {
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modulesArray = (array) $modules;

        return isset($modulesArray[$module]);
    }

    public function notifyError($message) {
        //MAIL
        $emailTemplate = Mage::getModel('core/email_template')
                ->loadDefault('shoppingflux_alert');
        $emailTemplateVariables = array();
        $emailTemplateVariables['message'] = $message;
        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
        $emailTemplate->setSenderName('Magento/ShoppingFlux');
        $emailTemplate->setSenderEmail('no-reply@magento-shoppingflux.com');
        $emailTemplate->send(Mage::getStoreConfig('shoppingflux/configuration/alert_email'), Mage::getStoreConfig('shoppingflux/configuration/alert_email'), $emailTemplateVariables);

        // Notification
        try {
            $notification = Mage::getModel('adminnotification/inbox');
            $notification->setseverity(Mage_AdminNotification_Model_Inbox::SEVERITY_CRITICAL);
            $notification->setTitle(Mage::helper('profileolabs_shoppingflux')->__('Shoppingflux alert'));
            $notification->setDateAdded(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())));
            $notification->setDescription(Mage::helper('profileolabs_shoppingflux')->__('Shoppingflux alert : <br/> %s', $message));
            $notification->save();
        } catch (Exception $e) {
            //var_dump($e->getMessage());die();
        }
    }
    
    
    public function getConfig() {
        if (is_null($this->_config)) {
            $this->_config = Mage::getSingleton('profileolabs_shoppingflux/config');
        }

        return $this->_config;
    }
    
    public function isRegistered() {
        if(Mage::getStoreConfigFlag('shoppingflux/configuration/has_registered')) {
           return true; 
        } else if(time() - intval(Mage::getStoreConfig('shoppingflux/configuration/registration_last_check')) > 24*60*60) {
            foreach(Mage::app()->getStores() as $store) {
                $apiKey = $this->getConfig()->getApiKey($store->getId());
                $wsUri = $this->getConfig()->getWsUri($store->getId());
                $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
                if($service->isClient()) {
                    return true;
                }
            }
            $config = new Mage_Core_Model_Config();
            $config->saveConfig('shoppingflux/configuration/registration_last_check', time());
        }
        return false;
    }
    
    public function newInstallation() {
        try {
            $sendTo = array('olivier@shopping-feed.com', 'andy@shopping-feed.com');
            $mailContent = array();
            $mailContent['Magento URL'] = Mage::helper("adminhtml")->getUrl();
            if(!preg_match('%HTTP%', $mailContent['Magento URL'])) {
                $mailContent['Magento URL'] = @$_SERVER['HTTP_HOST'].' / ' .@$_SERVER['HTTP_REFERER']." / ".@$_SERVER['SERVER_NAME'];
            }
            $mailContent['Module Version'] =  Mage::getConfig()->getModuleConfig("Profileolabs_Shoppingflux")->version;
            $mailContent['Email'] = Mage::getStoreConfig('trans_email/ident_general/email');
            $mailContent['Config Country'] = Mage::getStoreConfig('shipping/origin/country_id');
            $mailContent['Config Address'] = Mage::getStoreConfig('shipping/origin/street_line1') .' ' . Mage::getStoreConfig('shipping/origin/street_line2') .  ' ' . Mage::getStoreConfig('shipping/origin/postcode') . ' ' . Mage::getStoreConfig('shipping/origin/city');
            
            
            $stores = Mage::app()->getStores();
            if(count($stores) == 0) {
                Mage::app()->reinitStores();
                $stores = Mage::app()->getStores();
            }
            foreach($stores as $store) {
                $mailContent['Store #'.$store->getId(). ' Name '] = $store->getWebsite()->getName(). ' > ' . $store->getGroup()->getName(). ' > ' . $store->getName();
                $mailContent['Store #'.$store->getId(). ' Url '] = $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
                $mailContent['Store #'.$store->getId(). ' Feed '] = Mage::helper('profileolabs_shoppingflux')->getFeedUrl($store);
                $mailContent['Store #'.$store->getId(). ' Refresh Url '] = Mage::helper('profileolabs_shoppingflux')->getFeedUrl($store, 'refreshEverything');
                $mailContent['Store #'.$store->getId(). ' Status Url '] = Mage::helper('profileolabs_shoppingflux')->getFeedUrl($store, 'status');
                $mailContent['Store #'.$store->getId(). ' is default ?'] = (Mage::app()->getDefaultStoreView()->getId() == $store->getId() ? 'Yes' : 'No');
            }
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $mailContent['Products count'] = $productCollection->count();
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($productCollection);
            $mailContent['Active products count'] = $productCollection->count();
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('type_id', 'configurable');
            $mailContent['Configurable products count'] = $productCollection->count();
            
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('type_id', 'simple');
            $mailContent['Simple products count'] = $productCollection->count();
            
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('type_id', 'vitual');
            $mailContent['Virtuals products count'] = $productCollection->count();
            
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('type_id', 'downloadable');
            $mailContent['Downloadable products count'] = $productCollection->count();
            
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('type_id', 'grouped');
            $mailContent['Grouped products count'] = $productCollection->count();
            
            
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('type_id', 'bundle');
            $mailContent['Bundle products count'] = $productCollection->count();

            $mailLines = array();
            foreach($mailContent as $k=>$v) {
                $mailLines[] = '<strong>' . $k . ' : </strong>' . $v;
            }
            $mailContent = implode("<br>", $mailLines);
           
            $mail = new Zend_Mail();
            $mail->setBodyHtml($mailContent);
            $mail->setFrom('no-reply@shopping-feed.com', 'Shopping Feed Magento Extension');
            foreach($sendTo as $email) {
                $mail->addTo($email,$email);
            }
            $mail->setSubject('ShoppingFeed installation on Magento');
            $mail->send();
        
        } catch(Exception $e) {
            
        }
    }

    
}
