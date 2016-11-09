<?php
// V1 DEPRECATED
/**
 * @deprecated
 */
class Profileolabs_Shoppingflux_Block_Export_Flow extends Mage_Core_Block_Template {

    protected $_attributes = null;
    protected $_attributesConfigurable = array();
    protected $_attributesOptions = array();
    protected $_fileName = "flow2.xml";
    protected $_storeCategories = array();

    public function __construct() {
        $this->forceStore();
    }

    protected function _getStoreId() {
        //store id is store view id

        return $this->_getStore()->getId();
        ;
    }

    protected function _getStore() {

        $storeId = (int) $this->getRequest()->getParam('store', Mage::app()->getStore()->getId());
        return Mage::app()->getStore($storeId);
    }

    protected function forceStore() {
        if (( $storeId = (int) $this->getRequest()->getParam('store', 0) ) != 0) {
            Mage::app()->setCurrentStore(Mage::app()->getStore($storeId));
        }
    }

    protected function _writeXml($xml, $start = false) {
        $mode = "a+";
        if ($start)
            $mode = "w+";
        $storeId = $this->_getStoreId();
        $storeCode = Mage::app()->getStore($storeId)->getCode();
        $dir = Mage::getBaseDir('media') . DS . "shoppingflux" . DS . $storeCode . DS;
        $file = new Varien_Io_File;
        $file->checkAndCreateFolder($dir);
        $file->cd($dir);
        $file->streamOpen($this->_fileName, $mode);
        $file->streamLock();
        $file->streamWrite($xml);
        $file->streamUnlock();
        $file->streamClose();

        if ($file->fileExists($this->_fileName)) {
            return $this->_fileName;
        }

        return false;
    }

    protected $_exludeProductIds = null;

    protected function _getExcludeProductIds($store = null) {
        Varien_Profiler::start("SF::Flow::_getExcludeProductIds");
        if (is_null($this->_exludeProductIds)) {
            $mageCacheKey = 'sf_exclude_pids';
            $fromCache = Mage::app()->loadCache($mageCacheKey);
            if ($fromCache) {
                $this->_exludeProductIds = unserialize($fromCache);
            } else {

                $this->_exludeProductIds = array();
                $collection = Mage::getModel('catalog/product')
                        ->getCollection()
                        ->addFieldToFilter('type_id', array('neq' => 'simple'));
                if ($store) {
                    $collection->setStore($store)
                            ->addStoreFilter($store);
                }

                $product = Mage::getModel('catalog/product');
                Mage::getSingleton('core/resource_iterator')
                        ->walk($collection->getSelect(), array(array($this, '_excludeProductIds')), array('product' => $product));
                Mage::app()->saveCache(serialize($this->_exludeProductIds), $mageCacheKey, array('shoppingflux', Mage_Catalog_Model_Product::CACHE_TAG));
            }
        }

        Varien_Profiler::stop("SF::Flow::_getExcludeProductIds");
        return $this->_exludeProductIds;
    }

    public function _excludeProductIds($args) {
        $product = $args['product'];
        $product->reset()->setTypeInstance(null, true);
        $product->setData($args['row']);

        if ($product->getTypeId() == 'configurable') {
            $childrenIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getChildrenIds($product->getEntityId());
            if ($childrenIds && is_array($childrenIds) && !empty($childrenIds))
                $this->_exludeProductIds = array_merge($this->_exludeProductIds, $childrenIds[0]);
        }
        if ($product->getTypeId() == 'grouped' || $product->getTypeId() == 'bundle' || $product->getTypeId() == 'virtual') {
            $this->_exludeProductIds = array_merge($this->_exludeProductIds, array($product->getEntityId()));
        }
        //sometimes magento return empty id, so we filter the array
        $this->_exludeProductIds = array_filter($this->_exludeProductIds);
    }

    protected function _toHtml() {
        Varien_Profiler::start("SF::Flow::toHtml");
        ini_set("memory_limit", $this->getConfig()->getMemoryLimit() . "M");
        $storeId = $this->_getStoreId();

        /*
          oringinal price - getPrice() - inputed in admin
          special price - getSpecialPrice()
          getFinalPrice() - used in shopping cart calculations
         */
        $product = Mage::getModel('catalog/product');

        $priceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/specific_prices/price', $this->_getStoreId());
        $specialPriceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/specific_prices/special_price', $this->_getStoreId());


        $otherAttributes = array(
            "shoppingflux_product" => "shoppingflux_product",
            "shoppingflux_default_category" => "shoppingflux_default_category",
            "tax_class_id" => "tax_class_id",
            "special_price" => "special_price",
            "minimal_price" => "minimal_price",
            "special_from_date" => "special_from_date",
            "special_to_date" => "special_to_date",
            "image" => "image",
            $priceAttributeCode => $priceAttributeCode,
            $specialPriceAttributeCode => $specialPriceAttributeCode
        );
        $attributesToSelect = array_merge($this->getAttributesFromConfig(true), $otherAttributes);


        $stockItemWhere = null;
        if (!$this->getConfig()->isExportSoldout()) {

            $_configManageStock = (int) Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK);
            $stockItemWhere = "({{table}}.is_in_stock = 1) "
                    . " OR IF({{table}}.use_config_manage_stock=1," . $_configManageStock . ",{{table}}.manage_stock)=0";
        }


        $products = $product->getCollection()
                ->addStoreFilter($storeId)
                //	->addAttributeToFilter('status',1)
                ->addAttributeToSelect($this->getRequiredAttributes(), 'left')
                ->joinTable('cataloginventory/stock_item', 'product_id=entity_id', array('qty' => 'qty', 'is_in_stock' => 'is_in_stock'), $stockItemWhere, 'left');




        //->addAttributeToSelect($attributesToSelect, 'left')
        foreach ($attributesToSelect as $key => $attributeCode) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeCode);
            if ($attributeCode == 'image' || $attributeCode == 'sku' || ($this->getFlatHelper()->isEnabled($storeId) && $attribute->getUsedInProductListing())) {
                $products->addAttributeToSelect($attributeCode);
            } else {
                try {
                    $products->joinAttribute($attributeCode, 'catalog_product/' . $attributeCode, 'entity_id', null, 'left', $storeId);
                } catch (Exception $e) {
                    //die($attributeCode);
                }
            }
        }

        /*
          if ($this->getFlatHelper()->isEnabled($storeId)) {
          /*->addAttributeToSelect($attributesToSelect, 'left') shouldnt be enough ?

          $allAttributesToSelect = array_unique($attributesToSelect);
          foreach ($allAttributesToSelect as $attributeToSelect) {
          if ($attributeToSelect == "sku")
          continue;

          $products->joinAttribute($attributeToSelect, 'catalog_product/' . $attributeToSelect, 'entity_id', null, 'left', $storeId);
          }
          } */


        //FILTER BY SHOPPING FLUX FLAG
        $sfProductFilterAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'shoppingflux_product');
        if ($this->getConfig()->isExportFilteredByAttribute()) {
            if ($this->getFlatHelper()->isEnabled($storeId) && $sfProductFilterAttribute->getUsedInProductListing()) {
                $products->addAttributeToFilter('shoppingflux_product', 1);
            } else {
                $prefixAttribute = 'at_';
                $currentVersion = Mage::getVersion();
                if (version_compare($currentVersion, '1.6.0') < 0)
                    $prefixAttribute = '_table_';

                $fieldName = $prefixAttribute . "shoppingflux_product";
                $products->getSelect()->where("if(`{$fieldName}`.`value`, `{$fieldName}`.`value`, `{$fieldName}_default`.`value`)=1");
                //$products->addAttributeToFilter('shoppingflux_product',1);
            }
        }

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($products);
        $products->setVisibility($this->getConfig()->getVisibilitiesToExport($this->_getStoreId()));
        //Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($products);

        $products->addIdFilter($this->_getExcludeProductIds(), true);
        
        if ($this->getProductSku()) {
            $products->addAttributeToFilter('sku', $this->getProductSku());
        }


        if ($this->getLimit()) {
            //$products->setPageSize($this->getLimit());
            $products->getSelect()->limit($this->getLimit());
        }


        // if($_SERVER['REMOTE_ADDR'] == "127.0.0.1" || $_SERVER['REMOTE_ADDR']=="90.2.197.14")
        //die('>'.$products->getSize().' '.$products->getSelect().' ');


        $this->iterateProducts($products);

        //$output = $xmlObj->createXml();
        // $this->_writeXml($output,true);
        Varien_Profiler::stop("SF::Flow::toHtml");
        //return $output;
    }

    public function iterateProducts($products, $withLoad = false) {

        Varien_Profiler::start("SF::Flow::iterateProducts");
        Varien_Profiler::start("SF::Flow::iterateProducts-0");
        $xmlObj = Mage::getModel('profileolabs_shoppingflux/export_xmlflow');
        $xmlObj->startXml();
        Varien_Profiler::stop("SF::Flow::iterateProducts-0");
        if (!$withLoad) {

            $product = Mage::getModel('catalog/product');
            /*
              using resource iterator to load the data one by one
              instead of loading all at the same time. loading all data at the same time can cause the big memory allocation.
             */
            Varien_Profiler::start("SF::Flow::iterateProducts-1");
            Mage::getSingleton('core/resource_iterator')
                    ->walk($products->getSelect(), array(array($this, 'addNewItemXmlCallback')), array('xmlObj' => $xmlObj, 'product' => $product));
            Varien_Profiler::stop("SF::Flow::iterateProducts-1");
        } else {
            $products->addMinimalPrice()
                    ->addFinalPrice()
                    ->addTaxPercents();
        }

        unset($products);
        $xmlObj->endXml();
        Varien_Profiler::stop("SF::Flow::iterateProducts");
        return $xmlObj;
    }

    public function addNewItemXml($args) {
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback");
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-1");
        $product = $args['product'];
        $product->reset()->setTypeInstance(null, true);
        // $product = Mage::getModel('catalog/product'); 	 
        $product->setData($args['row']);


        //To get isinstock correct value
        // Mage::getModel('cataloginventory/stock_item')->assignProduct($product);


        /* @var $xmlObj Profileolabs_Shoppingflux_Model_Export_Xmlflow  */
        $xmlObj = $args['xmlObj'];
        $data = array(
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'product-url' => $this->cleanUrl($product->getProductUrl(false)),
            'is-in-stock' => $product->getIsInStock(),
            'qty' => round($product->getQty()),
        );

        foreach ($this->getConfig()->getMappingAttributes($this->_getStoreId()) as $nameNode => $code) {
            $data[$nameNode] = trim($xmlObj->extractData($nameNode, $code, $product));
        }


        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-1");

        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-2");
        $data = $this->getPrices($data, $product);
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-2");
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-3");
        $data = $this->getImages($data, $product);
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-3");
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-4");
        $data = $this->getCategories($data, $product);
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-4");
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-5");
        $data = $this->getShippingInfo($data, $product);
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-5");
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-6");
        $data = $this->getConfigurableAttributes($data, $product);
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-6");
        Varien_Profiler::start("SF::Flow::addNewItemXmlCallback-7");
        ini_set('display_errors', true);
        error_reporting(-1);
        foreach ($this->getConfig()->getAdditionalAttributes() as $attributeCode) {
            if (!$attributeCode)
                continue;
            $data[$attributeCode] = trim($xmlObj->extractData($attributeCode, $attributeCode, $product));
            /*$attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeCode);
            if ($attribute->usesSource()) {
                $value = $product->getAttributeText($attributeCode);
            } else {
                $value = $product->getData($attributeCode);
            }
            if (is_array($value)) {
                $value = explode(',', $value);
            }
            if ($value) {
                $data[$attributeCode] = trim($value);
            }*/
        }


        //Exceptions data shipping_delay
        if (!isset($data['shipping_delay']) && empty($data['shipping_delay']))
            $data['shipping_delay'] = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');

        if (!$this->getConfig()->isExportSoldout()) {

            if (!$data["is-in-stock"]) {
                Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-7");
                Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback");
                return;
            }
        }


        $dataObj = new Varien_Object(array('entry' => $data));
        Mage::dispatchEvent('shoppingflux_before_add_entry', array('data_obj' => $dataObj));

        $entry = $dataObj->getEntry();

        $xmlObj->_addEntry($entry);
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback-7");
        Varien_Profiler::stop("SF::Flow::addNewItemXmlCallback");
    }
    
    public function addNewItemXmlCallback($args) {
        return  $this->addNewItemXml($args);
       /* if (! function_exists('pcntl_fork')) {
            return $this->addNewItemXml($args);
        }
        switch ($pid = pcntl_fork()) {
            case -1:
               //fork failed, just do as usual
               return $this->addNewItemXml($args);
               break;

            case 0:
               // @child
               return $this->addNewItemXml($args);
               break;

            default:
               // @parent
               pcntl_waitpid($pid, $status);
               break;
         }*/
        
    }

    protected function getShippingInfo($data, $product) {

        $data["shipping-name"] = "";
        $data["shipping-price"] = "";

        $carrier = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_method');
        if (empty($carrier)) {
            $data["shipping-price"] = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_price');
            return $data;
        }

        $carrierTab = explode('_', $carrier);
        list($carrierCode, $methodCode) = $carrierTab;
        $data["shipping-name"] = ucfirst($methodCode);


        $shippingPrice = 0;
        if($this->getConfig()->getConfigData('shoppingflux_export/general/try_use_real_shipping_price')) {
            $countryCode = $this->getConfig()->getConfigData('shoppingflux_export/general/shipping_price_based_on');
            Varien_Profiler::start("SF::Flow::getShippingInfo-1");
            $shippingPrice = $this->helper('profileolabs_shoppingflux')->getShippingPrice($product, $carrier, $countryCode);
            Varien_Profiler::stop("SF::Flow::getShippingInfo-1");
        }

        if (!$shippingPrice) {
            $shippingPrice = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_price');
        }

        $data["shipping-price"] = $shippingPrice;

        return $data;
    }

    protected function getConfigurableAttributes($data, $product) {

        Varien_Profiler::start("SF::Flow::getConfigurableAttributes");
        $data["configurable_attributes"] = "";
        $data["childs_product"] = "";
        $images = array();

        $labels = array();
        if ($product->getTypeId() == "configurable") {


            $attributes = $this->helper('profileolabs_shoppingflux')->getAttributesConfigurable($product);

            $attributesToSelect = array();
            $attributesToOptions = array();

            foreach ($attributes as $attribute) {
                $attributesToSelect[] = $attribute['attribute_code'];

                $attributesToOptions[$attribute['attribute_code']] = array();
            }


            $attributesToSelect[] = "sku";
            $attributesToSelect[] = "status";



            $priceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/specific_prices/price', $this->_getStoreId());
            $specialPriceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/specific_prices/special_price', $this->_getStoreId());


            $otherAttributes = array(
                "shoppingflux_product" => "shoppingflux_product",
                "shoppingflux_default_category" => "shoppingflux_default_category",
                "tax_class_id" => "tax_class_id",
                "special_price" => "special_price",
                "minimal_price" => "minimal_price",
                "special_from_date" => "special_from_date",
                "special_to_date" => "special_to_date",
                "image" => "image",
                $priceAttributeCode => $priceAttributeCode,
                $specialPriceAttributeCode => $specialPriceAttributeCode
            );
            
            $attributesFromConfig = $this->getAttributesFromConfig(true, true);
            $attributesFromConfig = array_slice($attributesFromConfig, 0, 42);// Too much attributes causes too much joins, which can overload mysql limits
            $attributesToSelect = array_merge($otherAttributes, $attributesFromConfig);
            //$attributesFromConfig = $this->getAttributesFromConfig(true, true);
            //if (isset($attributesFromConfig['ean']))
            //    $attributesToSelect[] = $attributesFromConfig['ean'];
            //$attributesToSelect = array_merge($attributesToSelect, $attributesFromConfig);

            $usedProducts = $product->getTypeInstance(true)
                    ->getUsedProductCollection($product);
            foreach($attributesToSelect as $attributeCode) {
                $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeCode);
                if ($attributeCode == 'image' || $attributeCode == 'sku' || ($this->getFlatHelper()->isEnabled($this->_getStoreId()) && $attribute->getUsedInProductListing())) {
                    $usedProducts->addAttributeToSelect($attributeCode, 'left');
                } else {
                    try {
                        $usedProducts->joinAttribute( $attributeCode, 'catalog_product/'.$attributeCode, 'entity_id', null, 'left' );
                    } catch (Exception $e) {
                        
                    }
                }
            }

            if ($this->getConfig()->isExportFilteredByAttribute()) {
                $usedProducts->addAttributeToFilter('shoppingflux_product', 1);
            }

            $configurableAttributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

            // var_dump($usedProducts->getSelect().'');die();


            $usedProductsArray = array();
            $salable = false;
            foreach ($usedProducts as $usedProduct) {
                $salable = $salable || $usedProduct->isSalable();


                if (Mage::helper('profileolabs_shoppingflux')->isModuleInstalled('OrganicInternet_SimpleConfigurableProducts')
                        || Mage::helper('profileolabs_shoppingflux')->isModuleInstalled('DerModPro_BCP')) {

                    $tmpData = $this->getPrices(array(), $usedProduct);
                    $price = $tmpData['price-ttc'] > 0 ? $tmpData['price-ttc'] : $data['price-ttc'];
                    if ($data['price-ttc'] <= 0 || ($price > 0 && $price < $data['price-ttc'])) {
                        $data['price-ttc'] = $price;
                    }
                    $priceBeforeDiscount = $tmpData["price-before-discount"];
                    $discountAmount = $tmpData["discount-amount"];
                    $startDateDiscount = $tmpData["start-date-discount"];
                    $endDateDiscount = $tmpData["end-date-discount"];
                } else {

                    $price = $data['price-ttc'];
                    $priceBeforeDiscount = $data["price-before-discount"];
                    $discountAmount = $data["discount-amount"];
                    $startDateDiscount = $data["start-date-discount"];
                    $endDateDiscount = $data["end-date-discount"];

                    foreach ($configurableAttributes as $configurableAttribute) {
                        $attributeCode = $configurableAttribute['attribute_code'];
                        foreach ($configurableAttribute['values'] as $confAttributeValue) {
                            if ($confAttributeValue['pricing_value'] && $usedProduct->getData($attributeCode) == $confAttributeValue['value_index']) {
                                if ($confAttributeValue['is_percent']) {
                                    $price += $data['price-ttc'] * $confAttributeValue['pricing_value'] / 100;
                                    $priceBeforeDiscount += $data['price-before-discount'] * $confAttributeValue['pricing_value'] / 100;
                                } else {
                                    $price += $confAttributeValue['pricing_value'];
                                    $priceBeforeDiscount += $confAttributeValue['pricing_value'];
                                }
                            }
                        }
                    }
                }



                $discountPercent = round((($priceBeforeDiscount - $price) * 100) / $priceBeforeDiscount);

                $isInStock = 0;
                $qty = 0;
                if ($usedProduct->getStockItem()) {
                    $isInStock = $usedProduct->getStockItem()->getIsInStock();
                    $qty = $usedProduct->getStockItem()->getQty();
                }

                $usedProductsArray[$usedProduct->getId()]['child']["sku"] = $usedProduct->getSku();
                $usedProductsArray[$usedProduct->getId()]['child']["id"] = $usedProduct->getId();
                $usedProductsArray[$usedProduct->getId()]['child']["price-ttc"] = $price;
                $usedProductsArray[$usedProduct->getId()]['child']["price-before-discount"] = $priceBeforeDiscount;
                $usedProductsArray[$usedProduct->getId()]['child']["discount-amount"] = $discountAmount;
                $usedProductsArray[$usedProduct->getId()]['child']["discount-percent"] = $discountPercent;
                $usedProductsArray[$usedProduct->getId()]['child']["start-date-discount"] = $startDateDiscount;
                $usedProductsArray[$usedProduct->getId()]['child']["end-date-discount"] = $endDateDiscount;
                $usedProductsArray[$usedProduct->getId()]['child']['is-in-stock'] = $isInStock;
                $usedProductsArray[$usedProduct->getId()]['child']['qty'] = round($qty);
                if ($qty > 0 && $qty > $data['qty']) {
                    $data['qty'] = round($qty);
                }
                $usedProductsArray[$usedProduct->getId()]['child']["ean"] = isset($attributesFromConfig['ean']) ? $usedProduct->getData($attributesFromConfig['ean']) : '';

                $images = $this->getImages($images, $usedProduct);
                if (!$images['image-url-1']) {
                    $images = $this->getImages($images, $product);
                }
                foreach ($images as $key => $value) {
                    $usedProductsArray[$usedProduct->getId()]['child'][$key] = trim($value);
                }



                foreach ($this->getConfig()->getMappingAttributes($this->_getStoreId()) as $nameNode => $attributeCode) {
                    $attributeId = Mage::getModel('eav/entity_attribute')->getIdByCode('catalog_product', $attributeCode);
                    $found = false;
                    foreach ($attributes as $attribute) {
                        if ($attribute['attribute_id'] == $attributeId) {
                            $found = true;
                        }
                    }
                    if (!$found) {
                        if (!isset($this->_attributesConfigurable[$attributeId]))
                            $this->_attributesConfigurable[$attributeId] = $product->getResource()->getAttribute($attributeId);

                        $attributeModel = $this->_attributesConfigurable[$attributeId];

                        $value = '';
                        if ($usedProduct->getData($attributeCode)) {
                            $value = $attributeModel->getFrontend()->getValue($usedProduct);
                        }


                        $usedProductsArray[$usedProduct->getId()]['child'][$nameNode] = trim($value);
                    }
                }


                //$usedProductsArray[$usedProduct->getId()]['child']['price'] = $usedProduct->getPrice();

                foreach ($attributes as $attribute) {
                    $attributeCode = $attribute['attribute_code'];
                    $attributeId = $attribute['attribute_id'];

                    if (!isset($this->_attributesConfigurable[$attributeId]))
                        $this->_attributesConfigurable[$attributeId] = $product->getResource()->getAttribute($attributeId);

                    $attributeModel = $this->_attributesConfigurable[$attributeId];

                    $value = '';
                    if ($usedProduct->getData($attributeCode)) {
                        $value = $attributeModel->getFrontend()->getValue($usedProduct);
                    }

                    if (!in_array($value, $attributesToOptions[$attributeCode]))
                        $attributesToOptions[$attributeCode][] = $value;

                    $usedProductsArray[$usedProduct->getId()]['child'][$attributeCode] = trim($value);
                }


                if (!isset($usedProductsArray[$usedProduct->getId()]['child']['shipping_delay']) || !$usedProductsArray[$usedProduct->getId()]['child']['shipping_delay'])
                    $usedProductsArray[$usedProduct->getId()]['child']['shipping_delay'] = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');
            }




            $data['is-in-stock'] = (int) $salable;

            foreach ($attributesToOptions as $attributeCode => $value) {

                $data["configurable_attributes"][$attributeCode] = implode(",", $value);
            }

            $data["childs_product"] = $usedProductsArray;

            unset($usedProducts);
            unset($usedProductsArray);
        }

        Varien_Profiler::stop("SF::Flow::getConfigurableAttributes");

        return $data;
    }

    /**
     * Get prices of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getPrices($data, $product) {

        Varien_Profiler::start("SF::Flow::getPrice");
        $priceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/specific_prices/price', $this->_getStoreId());
        $specialPriceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/specific_prices/special_price', $this->_getStoreId());
        if (!$product->getData($priceAttributeCode)) {
            $priceAttributeCode = 'price';
            $specialPriceAttributeCode = 'special_price';
        }

        $discountAmount = 0;
        $finalPrice = $product->getData($priceAttributeCode);
        $priceBeforeDiscount = $product->getData($priceAttributeCode);
        if ($product->getData($specialPriceAttributeCode) > 0 && $product->getData($specialPriceAttributeCode) < $finalPrice) {
            $finalPrice = $product->getData($specialPriceAttributeCode);
            $discountAmount = $product->getData($priceAttributeCode) - $product->getData($specialPriceAttributeCode);
        }
        $discountFromDate = $product->getSpecialFromDate();
        $discountToDate = $product->getSpecialToDate();


        $product->setCalculatedFinalPrice($finalPrice);
        $product->setData('final_price', $finalPrice);
        
        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.5.0') < 0) {
            
        } else {
            $catalogPriceRulePrice = Mage::getModel('catalogrule/rule')->calcProductPriceRule($product,$product->getPrice());
            if($catalogPriceRulePrice>0 && $catalogPriceRulePrice<$discountAmount) {
                $discountAmount = $catalogPriceRulePrice;
                $discountFromDate = '';
                $discountToDate = '';
            }
        }
       
        $data["price-ttc"] = $this->helper('tax')->getPrice($product, $finalPrice, true); //$finalPrice;
        $data["price-before-discount"] = $this->helper('tax')->getPrice($product, $priceBeforeDiscount, true); //$priceBeforeDiscount;
        $data["discount-amount"] = $product->getTypeId() != 'bundle' ? $discountAmount : 0;
        $data["discount-percent"] = $this->getPercent($product);

        $data["start-date-discount"] = "";
        $data["end-date-discount"] = "";
        if ($discountFromDate) {
            $data["start-date-discount"] = $discountFromDate;
        }
        if ($discountToDate) {
            $data["end-date-discount"] = $discountToDate;
        }
        Varien_Profiler::stop("SF::Flow::getPrice");
        return $data;
    }

    /**
     * Get categories of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getCategories($data, $product) {
        if ($product->getData('shoppingflux_default_category') && $product->getData('shoppingflux_default_category') > 0) {
            return $this->getCategoriesViaShoppingfluxCategory($data, $product);
        }
        return $this->getCategoriesViaProductCategories($data, $product);
    }

    protected function getCategoriesViaShoppingfluxCategory($data, $product) {

        $categoryId = $product->getData('shoppingflux_default_category');
        $category = Mage::helper('profileolabs_shoppingflux')->getCategoriesWithParents();
        if (!isset($category['name'][$categoryId])) {
            return $this->getCategoriesViaProductCategories($data, $product);
        }
        Varien_Profiler::start("SF::Flow::getCategoriesViaShoppingfluxCategory");

        $categoryNames = explode(' > ', $category['name'][$categoryId]);
        $categoryUrls = explode(' > ', $category['url'][$categoryId]);


        //we drop root category, which is useless here
        array_shift($categoryNames);
        array_shift($categoryUrls);

        $data['category-breadcrumb'] = trim(implode(' > ', $categoryNames));

        $data["category"] = trim($categoryNames[0]);
        $data["category-url"] = $categoryUrls[0];


        for ($i = 1; $i <= 5; $i++) {
            if (isset($categoryNames[$i]) && isset($categoryUrls[$i])) {
                $data["category-sub-" . ($i)] = trim($categoryNames[$i]);
                $data["category-url-sub-" . ($i)] = $categoryUrls[$i];
            } else {
                $data["category-sub-" . ($i)] = '';
                $data["category-url-sub-" . ($i)] = '';
            }
        }

        Varien_Profiler::stop("SF::Flow::getCategoriesViaShoppingfluxCategory");
        return $data;
    }

    protected function getCategoriesViaProductCategories($data, $product) {

        Varien_Profiler::start("SF::Flow::getCategoriesViaProductCategories");
        $sorted = false;
        $asCollection = true;
        $toLoad = false;
        $parent = Mage::app()->getStore()->getRootCategoryId();

        $cacheKey = sprintf('%d-%d-%d-%d', $parent, $sorted, $asCollection, $toLoad);
        if (!isset($this->_storeCategories[$cacheKey])) {

            /**
             * Check if parent node of the store still exists
             */
            $category = Mage::getModel('catalog/category');
            /* @var $category Mage_Catalog_Model_Category */
            if (!$category->checkId($parent)) {
                /* if ($asCollection) {
                  return new Varien_Data_Collection();
                  } */


                Varien_Profiler::stop("SF::Flow::getCategoriesViaProductCategories");
                return $data;
            }

            $recursionLevel = 0;
            $storeCategories = $category->getCategories($parent, $recursionLevel, $sorted, $asCollection, $toLoad);

            $storeCategoriesIds = array();
            foreach ($storeCategories as $cat) {
                $storeCategoriesIds[] = $cat['entity_id'];
            }
            $this->_storeCategories[$cacheKey] = $storeCategoriesIds;
        }

        Varien_Profiler::start("SF::Flow::getCategoriesViaProductCategories-1");
        $storeCategoriesIds = $this->_storeCategories[$cacheKey];



        $categoryCollection = $product->getCategoryCollection()
                ->addAttributeToSelect(array('name'))
                ->addFieldToFilter('level', array('lteq' => 5))
                ->addUrlRewriteToResult()
                ->groupByAttribute('level')
                ->setOrder('level', 'ASC');

        if (count($storeCategoriesIds) > 0)
            $categoryCollection->addFieldToFilter("entity_id", array("in" => $storeCategoriesIds));

        $nbCategories = $categoryCollection->count();

        $cnt = 0;
        $lastCategory = null;
        foreach ($categoryCollection as $category) {
            $name = $category->getName();
            $level = $category->getLevel();
            $url = $this->cleanUrl($category->getUrl());
            if ($cnt == 0) {

                $data["category"] = trim($name);
                $data["category-url"] = $url;
            } else {

                $data["category-sub-" . ($cnt)] = trim($name);
                $data["category-url-sub-" . ($cnt)] = $url;
            }

            $lastCategory = $category;

            $cnt++;
        }
        $data['category-breadcrumb'] = "";
        if (!is_null($lastCategory) && is_object($lastCategory)) {

            $breadCrumb = array();

            $pathInStore = $category->getPathInStore();
            $pathIds = array_reverse(explode(',', $pathInStore));

            $categories = $category->getParentCategories();

            // add category path breadcrumb
            foreach ($pathIds as $categoryId) {
                if (isset($categories[$categoryId]) && $categories[$categoryId]->getName()) {
                    $breadCrumb[] = trim($categories[$categoryId]->getName());
                }
            }
            unset($categories);
            $data['category-breadcrumb'] = trim(implode(" > ", $breadCrumb));
        }



        unset($categoryCollection);


        if ($nbCategories == 0) {
            $data["category"] = "";
            $data["category-url"] = "";

            $cnt++;
        }


        for ($i = ($cnt); $i <= 5; $i++) {
            $data["category-sub-" . ($i)] = "";
            $data["category-url-sub-" . ($i)] = "";
        }


        Varien_Profiler::stop("SF::Flow::getCategoriesViaProductCategories-1");
        Varien_Profiler::stop("SF::Flow::getCategoriesViaProductCategories");
        return $data;
    }

    public function cleanUrl($url) {
        $url = str_replace("index.php/", "", $url);

        return $url;
    }

    public function getImages($data, $product) {

        Varien_Profiler::start("SF::Flow::getImages");

        $mediaUrl = Mage::getBaseUrl('media') . 'catalog/product';

        $i = 1;

        if ($product->getImage() != "" && $product->getImage() != 'no_selection') {
            $data["image-url-" . $i++] = $mediaUrl . $product->getImage();
        }



        //LOAD media gallery for this product			
        $mediaGallery = $product->getResource()->getAttribute('media_gallery');
        $mediaGallery->getBackend()->afterLoad($product);


        foreach ($product->getMediaGallery('images') as $image) {
            if ($mediaUrl . $product->getImage() == $product->getMediaConfig()->getMediaUrl($image['file']))
                continue;

            $data["image-url-" . $i++] = $product->getMediaConfig()->getMediaUrl($image['file']);
            if (($i - 6) == 0)
                break;
        }


        //Complet with empty nodes
        for ($j = $i; $j < 6; $j++) {
            $data["image-url-" . $i++] = "";
        }
        Varien_Profiler::stop("SF::Flow::getImages");
        return $data;
    }

    /**
     * Get singleton config for Export
     * @return Profileolabs_Shoppingflux_Model_Export_Config
     */
    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    protected function getPercent($product) {

        /* if($product->getTypeId() == 'bundle')
          return 0; */
        $price = round($product->getPrice(), 2);
        if ($price == "0") {
            $price = round($product->getMinimalPrice(), 2);
        }

        if ($price == "0")
            return 0;

        $special = round($product->getFinalPrice(), 2);
        $tmp = $price - $special;
        $tmp = ($tmp * 100) / $price;
        return round($tmp);
    }

    /**
     * 
     */
    protected function getAttributesFromConfig($checkIfExist = false, $withAdditional = true) {

        Varien_Profiler::start("SF::Flow::getAttributesFromConfig");
        if (is_null($this->_attributes)) {
            $attributes = $this->getConfig()->getMappingAttributes();
            if ($withAdditional) {
                $additionalAttributes = $this->getConfig()->getAdditionalAttributes();
                foreach ($additionalAttributes as $attributeCode) {
                    $attributes[$attributeCode] = trim($attributeCode);
                }
            }

            if ($checkIfExist) {
                $product = Mage::getModel('catalog/product');
                foreach ($attributes as $key => $code) {

                    $attribute = $product->getResource()->getAttribute($code);
                    if ($attribute instanceof Mage_Catalog_Model_Resource_Eav_Attribute && $attribute->getId() && $attribute->getFrontendInput() != 'weee') {
                        $this->_attributes[$key] = $code;
                    }
                }
            }
            else
                $this->_attributes = $attributes;
        }

        Varien_Profiler::stop("SF::Flow::getAttributesFromConfig");
        return $this->_attributes;
    }

    protected function getRequiredAttributes() {

        $requiredAttributes = array("sku" => "sku",
            "price" => "price",
            "image" => "image");

        return $requiredAttributes;
    }

    protected function getAllAttributes() {
        return array_merge($this->getAttributesFromConfig(true), $this->getRequiredAttributes());
    }

    /**
     * Retrieve Catalog Product Flat Helper object
     *
     * @return Mage_Catalog_Helper_Product_Flat
     */
    public function getFlatHelper() {
        return Mage::helper('catalog/product_flat');
    }

}