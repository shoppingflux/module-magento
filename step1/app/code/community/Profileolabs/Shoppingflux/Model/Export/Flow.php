<?php

/**
 * 
 * @deprecated deprecated since 0.1.1
 *
 */
class Profileolabs_Shoppingflux_Model_Export_Flow {

    protected $_exceptions = array();
    protected $_lastCount = 0;
    protected $_totalCount = null;
    protected $_totalOffset = null;
    protected $_fileName = "flow.xml";
    protected $_xmlFlow = "";
    protected $_attributes = null;
    protected $_storeId = 0;
    protected $_errors = array();



    /*
     * Products collection
     * @var Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
     */
    protected $_productCollection = null;

    /**
     * Get node XML for a product
     * @param  Mage_Catalog_Model_Product $product
     * @return string $nodeXml
     */
    protected function _getProductNode($product) {

        $_helper = Mage::helper('catalog/output');

        $product->setStoreId($this->getStoreId());



        $nodeXml = "<product>" . chr(10);

        $nodeXml .= $this->getNode("id-product", $product->getId());

        //$nodeXml .= $this->getNode("sku",$product->getSku());

        foreach ($this->getAttributesForXml() as $nameNode => $valueNode) {

            $data = $product->getData($valueNode);



            $attribute = $product->getResource()->getAttribute($valueNode);
            if ($attribute) {

                $data = $product->getResource()->getAttribute($valueNode)->getFrontend()->getValue($product);
                $data = $_helper->productAttribute($product, $data, $valueNode);
            }



            //Synthetize it
            $method = "get" . ucfirst($valueNode);
            if (method_exists($product, $method))
                $data = $product->$method();

            //TODO remove this
            if ($data == "No" || $data == "Non")
                $data = "";

            //Exceptions data
            if ($nameNode == 'shipping_delay' && empty($data))
                $data = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');

            if ($nameNode == 'quantity')
                $data = round($data);


            $nodeXml .= $this->getNode($nameNode, $data);
        }

        //Add stock status
        $nodeXml .= $this->getNode("is-in-stock", (int) $product->isSaleable());

        //Add product url
        $nodeXml .= $this->getNode("product-url", $this->cleanUrl($product->getProductUrl(false)));

        //Add image's nodes
        $nodeXml .= $this->getImages($product);

        //Add categories
        $nodeXml .= $this->getCategories($product);

        //ADD Prices
        $nodeXml .= $this->getPrices($product);

        //ADD PARENT ID
        $nodeXml .= $this->getParentId($product);

        //ADD Shipping Name
        $nodeXml .= $this->getShippingName();

        //Add ShippingPrice
        $nodeXml .= $this->getShippingPrice($product);

        //Add Options
        $nodeXml .= $this->getOptions($product);

        $nodeXml .= $this->getNode("sku", $product->getSku());

        $nodeXml .= "</product>" . chr(10);


        return $nodeXml;
    }

    protected function getOptions($product) {
        $nodes = "<options>" . chr(10);
        $labels = array();
        if ($product->getTypeId() == "configurable") {
            $attributes = $product->getTypeInstance()->getConfigurableAttributes();
            foreach ($attributes as $attribute) {
                $options = $attribute->getProductAttribute()->getSource()->getAllOptions(false);
                if (count($options)) {
                    $labels = array();
                    foreach ($options as $option)
                        $labels[] = $option['label'];

                    $nodes .= $this->getNode($attribute->getProductAttribute()->getAttributeCode(), implode(',', $labels));
                }
            }
        }
        /* elseif(count($product->getOptions()))
          {
          $options = $product->getOptions();
          foreach($options as $option)
          {
          foreach($option->getValues() as $value)
          {
          $labels[] = $value->getTitle();
          }

          $nodes .= $this->getNode($option->getTitle(),implode(',',$labels));

          }
          } */


        $nodes .= "</options>" . chr(10);
        return $nodes;
    }

    protected function getShippingPrice($product) {
        $carrier = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_method');
        $shippingPrice = 0;
        if (!empty($carrier)) {

            $countryCode = $this->getConfig()->getConfigData('shoppingflux_export/general/shipping_price_based_on');
            $shippingPrice = $this->getHelper()->getShippingPrice($product, $carrier, $countryCode);
        }

        if (!$shippingPrice) {
            $shippingPrice = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_price');
        }

        $node = "";
        $node = $this->getNode("shipping-price", $shippingPrice);

        return $node;
    }

    protected function getShippingName() {
        $node = "";
        $carrier = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_method');
        if (empty($carrier))
            return $node;
        $carrierTab = explode('_', $carrier);
        list($carrierCode, $methodCode) = $carrierTab;

        $node = $this->getNode("shipping-name", ucfirst($methodCode));

        return $node;
    }

    protected function getParentId($product, $raw = false) {
        $node = "";
        $ids = array();
        $ids = Mage::getResourceSingleton('catalog/product_type_configurable')
                ->getParentIdsByChild($product->getId());

        if (!count($ids))
            $ids = Mage::getResourceSingleton('bundle/selection')
                    ->getParentIdsByChild($product->getId());

        $idParent = count($ids) ? current($ids) : "";

        if ($raw)
            return $idParent;

        $node = $this->getNode("parent-id", $idParent);

        return $node;
    }

    private function _getMediaGallery($product) {
        $media = $product->getData('media_gallery');
        if (is_null($media)) {
            if ($attribute = $product->getResource()->getAttribute('media_gallery')) {
                $attribute->getBackend()->afterLoad($product);
                $media = $product->getData('media_gallery');
            }
        }
        return $media;
    }

    private function _imageIsValid($url) {
        return (trim($url) != '' && !preg_match('%(no_selection|no_image\.jpg)$%i', $url));
    }

    /**
     * Get images of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getImages($product) {
        $nodes = "";

        $mediaUrl = Mage::getBaseUrl('media') . 'catalog/product';

        $images = array();

        $baseImage = Mage::helper('catalog/product')->getImageUrl($product);
        if ($this->_imageIsValid($baseImage)) {
            $images[] = $baseImage;
        }
        $smallimage = Mage::helper('catalog/product')->getSmallImageUrl($product);
        if ($this->_imageIsValid($smallimage)) {
            $images[] = $smallimage;
        }

        $additionalImages = $this->_getMediaGallery($product);
        if ($additionalImages)
            $additionalImages = $additionalImages['images'];
        foreach ($additionalImages as $additionalImage) {
            $image = Mage::getSingleton('catalog/product_media_config')->getMediaUrl($additionalImage['file']);
            if ($this->_imageIsValid($image)) {
                $images[] = $image;
            }
        }

        $images = array_unique($images);
        $images = array_values($images);

        if (empty($images)) {
            $parentId = $this->getParentId($product, true);
            if (!empty($parentId)) {
                $parent = Mage::getModel('catalog/product')->load($parentId);
                return $this->getImages($parent);
            }
        }

        foreach ($images as $i => $image) {
            $nodes .= $this->getNode("image-url-" . ($i + 1), $image);
        }
        

//Complet with empty nodes
        for ($j = $i+1; $j < 6; $j++) {
            $nodes .= $this->getNode("image-url-" . $j, '');
        }
        
        return $nodes;
        
        
        
        


/*
        $i = 1;
        $imagesFound = false;
        if ($product->getImage() != "" && $product->getImage() != 'no_selection') {
            $nodes .= $this->getNode("image-url-" . $i++, $mediaUrl . $product->getImage());
            $imagesFound = true;
        }

        //LOAD media gallery for this product
        $mediaGallery = $product->getResource()->getAttribute('media_gallery');
        $mediaGallery->getBackend()->afterLoad($product);

        if ($product->getMediaGalleryImages() instanceof Varien_Data_Collection) {
            foreach ($product->getMediaGalleryImages() as $image) {
                $imagesFound = true;
                $nodes .= $this->getNode("image-url-" . $i++, $image->getUrl());
                if ($i == 6)
                    break;
            }
        }




        if (!$imagesFound) {//Images not found, we try to get them from parent if exists
            $parentId = $this->getParentId($product, true);
            if (!empty($parentId)) {
                $parent = Mage::getModel('catalog/product')->load($parentId);
                if ($parent->getId()) {
                    if ($parent->getImage() != "" && $parent->getImage() != 'no_selection') {
                        $nodes .= $this->getNode("image-url-" . $i++, $mediaUrl . $parent->getImage());
                    }

                    $mediaGallery = $parent->getResource()->getAttribute('media_gallery');
                    $mediaGallery->getBackend()->afterLoad($parent);
                    if ($parent->getMediaGalleryImages() instanceof Varien_Data_Collection)
                        foreach ($parent->getMediaGalleryImages() as $image) {
                            $nodes .= $this->getNode("image-url-" . $i++, $image->getUrl());
                            if ($i == 6)
                                break;
                        }
                }
            }
        }

        //Complet with empty nodes
        for ($j = $i; $j < 6; $j++) {
            $nodes .= $this->getNode("image-url-" . $i++, '');
        }


        return $nodes;*/
    }

    /**
     * Get categories of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getCategories($product) {
        $nodes = "";
        $categoryCollection = $product->getCategoryCollection()
                ->addAttributeToSelect(array('name'))
                ->addFieldToFilter('level', array('lteq' => 5))
                ->addUrlRewriteToResult()
                ->groupByAttribute('level')
                ->setOrder('level', 'ASC')
        ;

        $nbCategories = $categoryCollection->count();
        //echo "pId = ".$product->getId()." nb = {$nbCategories}<br />";
        if ($nbCategories < 1) {
            //Get categories from parent if exist
            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')
                    ->getParentIdsByChild($product->getId());

            foreach ($parentIds as $parentId) {
                $categoryCollection = Mage::getResourceSingleton('catalog/product')
                        ->getCategoryCollection(new Varien_Object(array("id" => $parentId)))
                        ->addAttributeToSelect(array('name'))
                        ->addFieldToFilter('level', array('lteq' => 5))
                        ->addUrlRewriteToResult()
                        ->groupByAttribute('level')
                        ->setOrder('level', 'ASC');

                if (($nbCategories = $categoryCollection->count()) > 0)
                    break;
            }
        }


        $cnt = 0;

        foreach ($categoryCollection as $category) {
            //$category->setStoreId($this->getStoreId()); //When store is setted the rewrite url don't found 
            $category->getUrlInstance()->setStore($this->getStoreId());
            $category->getUrlInstance()->setUseSession(false);

            $name = $category->getName();
            $level = $category->getLevel();
            $url = $this->cleanUrl($category->getUrl());
            if ($cnt == 0) {

                $nodes .= $this->getNode("category", $name);
                $nodes .= $this->getNode("category-url", $url);
            } else {

                $nodes .= $this->getNode("category-sub-" . ($cnt), $name);
                $nodes .= $this->getNode("category-url-sub-" . ($cnt), $url);
            }

            $cnt++;
        }
        if ($nbCategories == 0) {
            $nodes .= $this->getNode("category", "");
            $nodes .= $this->getNode("category-url", "");

            $cnt++;
        }


        for ($i = ($cnt); $i <= 5; $i++) {
            $nodes .= $this->getNode("category-sub-" . $i, "");
            $nodes .= $this->getNode("category-url-sub-" . $i, "");
        }

        return $nodes;
    }

    /**
     * Get prices of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getPrices($product) {
        $nodes = "";

        $finalPrice = $product->getFinalPrice() != 0 ? $product->getFinalPrice() : $product->getData('price');

        $nodes .= $this->getNode("price-ttc", $finalPrice);
        $nodes .= $this->getNode("price-before-discount", $product->getPrice());
        $nodes .= $this->getNode("discount-amount", $product->getTypeId() != 'bundle' ? $product->getSpecialPrice() : 0);



        $nodes .= $this->getNode("discount-percent", $this->getPercent($product));

        if ($product->getSpecialFromDate() != "" && Zend_Date::isDate($product->getSpecialFromDate())) {
            $startDate = new Zend_Date($product->getSpecialFromDate());
            $nodes .= $this->getNode("start-date-discount", $startDate->toString(Zend_Date::ISO_8601));
        }
        if ($product->getSpecialToDate() != "" && Zend_Date::isDate($product->getSpecialToDate())) {

            $endDate = new Zend_Date($product->getSpecialToDate());
            $nodes .= $this->getNode("end-date-discount", $endDate->toString(Zend_Date::ISO_8601));
        }

        return $nodes;
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

    /*
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
     */

    public function getProductCollection() {
        if (is_null($this->_productCollection)) {
            $this->_productCollection = Mage::getResourceModel('catalog/product_collection');
            $this->_productCollection->addStoreFilter($this->getStoreId())
                    ->addAttributeToSelect($this->getAllAttributes())
                    ->addAttributeToFilter('status', 1)
                    ->addAttributeToSort('entity_id', 'asc')
                    ->joinField('qty', 'cataloginventory/stock_item', 'qty', 'product_id=entity_id', '{{table}}.stock_id=1', 'left')
            //->addAttributeToFilter('visibility',array('nin' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE));
            ;

            $this->_productCollection->joinTable(
                    'core/url_rewrite', 'product_id=entity_id', array('request_path'), '{{table}}.category_id IS NULL AND {{table}}.is_system = 1 AND {{table}}.store_id = ' . $this->getStoreId() . '', 'left'
            );



            if ($this->getConfig()->isExportFilteredByAttribute()) {
                $this->_productCollection->addAttributeToFilter('shoppingflux_product', array('eq' => 1));
            }
            $this->_totalCount = $this->_productCollection->getSize();

            $this->_totalOffset = ceil((int) $this->_totalCount / $this->getLimit());
        }

        return $this->_productCollection;
    }

    public function getCollectionByOffset($offset = 1) {
        $this->getProductCollection()->clear()->setPage($offset, $this->getLimit());
        //$this->_productCollection->addOptionsToResult();
        Mage::getModel('cataloginventory/stock_status')->addStockStatusToProducts($this->getProductCollection(), Mage::app()->getStore($this->getStoreId())->getWebsiteId());
        $this->_lastCount = $this->getProductCollection()->count();

        return $this->getProductCollection();
    }

    public function generateProductsNodes($offset = 1) {
        $start = false;
        if ($offset == 1) {

            $this->_xmlFlow = '<?xml version="1.0" encoding="utf-8"?>' . chr(10);
            $this->_xmlFlow .= "<products>" . chr(10);
            $start = true;
        }

        foreach ($this->getCollectionByOffset($offset) as $product) {
            try {

                if (!$this->_validate($product)) {
                    $this->_lastCount--;
                    continue;
                }

                $this->_xmlFlow .= $this->_getProductNode($product);
            } catch (Exception $e) {
                $this->_errors[] = 'Product ID = ' . $product->getId() . "<br />" . $e->getMessage();
            }
        }

        if ($offset == $this->_totalOffset) {
            $this->closeFlow();
        }

        $this->_writeXml($start);

        return $this;
        //TODO maybe unset product collection
    }

    public function getErrors() {
        return $this->_errors;
    }

    public function reset() {
        unset($this->_productCollection);
        $this->_productCollection = null;
        $this->_xmlFlow = null;

        return $this;
    }

    protected function getNode($name, $value, $withCDATA = 1) {
        $value = $this->getHelper()->cleanNotUtf8($value);
        $openCDATA = "";
        $closeCDATA = "";
        if ($withCDATA) {
            $openCDATA = "<![CDATA[";
            $closeCDATA = "]]>";
        }
        return "<{$name}>{$openCDATA}{$value}{$closeCDATA}</{$name}>" . chr(10);
    }

    public function closeFlow() {
        $this->_xmlFlow .= "</products>";
        //$this->_writeXml();
    }

    protected function _writeXml($start = false) {
        $mode = "a+";
        if ($start)
            $mode = "w+";
        $storeId = $this->getStoreId();
        $storeCode = Mage::app()->getStore($storeId)->getCode();
        $dir = Mage::getBaseDir('media') . DS . "shoppingflux" . DS . $storeCode . DS;
        $file = new Varien_Io_File;
        $file->checkAndCreateFolder($dir);
        $file->cd($dir);
        $file->streamOpen($this->_fileName, $mode);
        $file->streamLock();
        $file->streamWrite($this->_xmlFlow);
        $file->streamUnlock();
        $file->streamClose();

        if ($file->fileExists($this->_fileName)) {
            return $this->_fileName;
        }

        return false;
    }

    public function getXml() {
        return $this->_xmlFlow;
    }

    public function getLastCount() {
        return $this->_lastCount;
    }

    public function getLimit() {
        return $this->getConfig()->getExportProductLimit();
    }

    public function setException($error, $level = null, $position = 0) {

        $e = new Mage_Dataflow_Model_Convert_Exception($error);
        $e->setLevel(!is_null($level) ? $level : Mage_Dataflow_Model_Convert_Exception::NOTICE);
        // $e->setContainer($this);
        $e->setPosition($position);

        $this->_exceptions[] = $e;
        return $this;
    }

    public function getExceptions() {
        $exceptions = array();
        foreach ($this->_exceptions as $e) {

            switch ($e->getLevel()) {
                case Varien_Convert_Exception::FATAL:
                    $img = 'error_msg_icon.gif';
                    $liStyle = 'background-color:#FBB; ';
                    break;
                case Varien_Convert_Exception::ERROR:
                    $img = 'error_msg_icon.gif';
                    $liStyle = 'background-color:#FDD; ';
                    break;
                case Varien_Convert_Exception::WARNING:
                    $img = 'fam_bullet_error.gif';
                    $liStyle = 'background-color:#FFD; ';
                    break;
                case Varien_Convert_Exception::NOTICE:
                    $img = 'fam_bullet_success.gif';
                    $liStyle = 'background-color:#DDF; ';
                    break;
            }

            $exceptions[] = array(
                "style" => $liStyle,
                "src" => Mage::getDesign()->getSkinUrl('images/' . $img),
                "message" => $e->getMessage(),
                "position" => $e->getPosition()
            );
        }

        return $exceptions;
    }

    /**
     * Get singleton config for Export
     * @return Profileolabs_Shoppingflux_Model_Export_Config
     */
    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    /**
     * 
     */
    protected function getAttributesForXml() {
        if (is_null($this->_attributes)) {
            $this->_attributes = $this->getConfig()->getMappingAllAttributes();
        }

        return $this->_attributes;
    }

    protected function getAllAttributes() {
        $requiredAttributes = array(
            "sku" => "sku",
            "price" => "price",
            "special_price" => "special_price",
            "minimal_price" => "minimal_price",
            "special_from_date" => "special_from_date",
            "special_to_date" => "special_to_date",
            "image" => "image");

        return array_merge($this->getAttributesForXml(), $requiredAttributes);
    }

    public function generateDirectly() {

        if (!$this->getConfig()->isExportEnabled())
            return $this;

        try {
            $stores = Mage::getModel('core/store')->getCollection();
            $product = Mage::getModel('catalog/product');
            foreach ($stores as $store) {
                if ($store->getId() == 0)
                    continue;

                $this->setStoreId($store->getId());

                $flowItemsCount = $this->getProductCollection()->getSize();
                $offsets = ceil((int) $flowItemsCount / $this->getLimit());
                if ($offsets == 0)
                    $offsets = 1;

                $this->_xmlFlow = '<?xml version="1.0" encoding="utf-8"?>' . chr(10);
                $this->_xmlFlow .= "<products>" . chr(10);

                for ($i = 1; $i <= $offsets; $i++) {

                    //Mage::log('offset = '.$i,null,"test_flow.log");
                    $collection = $this->getCollectionByOffset($i);
                    foreach ($collection as $product) {
                        //Mage::log('pId = '.$product,null,"test_flow.log");
                        try {

                            if (!$this->_validate($product)) {
                                $this->_lastCount--;
                                continue;
                            }

                            $this->_xmlFlow .= $this->_getProductNode($product);
                        } catch (Exception $e) {
                            $this->_errors[] = 'Product ID = ' . $product->getId() . "<br />" . $e->getMessage();
                        }
                    }

                    /*
                      using resource iterator to load the data one by one
                      instead of loading all at the same time. loading all data at the same time can cause the big memory allocation.
                     */
                    /* Mage::getSingleton('core/resource_iterator')
                      ->walk($collection->getSelect(), array(array($this, 'addItemXmlCallback')), array('xml'=> $this->_xmlFlow, 'product'=>$product));
                     */

                    unset($collection);
                }

                $this->closeFlow();
                $this->_writeXml(true);
                $this->reset();
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::throwException($e);
        }
        return $this;
    }

    public function addItemXmlCallback($args) {
        $product = $args['product'];
        $product->setData($args['row']);
        $xmlFlow = $args['xml'];

        try {

            if (!$this->_validate($product)) {

                //Mage::log("noValid = ".$product,null,'test_callback.log');	$this->_lastCount--;
                continue;
            }


            //Mage::log('ID P = ' . $product->getId(), null, 'test_callback.log');
            $xmlFlow .= $this->_getProductNode($product);
            //Mage::log($xmlFlow, null, 'test_callback.log');
        } catch (Exception $e) {
            $this->_errors[] = 'Product ID = ' . $product->getId() . "<br />" . $e->getMessage();
        }
    }

    /**
     * Return Shoppingflu Helper
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    protected function getHelper() {
        return Mage::helper('profileolabs_shoppingflux');
    }

    public function setStoreId($storeId) {
        $this->_storeId = $storeId;
        return $this;
    }

    public function getStoreId() {
        return $this->_storeId;
        return $this;
    }

    public function getTotalCount() {
        if (is_null($this->_totalCount))
            $this->getProductCollection();

        return $this->_totalCount;
    }

    protected function _validate($product) {
        /* if($product->getType() != "bundle" && $product->getPrice() == 0 && $product->getMinimalPrice() == 0)
          return false; */

        if (!$this->getConfig()->isExportSoldout() && !$product->isSaleable()) {
            //$this->setException("Le produit {$product->getSku()} est hors stock. Il n'a pas été importé.",Varien_Convert_Exception::NOTICE);
            $this->_errors[] = Mage::helper('profileolabs_shoppingflux')->__("Product %s is out of stock. Product has not been imported.", $product->getSku());
            return false;
        }

        return true;
    }

    public function cleanUrl($url) {
        $url = str_replace("index.php/", "", $url);

        return $url;
    }

}