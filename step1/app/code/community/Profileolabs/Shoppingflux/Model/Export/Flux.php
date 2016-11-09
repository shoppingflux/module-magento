<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Export_Flux extends Mage_Core_Model_Abstract {

    protected $_attributesFromConfig = array();
    protected $_attributesConfigurable = array();
    protected $_storeCategories = array();

    protected function _construct() {
        $this->_init('profileolabs_shoppingflux/export_flux');
    }

    public function getEntry($productSku, $storeId) {
        $collection = $this->getCollection();
        $collection->addFieldToFilter('sku', $productSku);
        $collection->addFieldToFilter('store_id', $storeId);
        if ($collection->count() > 0) {
            return $collection->getFirstItem();
        }
        $model = Mage::getModel('profileolabs_shoppingflux/export_flux')
                ->setStoreId($storeId)
                ->setSku($productSku)
                ->setProductId(Mage::getModel('catalog/product')->getIdBySku($productSku))
                ->setUpdateNeeded(0);
        return $model;
    }

    protected function _getProductBySku($productSku, $storeId) {
        $pId = Mage::getModel('catalog/product')->getIdBySku($productSku);
        if (!$pId) {
            return false;
        }
        return $this->_getProduct($pId, $storeId);
    }

    protected function _getProduct($product, $storeId) {
        if (is_numeric($product)) {
            $productId = $product;
        } else if (is_object($product)) {
            $productId = $product->getId();
        } else {
            return false;
        }

        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $read->select()
                ->distinct()
                ->from(Mage::getConfig()->getTablePrefix() . 'catalog_product_website', array('website_id'))
                ->where('product_id = ?', $productId);
        $result = $read->fetchAll($select);
        $websiteIds = array();
        foreach ($result as $row) {
            $websiteIds[] = $row['website_id'];
        }

        if (!in_array(Mage::app()->getStore($storeId)->getWebsiteId(), $websiteIds)) {
            return false;
        }

        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        if (!$product->getId()) {
            return false;
        }

        return $product;
    }

    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    public function addMissingProduct($args) {
        $storeId = $args['store_id'];
        $this->updateProductInFlux($args['row']['sku'], $storeId);
    }

    public function checkForDeletedProducts() {
        $collection = Mage::getModel('profileolabs_shoppingflux/export_flux')->getCollection();
        $collection->getSelect()->joinLeft(
                    array('p' => $collection->getTable('catalog/product')),
                    'p.entity_id = main_table.product_id',
                    array()
                );
        $existingStoreIds = array();
        foreach(Mage::app()->getStores() as $store) {
            $existingStoreIds[] = $store->getId();
        }
        $collection->getSelect()->where("p.entity_id IS NULL or main_table.store_id NOT IN (".implode(',',$existingStoreIds).")");
        //$collection->walk('delete');
        Mage::getSingleton('core/resource_iterator')
                        ->walk($collection->getSelect(), array(array($this, 'removeDeletedProduct')));
    }
    
    public function removeDeletedProduct($args) {
        $fluxItem = Mage::getModel('profileolabs_shoppingflux/export_flux')->load($args['row']['id']);
        $fluxItem->delete();
    }
    
    
    public function checkForMissingProducts($store_id = false, $maxImport = 1000) {
        ini_set('display_errors', 1);
        error_reporting(-1);
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            if (!$this->getConfig()->isExportEnabled($storeId)) {
                continue;
            }
            if (!$store_id || $storeId == $store_id) {
                $productCollection = Mage::getModel('catalog/product')->getCollection()->addStoreFilter($storeId)->setStoreId($storeId);
                $productCollection->addAttributeToSelect('sku', 'left');
                $currentVersion = Mage::getVersion();
                $tableName = Mage::getSingleton('core/resource')->getTableName('profileolabs_shoppingflux/export_flux');
                $productCollection->getSelect()->joinLeft(
                        array('sf'=>$tableName), 
                        "entity_id=sf.product_id and store_id = '" . $storeId . "'", 
                        array('skusf' => 'sku')
                        );
                //not compatible with mage 1.3
                //$productCollection->joinTable(array('sf'=>'profileolabs_shoppingflux/export_flux'), "sku=sku", array('skusf'=>'sku'), "{{table}}.store_id = '".$storeId."'", 'left');
                $productCollection->setPage(1, $maxImport);
                $productCollection->getSelect()->where('sf.product_id IS NULL');
                //$productCollection->load();
                //echo $productCollection->getSelect();
                Mage::getSingleton('core/resource_iterator')
                        ->walk($productCollection->getSelect(), array(array($this, 'addMissingProduct')), array('store_id' => $storeId));
            }
        }
        
    }

    public function updateFlux($store_id = false, $maxImportLimit = 1000, $shouldExportOnly = false) {
        //if(rand(0,100) == 50) {
            //no need to execute this every time. todo : move to cron task
        //    $this->checkForDeletedProducts();
        //}
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $isCurrentStore = (Mage::app()->getStore()->getId() == $storeId);
            if (!$store_id || $store_id == $storeId) {
                if (!$isCurrentStore) {
                    $appEmulation = Mage::getSingleton('core/app_emulation');
                    if ($appEmulation) { // not available in 1.4
                        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    }
                }
                try {
                    $collection = Mage::getModel('profileolabs_shoppingflux/export_flux')->getCollection();
                    $collection->addFieldToFilter('update_needed', 1);
                    $collection->addFieldToFilter('store_id', $storeId);
                    $collection->getSelect()->order('rand()');//if concurrent calls, this will avoid updating the same products..
                    if ($shouldExportOnly) {
                        $collection->addFieldToFilter('should_export', 1);
                    }
                    foreach ($collection as $item) {
                        $this->updateProductInFlux($item->getSku(), $storeId);
                    }
                    $this->checkForMissingProducts($storeId, $maxImportLimit);
                    if (!$isCurrentStore && $appEmulation) {
                        $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                    }
                } catch (Exception $e) {
                    if (!$isCurrentStore && $appEmulation) {
                        $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                    }
                }
            }
        }
    }

    public function productNeedUpdateForStore($productId, $storeId, $ignoreRelations = false) {
        $product = $this->_getProduct($productId, $storeId);
        if ($product && $product->getId()) {
            $fluxEntry = Mage::getModel('profileolabs_shoppingflux/export_flux')->getEntry($product->getSku(), $storeId);
            if ($fluxEntry->getUpdateNeeded() != 1) {
                $fluxEntry->setUpdateNeeded(1);
                $fluxEntry->save();
            }
            if(!$ignoreRelations) {
                // update also parents
                $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
                foreach ($parentIds as $parentId) {
                    $this->productNeedUpdateForStore($parentId, $storeId, true);
                }
                // ... and child
                if($product->getTypeId() == 'configurable') {
                    $childProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null,$product);
                     foreach($childProducts as $childProduct) {
                        if($childProduct->getTypeId() == 'simple') {
                            $this->productNeedUpdateForStore($childProduct->getId(), $storeId, true);
                        }
                    }
                }
            }
        } 
    }

    public function productNeedUpdate($productId) {
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $this->productNeedUpdateForStore($productId, $storeId);
        }
        return;
    }

    protected function _shouldUpdate($product, $storeId) {
        if (!$this->getConfig()->isExportEnabled($storeId)) {
            return false;
        }

        if ($product->getStatus() == 2)
            return false;

        if ($product->getTypeId() == 'grouped' || $product->getTypeId() == 'bundle' || $product->getTypeId() == 'virtual') {
            return false;
        }

        if (!$this->getConfig()->isExportNotSalable() && !$product->isSalable()) {
            return false;
        }

        if ($product->getTypeId() == 'simple') {
            $parentIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());

            //FIX Added on 2014-08-14 to solve a case : unexistant parent found, so product is never exported...
            foreach ($parentIds as $k => $parentId) {
                if (!Mage::getModel('catalog/product')->getCollection()->addStoreFilter($storeId)->addFieldToFilter('entity_id', $parentId)->count()) {
                    unset($parentIds[$k]);
                }
            }
            //END FIX

            if (!empty($parentIds))
                return false;
        }



        $store = Mage::app()->getStore($storeId);
        if (!in_array($store->getWebsiteId(), $product->getWebsiteIds())) {
            return false;
        }

        return true;
    }

    protected $_attributes = array();

    protected function _getAttribute($attributeCode, $storeId = null) {
        if (!isset($this->_attributes[$attributeCode])) {
            $this->_attributes[$attributeCode] = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeCode);
            if ($storeId) {
                $this->_attributes[$attributeCode]->setStoreId($storeId);
            }
        }
        return $this->_attributes[$attributeCode];
    }

    protected function _getAttributeDataForProduct($nameNode, $attributeCode, $product, $storeId = null) {
        $_helper = Mage::helper('catalog/output');
        if(!$attributeCode) {
            return '';
        }

        $data = $product->getData($attributeCode);

        $attribute = $this->_getAttribute($attributeCode, $storeId);
        if ($attribute) {
            if ($attribute->getFrontendInput() == 'date') {
                return $data;
            }

            //$data = $attribute->getFrontend()->getValue($product);
            //$data = $_helper->productAttribute($product, $data, $attributeCode);
            if ($attribute->usesSource()) {
                $data = $attribute->getSource()->getOptionText($data);
                if (is_array($data)) {
                    $data = implode(', ', $data);
                }
                //$data = $product->getAttributeText($attributeCode);
            }


            if ($attribute->getFrontendInput() == 'weee') {
                $weeeAttributes = Mage::getSingleton('weee/tax')->getProductWeeeAttributes($product);
                if(isset($data[0]['value'])) {//in case weeeAttributes is empty
                        $data = $data[0]['value'];
                }

                foreach ($weeeAttributes as $wa) {
                    if ($wa->getCode() == $attributeCode) {
                        $data = round($wa->getAmount(), 2);
                        break;
                    }
                }
            }
        }

        //TODO remove this
        if ($data == "No" || $data == "Non")
            $data = "";

        //Exceptions data
        if ($nameNode == 'shipping_delay' && empty($data))
            $data = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay', $storeId);

        if ($nameNode == 'quantity')
            $data = round($data);

        if(is_array($data)) {
            $data = implode(',', $data);
        }
        return trim($data);
    }

    protected $_memoryLimit = null;
    protected $_maxExecutionTime = null;

    protected function _checkMemory() {
        $request = Mage::app()->getRequest();
        if ($request->getControllerName() == 'export_flux' && $request->getActionName() == 'index') {
            if (is_null($this->_memoryLimit)) {
                $memoryLimit = ini_get('memory_limit');
                if (preg_match('%M$%', $memoryLimit)) {
                    $memoryLimit = intval($memoryLimit) * 1024 * 1024;
                } else if (preg_match('%G$%', $memoryLimit)) {
                    $memoryLimit = intval($memoryLimit) * 1024 * 1024 * 1024;
                } else {
                    $memoryLimit = false;
                }
                $this->_memoryLimit = $memoryLimit;
                if(intval(Mage::getStoreConfig('shoppingflux_export/general/execution_time_limit')) > 1) {
                    $this->_maxExecutionTime = intval(Mage::getStoreConfig('shoppingflux_export/general/execution_time_limit'));
                } else {
                    $this->_maxExecutionTime = (ini_get('max_execution_time')>1)?ini_get('max_execution_time'):600;
                }
                $this->_maxExecutionTime = min($this->_maxExecutionTime, 9 * 60); //There is a 10min timeout on SF side. We took 9 for margin.
                
            }
            $isTimeToDie = (microtime(true) - Mage::registry('export_feed_start_at') > $this->_maxExecutionTime);
            if ($this->_memoryLimit > 0 || $isTimeToDie) {
                $currentMemoryUsage = memory_get_usage(true);
                if ($isTimeToDie || $this->_memoryLimit - 15 * 1024 * 1024 <= $currentMemoryUsage) {
                    header('Content-type: text/html; charset=UTF-8');
                    header('Refresh: 0;'); 
                    $reasons = array();
                    if($isTimeToDie) {
                    	$reasons[] = 'Is Time to die : Execution time : '.(round(microtime(true) - Mage::registry('export_feed_start_at'), 2)).' - Max execution time : ' . $this->_maxExecutionTime;
                    }
                    if($this->_memoryLimit-10*1024*1024 <= $currentMemoryUsage) {
                    	$reasons[] = 'Memory limit : Used '.$currentMemoryUsage.' of '.$this->_memoryLimit;
                    }
                    
                    die('<html><head><meta http-equiv="refresh" content="0"/></head><body><!--Reason : '.implode(',', $reasons).'--></body></html>');
                }
            }
        }
    }

    public function updateProductInFluxForAllStores($productSku) {
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $isCurrentStore = (Mage::app()->getStore()->getId() == $storeId);
            try {
                if (!$isCurrentStore) {
                    $appEmulation = Mage::getSingleton('core/app_emulation');
                    if ($appEmulation) {
                        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    }
                }
                $this->updateProductInFlux($productSku, $storeId);
                if (!$isCurrentStore && $appEmulation) {
                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                }
            } catch (Exception $e) {
                if (!$isCurrentStore && $appEmulation) {
                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
                }
            }
        }
    }

    public function updateProductInFlux($productSku, $storeId) {

        //$this->_checkMemory();

        $product = $this->_getProductBySku($productSku, $storeId);

        if (!$product || !$product->getSku()) {
            $fluxEntry = Mage::getModel('profileolabs_shoppingflux/export_flux')->getEntry($productSku, $storeId);
            $fluxEntry->setShouldExport(0);
            $fluxEntry->setUpdateNeeded(0);
            $fluxEntry->setUpdatedAt(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())));
            $fluxEntry->save();
            return;
        }


        if (!$this->_shouldUpdate($product, $storeId)) {
            $fluxEntry = Mage::getModel('profileolabs_shoppingflux/export_flux')->getEntry($product->getSku(), $storeId);
            $fluxEntry->setShouldExport(0);
            $fluxEntry->setStockValue($product->getStockItem()->getQty());
            $fluxEntry->setUpdateNeeded(0);
            $fluxEntry->setUpdatedAt(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())));
            $fluxEntry->save();
            return;
        }
        //Varien_Profiler::start("SF::Flux::updateProductInFlux");

        $xmlObj = Mage::getModel('profileolabs_shoppingflux/export_xml');
        $xml = '';

        if ($this->getConfig()->useManageStock()) {
            $_configManageStock = (int) Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK, $storeId);
            $_manageStock = ($product->getStockItem()->getUseConfigManageStock() ? $_configManageStock : $product->getStockItem()->getManageStock());
        } else {
            $_manageStock = true;
        }
        
        $data = array(
            'id' => $product->getId(),
            'last-feed-update' => date('Y-m-d H:i:s'),
            'mage-sku' => $product->getSku(),
            'product-url' => $this->cleanUrl($product->getProductUrl(false)),
            'is-in-stock' => $_manageStock ? $product->getStockItem()->getIsInStock() : 1,
            'salable' => intval($product->isSalable()),
            'qty' => $product->isSalable() ? ($_manageStock ? round($product->getStockItem()->getQty()) : 100) : 0,
            'qty-increments' => $this->getConfig()->getTransformQtyIncrements($product) ? 1 : $this->getConfig()->getQtyIncrements($product),
            'tax-rate' => $product->getTaxPercent(),
        );

        if ($this->getConfig()->getTransformQtyIncrements($product)) {
            $qtyIncrements = $this->getConfig()->getQtyIncrements($product);
            $data['qty'] = $data['qty'] / $qtyIncrements;
        }

       
        foreach ($this->getConfig()->getMappingAttributes($storeId) as $nameNode => $code) {
            $data[$nameNode] = $this->_getAttributeDataForProduct($nameNode, $code, $product, $storeId); //trim($xmlObj->extractData($nameNode, $code, $product));
            if ($this->getConfig()->getTransformQtyIncrements($product)) {
                $qtyIncrements = $this->getConfig()->getQtyIncrements($product);
                if ($nameNode == 'name') {
                    $data[$nameNode] = $data[$nameNode] . Mage::helper('profileolabs_shoppingflux')->__(' - Set of %d', $qtyIncrements);
                } else if ($nameNode == 'sku') {
                    $data[$nameNode] = '_SFQI_' . $qtyIncrements . '_' . $data[$nameNode];
                } else if ($nameNode == 'weight') {
                    $data[$nameNode] = $qtyIncrements * $data[$nameNode];
                }
            }
        }

        //Varien_Profiler::start("SF::Flux::getPrices");
        $data = $this->getPrices($data, $product, $storeId);
        //Varien_Profiler::stop("SF::Flux::getPrices");
        //Varien_Profiler::start("SF::Flux::getImages");
        $data = $this->getImages($data, $product, $storeId);
        //Varien_Profiler::stop("SF::Flux::getImages");
        //Varien_Profiler::start("SF::Flux::getCategories");
        $data = $this->getCategories($data, $product, $storeId);
        //Varien_Profiler::stop("SF::Flux::getCategories");
        //Varien_Profiler::start("SF::Flux::getShippingInfo");
        $data = $this->getShippingInfo($data, $product, $storeId);
        //Varien_Profiler::stop("SF::Flux::getShippingInfo");
        if ($this->getConfig()->getManageConfigurables()) {
            //Varien_Profiler::start("SF::Flux::getConfigurableAttributes");
            $data = $this->getConfigurableAttributes($data, $product, $storeId);
            //Varien_Profiler::stop("SF::Flux::getConfigurableAttributes");
        }
        //Varien_Profiler::start("SF::Flux::getAdditionalAttributes");
        foreach ($this->getConfig()->getAdditionalAttributes($storeId) as $attributeCode) {
            $data[$attributeCode] = $this->_getAttributeDataForProduct($attributeCode, $attributeCode, $product, $storeId);
        }

        //Varien_Profiler::stop("SF::Flux::getAdditionalAttributes");
        //Varien_Profiler::start("SF::Flux::addEntry1");
        if (!isset($data['shipping_delay']) && empty($data['shipping_delay']))
            $data['shipping_delay'] = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');


        if ($this->getConfig()->getEnableEvents()) {
            $dataObj = new Varien_Object(array('entry' => $data, 'store_id' => $storeId, 'product' => $product));
            Mage::dispatchEvent('shoppingflux_before_update_entry', array('data_obj' => $dataObj));

            $entry = $dataObj->getEntry();
        } else {
            $entry = $data;
        }


        //Varien_Profiler::stop("SF::Flux::addEntry1");
        //Varien_Profiler::start("SF::Flux::addEntry2");
        $xml .= $xmlObj->_addEntry($entry);
        //Varien_Profiler::stop("SF::Flux::addEntry2");
        //Varien_Profiler::start("SF::Flux::saveProductFlux");
        $fluxEntry = Mage::getModel('profileolabs_shoppingflux/export_flux')->getEntry($product->getSku(), $storeId);
        $fluxEntry->setUpdatedAt(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())));
        $fluxEntry->setXml($xml);
        $fluxEntry->setUpdateNeeded(0);
        $fluxEntry->setProductId($product->getId());
        $fluxEntry->setStockValue($product->getStockItem()->getQty());
        $fluxEntry->setPriceValue($product->getFinalPrice());
        $fluxEntry->setIsInStock($data['is-in-stock']);
        $fluxEntry->setSalable($product->isSalable());
        $fluxEntry->setIsInFlux(intval($product->getData('shoppingflux_product')));
        $fluxEntry->setType($product->getTypeId());
        $fluxEntry->setVisibility($product->getVisibility());
        $fluxEntry->setShouldExport(1);
        $fluxEntry->save();
        // Faster
        /*
          $tableName = Mage::getSingleton('core/resource')->getTableName('profileolabs_shoppingflux/export_flux');
          $write = Mage::getSingleton('core/resource')->getConnection('core_write');
          $request = "INSERT INTO " . $tableName . " (sku, store_id, updated_at, xml, update_needed, is_in_stock, is_in_flux, type, visibility, should_export) VALUES ";
          $request .= "(" . $write->quote($product->getSku()) . ", " . $write->quote($storeId) . ", " . $write->quote(date('Y-m-d H:i:s')) . ", " . $write->quote($xml) . ", '0', " . $write->quote($data['is-in-stock']) . ", " . $write->quote($product->getData('shoppingflux_product')) . ", '" . $product->getTypeId() . "', '" . $product->getVisibility() . "', '1')";
          $request .= " on duplicate key update updated_at = VALUES(updated_at), xml = VALUES(xml), update_needed = VALUES(update_needed), is_in_stock = VALUES(is_in_stock), is_in_flux = VALUES(is_in_flux), type = VALUES(type), visibility = VALUES(visibility),  should_export = VALUES(should_export) ";
          $write->query($request);
         */
        //Varien_Profiler::stop("SF::Flux::saveProductFlux");
        //Varien_Profiler::stop("SF::Flux::updateProductInFlux");
    }

    /**
     * Get prices of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getPrices($data, $product, $storeId) {

        $priceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/attributes_mapping/price', $storeId);
        $specialPriceAttributeCode = $this->getConfig()->getConfigData('shoppingflux_export/attributes_mapping/special_price', $storeId);
        if (!$product->getData($priceAttributeCode)) {
            $priceAttributeCode = 'price';
            $specialPriceAttributeCode = 'special_price';
        }

        $discountAmount = 0;
        $finalPrice = $product->getData($priceAttributeCode);
        $priceBeforeDiscount = $product->getData($priceAttributeCode);
        if ($product->getData($specialPriceAttributeCode) > 0 && $product->getData($specialPriceAttributeCode) < $finalPrice) {
            $finalPrice = $product->getData($specialPriceAttributeCode);
            $discountAmount = $priceBeforeDiscount - $finalPrice;
        }
        $discountFromDate = $product->getSpecialFromDate();
        $discountToDate = $product->getSpecialToDate();


        $product->setCalculatedFinalPrice($finalPrice);
        $product->setData('final_price', $finalPrice);
        $currentVersion = Mage::getVersion();
        if (version_compare($currentVersion, '1.5.0') < 0) {
            if ($this->getConfig()->getManageCatalogRules()) {
                Mage::dispatchEvent('catalog_product_get_final_price', array('product'=>$product));
                $finalPrice = $product->getFinalPrice();
                $discountAmount = $priceBeforeDiscount - $finalPrice;
                $discountFromDate = '';
                $discountToDate = '';
            }
        } else {
            if ($this->getConfig()->getManageCatalogRules()) {
                $catalogPriceRulePrice = Mage::getModel('catalogrule/rule')->calcProductPriceRule($product, $product->getPrice());
                if ($catalogPriceRulePrice > 0 && $catalogPriceRulePrice < $finalPrice) {
                    $finalPrice = $catalogPriceRulePrice;
                    $discountAmount = $priceBeforeDiscount - $catalogPriceRulePrice;
                    $discountFromDate = '';
                    $discountToDate = '';
                }
            }
        }

        if ($this->getConfig()->getTransformQtyIncrements($product)) {
            $qtyIncrements = $this->getConfig()->getQtyIncrements($product);
            $finalPrice *= $qtyIncrements;
            $priceBeforeDiscount *= $qtyIncrements;
        }

        $data["price-ttc"] = Mage::helper('tax')->getPrice($product, $finalPrice, true); //$finalPrice;
        $data["price-before-discount"] = Mage::helper('tax')->getPrice($product, $priceBeforeDiscount, true); //$priceBeforeDiscount;
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
        unset($data['price']);
        unset($data['special_price']);
        return $data;
    }

    /**
     * Get categories of product
     * @param Mage_Catalog_Model_Product $product
     * @return string $nodes
     */
    protected function getCategories($data, $product, $storeId) {
        if ($product->getData('shoppingflux_default_category') && $product->getData('shoppingflux_default_category') > 0) {
            $data =  $this->getCategoriesViaShoppingfluxCategory($data, $product);
        } else {
            $data = $this->getCategoriesViaProductCategories($data, $product);
        }
        if(!$data['category-breadcrumb']) {
            $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            $parentIds = array_merge($parentIds, Mage::getModel('catalog/product_type_grouped')->getParentIdsByChild($product->getId()));
            $parentIds = array_merge($parentIds, Mage::getModel('bundle/product_type')->getParentIdsByChild($product->getId()));
            $parentIds = array_unique($parentIds);
            foreach ($parentIds as $parentId) {
                if(!$data['category-breadcrumb']) {
                    $parentProduct = $this->_getProduct($parentId, $storeId);
                    if($parentProduct && is_object($parentProduct) && $parentProduct->getId()) {
                        $data = $this->getCategories($data, $parentProduct, $storeId);
                    }
                }
                
            }
        }
        return $data;
    }

    protected function getCategoriesViaShoppingfluxCategory($data, $product) {

        //Varien_Profiler::start("SF::Flux::getCategoriesViaShoppingfluxCategory-1");
        $categoryId = $product->getData('shoppingflux_default_category');
        if (!$categoryId) {
            $categoryId = $product->getData('main_category');//Compatibility with Webcooking_MainCategory
            if (!$categoryId) {
                return $this->getCategoriesViaProductCategories($data, $product);
            }
        }
        $category = Mage::helper('profileolabs_shoppingflux')->getCategoriesWithParents(false, $product->getStoreId());
        //Varien_Profiler::stop("SF::Flux::getCategoriesViaShoppingfluxCategory-1");
        if (!isset($category['name'][$categoryId])) {
            return $this->getCategoriesViaProductCategories($data, $product);
        }

        //Varien_Profiler::start("SF::Flux::getCategoriesViaShoppingfluxCategory");

        $categoryNames = explode(' > ', $category['name'][$categoryId]);
        $categoryMetaTitles = explode(' > ', $category['meta_title'][$categoryId]);
        $categoryMetaDescriptions = explode(' > ', $category['meta_description'][$categoryId]);
        $categoryMetaKeywords = explode(' > ', $category['meta_keywords'][$categoryId]);
        $categoryUrls = explode(' > ', $category['url'][$categoryId]);


        //we drop root category, which is useless here
        array_shift($categoryNames);
        array_shift($categoryMetaTitles);
        array_shift($categoryMetaDescriptions);
        array_shift($categoryMetaKeywords);
        array_shift($categoryUrls);


        $data['category-breadcrumb'] = trim(implode(' > ', $categoryNames));

        $data["category-main"] = isset($categoryNames[0]) ? trim($categoryNames[0]) : '';
        $data["category-url-main"] = isset($categoryUrls[0]) ? $categoryUrls[0] : '';
        $data["category-metatitle-main"] = isset($categoryMetaTitles[0]) ? $categoryMetaTitles[0] : '';
        $data["category-metadescription-main"] = isset($categoryMetaDescriptions[0]) ? $categoryMetaDescriptions[0] : '';
        $data["category-metakeywords-main"] = isset($categoryMetaKeywords[0]) ? $categoryMetaKeywords[0] : '';


        for ($i = 1; $i <= 5; $i++) {
            if (isset($categoryNames[$i])) {
                $data["category-sub-" . ($i)] = trim($categoryNames[$i]);
            } else {
                $data["category-sub-" . ($i)] = '';
            }
            if (isset($categoryUrls[$i])) {
                $data["category-url-sub-" . ($i)] = $categoryUrls[$i];
            } else {
                $data["category-url-sub-" . ($i)] = '';
            }
            if (isset($categoryMetaTitles[$i])) {
                $data["category-metatitle-sub-" . ($i)] = $categoryMetaTitles[$i];
            } else {
                $data["category-metatitle-sub-" . ($i)] = '';
            }
            if (isset($categoryMetaDescriptions[$i])) {
                $data["category-metadescription-sub-" . ($i)] = $categoryMetaDescriptions[$i];
            } else {
                $data["category-metadescription-sub-" . ($i)] = '';
            }
            if (isset($categoryMetaKeywords[$i])) {
                $data["category-metakeywords-sub-" . ($i)] = $categoryMetaKeywords[$i];
            } else {
                $data["category-metakeywords-sub-" . ($i)] = '';
            }
        }

        //Varien_Profiler::stop("SF::Flux::getCategoriesViaShoppingfluxCategory");
        return $data;
    }

    protected function getCategoriesViaProductCategories($data, $product) {

        //Varien_Profiler::start("SF::Flux::getCategoriesViaProductCategories");
        $cnt = 0;

        if (!$this->getConfig()->getUseOnlySFCategory()) {
            $rootCategoryId = Mage::app()->getStore($product->getStoreId())->getRootCategoryId();

            $categoryWithParents = Mage::helper('profileolabs_shoppingflux')->getCategoriesWithParents(false, $product->getStoreId(), false, false);
            $maxLevelCategory = $this->getConfig()->getMaxCategoryLevel() > 0 ? $this->getConfig()->getMaxCategoryLevel() : 5;


            //Selection of the deepest category
            $productCategoryIds = $product->getCategoryIds();
            $choosenProductCategoryId = false;
            $choosenCategoryLevel = 0;
            foreach ($productCategoryIds as $productCategoryId) {
                if (isset($categoryWithParents['name'][$productCategoryId])) {
                    $categoryNames = explode(' > ', $categoryWithParents['name'][$productCategoryId]);
                    if (count($categoryNames) > $choosenCategoryLevel) {
                        $choosenProductCategoryId = $productCategoryId;
                    }
                }
            }

            //Adding the deepest category to breadcrumb
            if ($choosenProductCategoryId) {
                $categoryNames = explode(' > ', $categoryWithParents['name'][$choosenProductCategoryId]);
                $categoryMetaTitles = explode(' > ', $categoryWithParents['meta_title'][$choosenProductCategoryId]);
                $categoryMetaDescriptions = explode(' > ', $categoryWithParents['meta_description'][$choosenProductCategoryId]);
                $categoryMetaKeywords = explode(' > ', $categoryWithParents['meta_keywords'][$choosenProductCategoryId]);
                $categoryUrls = explode(' > ', $categoryWithParents['url'][$choosenProductCategoryId]);
                //we drop root category, which is useless here
                array_shift($categoryNames);
                array_shift($categoryMetaTitles);
                array_shift($categoryMetaDescriptions);
                array_shift($categoryMetaKeywords);
                array_shift($categoryUrls);
                $categoryNames = array_slice($categoryNames, 0, $maxLevelCategory, true);
                $categoryUrls = array_slice($categoryUrls, 0, $maxLevelCategory, true);
                $data['category-breadcrumb'] = trim(implode(' > ', $categoryNames));

                $data["category-main"] = trim($categoryNames[0]);
                $data["category-url-main"] = $categoryUrls[0];
                $data["category-metatitle-main"] = $categoryMetaTitles[0];
                $data["category-metadescription-main"] = $categoryMetaDescriptions[0];
                $data["category-metakeywords-main"] = $categoryMetaKeywords[0];


                for ($i = 1; $i <= 5; $i++) {
                    if (isset($categoryNames[$i])) {
                        $data["category-sub-" . ($i)] = trim($categoryNames[$i]);
                    } else {
                        $data["category-sub-" . ($i)] = '';
                    }
                    if (isset($categoryUrls[$i])) {
                        $data["category-url-sub-" . ($i)] = $categoryUrls[$i];
                    } else {
                        $data["category-url-sub-" . ($i)] = '';
                    }
                    if (isset($categoryMetaTitles[$i])) {
                        $data["category-metatitle-sub-" . ($i)] = $categoryMetaTitles[$i];
                    } else {
                        $data["category-metatitle-sub-" . ($i)] = '';
                    }
                    if (isset($categoryMetaDescriptions[$i])) {
                        $data["category-metadescription-sub-" . ($i)] = $categoryMetaDescriptions[$i];
                    } else {
                        $data["category-metadescription-sub-" . ($i)] = '';
                    }
                    if (isset($categoryMetaKeywords[$i])) {
                        $data["category-metakeywords-sub-" . ($i)] = $categoryMetaKeywords[$i];
                    } else {
                        $data["category-metakeywords-sub-" . ($i)] = '';
                    }
                }
            }
        }

        if (!isset($data["category-main"])) {
            $data["category-breadcrumb"] = "";
            $data["category-main"] = "";
            $data["category-url-main"] = "";
            $cnt++;
        }


        for ($i = 1; $i <= 5; $i++) {
            if (!isset($data["category-sub-" . ($i)])) {
                $data["category-sub-" . ($i)] = "";
                $data["category-url-sub-" . ($i)] = "";
            }
        }


        //Varien_Profiler::stop("SF::Flux::getCategoriesViaProductCategories");
        return $data;
    }

    public function cleanUrl($url) {
        $url = str_replace("index.php/", "", $url);
        $url = preg_replace('%(.*)\?.*$%i', '$1', $url);
        return $url;
    }

    public function getImages($data, $product, $storeId, $checkParentIfNone = true) {



        $mediaUrl = Mage::getBaseUrl('media') . 'catalog/product';

        $i = 1;

        if ($product->getImage() != "" && $product->getImage() != 'no_selection') {
            $data["image-url-" . $i] = $mediaUrl . $product->getImage();
            $data["image-label-" . $i] = $product->getImageLabel();
            $i++;
        }


        if ($this->getConfig()->getManageMediaGallery()) {
            //LOAD media gallery for this product			
            $mediaGallery = $product->getResource()->getAttribute('media_gallery');
            $mediaGallery->getBackend()->afterLoad($product);


            foreach ($product->getMediaGallery('images') as $image) {
                if ($mediaUrl . $product->getImage() == $product->getMediaConfig()->getMediaUrl($image['file']))
                    continue;
                if($image['disabled'])
                    continue;

                $data["image-url-" . $i] = $product->getMediaConfig()->getMediaUrl($image['file']);
                $data["image-label-" . $i] = $image['label'];
                $i++;
                if (($i - 6) == 0)
                    break;
            }
        }


        //Complet with empty nodes
        for ($j = $i; $j < 6; $j++) {
            $data["image-url-" . $i] = "";
            $data["image-label-" . $i] = "";
            $i++;
        }
        
        if(!$data['image-url-1'] && $checkParentIfNone) {
            $groupedParentsIds = Mage::getResourceSingleton('catalog/product_link')
                   ->getParentIdsByChild($product->getId(), Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
            $parentId = current($groupedParentsIds);
            $parentProduct = $this->_getProduct($parentId, $storeId);
            if($parentProduct && $parentProduct->getId()) {
                return $this->getImages($data, $parentProduct, $storeId, false);
            }
        }
        
        
        return $data;
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
    protected function getAttributesFromConfig($checkIfExist = false, $withAdditional = true, $storeId = null) {

        if (!isset($this->_attributesFromConfig[$storeId])) {
            $this->_attributesFromConfig[$storeId] = array();
            $attributes = $this->getConfig()->getMappingAttributes($storeId);
            if ($withAdditional) {
                $additionalAttributes = $this->getConfig()->getAdditionalAttributes($storeId);
                foreach ($additionalAttributes as $attributeCode) {
                    $attributes[$attributeCode] = trim($attributeCode);
                }
            }

            if ($checkIfExist) {
                $product = Mage::getModel('catalog/product');
                foreach ($attributes as $key => $code) {
                    $attribute = $this->_getAttribute($code);
                    if ($attribute instanceof Mage_Catalog_Model_Resource_Eav_Attribute && $attribute->getId() && $attribute->getFrontendInput() != 'weee') {
                        $this->_attributesFromConfig[$storeId][$key] = $code;
                    }
                }
            } else {
                $this->_attributesFromConfig[$storeId] = $attributes;
            }
        }

        return $this->_attributesFromConfig[$storeId];
    }

    protected function getRequiredAttributes() {

        $requiredAttributes = array("sku" => "sku",
            "price" => "price",
            "image" => "image");

        return $requiredAttributes;
    }

    /**
     * 
     * @deprecated
     */
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

    protected function getShippingInfo($data, $product, $storeId) {

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
        if ($this->getConfig()->getConfigData('shoppingflux_export/general/try_use_real_shipping_price')) {
            $countryCode = $this->getConfig()->getConfigData('shoppingflux_export/general/shipping_price_based_on');
            $shippingPrice = Mage::helper('profileolabs_shoppingflux')->getShippingPrice($product, $carrier, $countryCode);
        }

        if (!$shippingPrice) {
            $shippingPrice = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_price');
        }

        $data["shipping-price"] = $shippingPrice;

        return $data;
    }

    protected function getConfigurableAttributes($data, $product, $storeId) {

        $xmlObj = Mage::getModel('profileolabs_shoppingflux/export_xml');
        $data["configurable_attributes"] = "";
        $data["childs_product"] = "";
        $images = array();

        $labels = array();
        if ($product->getTypeId() == "configurable") {

            $attributes = Mage::helper('profileolabs_shoppingflux')->getAttributesConfigurable($product);

            $attributesToOptions = array();

            foreach ($attributes as $attribute) {
                $attributesToOptions[$attribute['attribute_code']] = array();
            }

            $usedProducts = $product->getTypeInstance(true)
                    ->getUsedProductCollection($product);


            $configurableAttributes = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);


            $usedProductsArray = array();
            $salable = false;
            foreach (Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($usedProducts->getSelect()) as $usedProduct) { //Prevent Old magento bug
                $usedProduct = $this->_getProduct($usedProduct['entity_id'], $storeId);

                if (!$usedProduct) {
                    continue;
                }
                if ($usedProduct->getStatus() == 2) {
                    continue;
                }

                $salable = $salable || $usedProduct->isSalable();


                if (Mage::helper('profileolabs_shoppingflux')->isModuleInstalled('OrganicInternet_SimpleConfigurableProducts') || Mage::helper('profileolabs_shoppingflux')->isModuleInstalled('DerModPro_BCP')) {

                    $tmpData = $this->getPrices(array(), $usedProduct, $storeId);
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

                $attributesFromConfig = $this->getAttributesFromConfig(true, true, $storeId);

                $discountPercent = 0;
                if ($priceBeforeDiscount) {
                    $discountPercent = round((($priceBeforeDiscount - $price) * 100) / $priceBeforeDiscount);
                }

                $isInStock = 0;
                $qty = 0;
                if ($usedProduct->getStockItem()) {
                    if ($this->getConfig()->useManageStock()) {
                        $_configManageStock = (int) Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK, $storeId);
                        $_manageStock = $usedProduct->getStockItem()->getUseConfigManageStock() ? $_configManageStock : $usedProduct->getStockItem()->getManageStock();
                    } else {
                        $_manageStock = true;
                    }

                    $isInStock = $_manageStock ? $usedProduct->getStockItem()->getIsInStock() : 1;

                    if ($usedProduct->isSalable()) {
                        $qty = $_manageStock ? $usedProduct->getStockItem()->getQty() : 100;
                    } else {
                        $qty = 0;
                    }
                }

                $usedProductsArray[$usedProduct->getId()]['child']["sku"] = $usedProduct->getSku();
                $usedProductsArray[$usedProduct->getId()]['child']["id"] = $usedProduct->getId();
                $usedProductsArray[$usedProduct->getId()]['child']["child-url"] = $this->cleanUrl($usedProduct->getProductUrl(false));
                $usedProductsArray[$usedProduct->getId()]['child']["price-ttc"] = $price;
                $usedProductsArray[$usedProduct->getId()]['child']["price-before-discount"] = $priceBeforeDiscount;
                $usedProductsArray[$usedProduct->getId()]['child']["discount-amount"] = $discountAmount;
                $usedProductsArray[$usedProduct->getId()]['child']["discount-percent"] = $discountPercent;
                $usedProductsArray[$usedProduct->getId()]['child']["start-date-discount"] = $startDateDiscount;
                $usedProductsArray[$usedProduct->getId()]['child']["end-date-discount"] = $endDateDiscount;
                $usedProductsArray[$usedProduct->getId()]['child']['is-in-stock'] = $isInStock;
                $usedProductsArray[$usedProduct->getId()]['child']['qty'] = round($qty);
                $usedProductsArray[$usedProduct->getId()]['child']['tax-rate'] = $usedProduct->getTaxPercent();
                if (!$data['tax-rate'] && $usedProductsArray[$usedProduct->getId()]['child']['tax-rate']) {
                    $data['tax-rate'] = $usedProductsArray[$usedProduct->getId()]['child']['tax-rate'];
                }
                if ($qty > 0 && $qty > $data['qty']) {
                    $data['qty'] = round($qty);
                }
                $usedProductsArray[$usedProduct->getId()]['child']["ean"] = isset($attributesFromConfig['ean']) ? $usedProduct->getData($attributesFromConfig['ean']) : '';

                $images = $this->getImages($images, $usedProduct, $storeId, false);
                if (!$images['image-url-1']) {
                    $images = $this->getImages($images, $product, $storeId);
                }
                foreach ($images as $key => $value) {
                    $usedProductsArray[$usedProduct->getId()]['child'][$key] = trim($value);
                }

                foreach ($attributesFromConfig as $nameNode => $attributeCode) {
                    if($attributeCode) {
                        $usedProductsArray[$usedProduct->getId()]['child'][$nameNode] = $this->_getAttributeDataForProduct($nameNode, $attributeCode, $usedProduct, $storeId); //$xmlObj->extractData($nameNode, $attributeCode, $usedProduct);
                    }
                }


                $attributes = Mage::helper('profileolabs_shoppingflux')->getAttributesConfigurable($product);
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

                    if (!isset($attributesToOptions[$attributeCode]) || !in_array($value, $attributesToOptions[$attributeCode]))
                        $attributesToOptions[$attributeCode][] = $value;

                    $usedProductsArray[$usedProduct->getId()]['child'][$attributeCode] = trim($value);
                }


                if (!isset($usedProductsArray[$usedProduct->getId()]['child']['shipping_delay']) || !$usedProductsArray[$usedProduct->getId()]['child']['shipping_delay'])
                    $usedProductsArray[$usedProduct->getId()]['child']['shipping_delay'] = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');
                
                unset($usedProductsArray[$usedProduct->getId()]['child']['price']);
                unset($usedProductsArray[$usedProduct->getId()]['child']['special_price']);
            }




            $data['is-in-stock'] = (int) $salable;

            foreach ($attributesToOptions as $attributeCode => $value) {

                $data["configurable_attributes"][$attributeCode] = implode(",", $value);
            }
            $data["childs_product"] = $usedProductsArray;

            unset($usedProducts);
            unset($usedProductsArray);
        }
        return $data;
    }

}
