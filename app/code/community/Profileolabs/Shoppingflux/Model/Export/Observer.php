<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait, vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Export_Observer {

    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }
    
    public function catalogruleAfterApply($observer) {
        return; //Disabled because it invalidate all the feed at each product / inventory save...
        if(!$this->getConfig()->getManageCatalogRules()) {
            return;
        }
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->beginTransaction();
        try {
            $query = "update " . Mage::getSingleton('core/resource')->getTableName('profileolabs_shoppingflux/export_flux') . " set update_needed = 1 ";
            $write->query($query);
            $write->commit();
        } catch (Exception $e) {
            $write->rollback();
        }     
    }
    
    
    public static function checkStock($storeId = false) {
        if(!$storeId || !is_numeric($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }
        $productCollection = Mage::getModel('catalog/product')->getCollection();
        $productCollection->getSelect()->join(
                    array('sf_stock' => $productCollection->getTable('cataloginventory/stock_item')), 'e.entity_id = sf_stock.product_id', array('qty', 'actual_qty'=>'qty')
            );
        $productCollection->getSelect()->join(
                    array('flux' => $productCollection->getTable('profileolabs_shoppingflux/export_flux')), "e.entity_id = flux.product_id and flux.store_id = '".$storeId."'", array('stock_value', 'sku')
            );
        $productCollection->getSelect()->where('CAST(sf_stock.qty AS SIGNED) != flux.stock_value');
        if (Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK, $storeId)) {
            $productCollection->getSelect()->where('(sf_stock.use_config_manage_stock = 0 and sf_stock.manage_stock = 1) OR (sf_stock.use_config_manage_stock = 1)');
        } else {
            $productCollection->getSelect()->where('(sf_stock.use_config_manage_stock = 0 and sf_stock.manage_stock = 1)');
        }
        $productCollection->getSelect()->where('flux.update_needed = 0');
        $productCollection->getSelect()->group('e.entity_id');
        foreach($productCollection as $product) {
            Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($product);
        }
    }
    
    

    public function updateFlux() {
        if(Mage::getStoreConfigFlag('shoppingflux_export/general/enable_cron')) {
            foreach(Mage::app()->getStores() as $store) {
                $feedUrl = Mage::helper('profileolabs_shoppingflux')->getFeedUrl($store);
                if($feedUrl) {
                    file_get_contents($feedUrl);
                }
            }
        }
    }

    
    protected function generateFluxInFileForStore($storeId) {
        $filePath = Mage::getBaseDir('media') . DS . 'shoppingflux_'.$storeId.'.xml';
        $handle = fopen($filePath, 'a');
        ftruncate($handle, 0);

        //Mage::getModel('profileolabs_shoppingflux/export_flux')->updateFlux($storeId, 1000000);
        $collection = Mage::getModel('profileolabs_shoppingflux/export_flux')->getCollection();
        $collection->addFieldToFilter('should_export', 1);
        $collection->addFieldToFilter('store_id', $storeId);
        $sizeTotal = $collection->count();
        $collection->clear();

        if (!$this->getConfig()->isExportNotSalable($storeId)) {
            $collection->addFieldToFilter('salable', 1);
        }
        if (!$this->getConfig()->isExportSoldout($storeId)) {
            $collection->addFieldToFilter('is_in_stock', 1);
        }
        if ($this->getConfig()->isExportFilteredByAttribute($storeId)) {
            $collection->addFieldToFilter('is_in_flux', 1);
        }
        $visibilities = $this->getConfig()->getVisibilitiesToExport($storeId);
        $collection->getSelect()->where("find_in_set(visibility, '" . implode(',', $visibilities) . "')");



        $xmlObj = Mage::getModel('profileolabs_shoppingflux/export_xml');
        $startXml = $xmlObj->startXml(array('size-exportable' => $sizeTotal, 'size-xml' => $collection->count(), 'with-out-of-stock' => intval($this->getConfig()->isExportSoldout()), 'with-not-salable'=> intval($this->getConfig()->isExportNotSalable()), 'selected-only' => intval($this->getConfig()->isExportFilteredByAttribute()), 'visibilities' => implode(',', $visibilities)));
        fwrite($handle, $startXml);
        Mage::getSingleton('core/resource_iterator')
                ->walk($collection->getSelect(), array(array($this, 'saveProductXml')), array('handle'=>$handle));
        $endXml = $xmlObj->endXml();
        fwrite($handle, $endXml);
        fclose($handle);
    }
    
    public function saveProductXml($args) {
        fwrite($args['handle'], $args['row']['xml']);
    }
    
    public function generateFluxInFile() {
        //foreach(Mage::app()->getStores() as $store) {
        //    $this->generateFluxInFileForStore($store->getId());
        //}
        $this->generateFluxInFileForStore(Mage::app()->getDefaultStoreView()->getId());
    }


    /**
     * Add shoppingflux product tab in category edit page
     * @param $observer
     */
    public function addShoppingfluxProductsTab($observer) {
        $tabs = $observer->getTabs();
        $tabs->addTab('shoppingflux_products', array(
            'label' => Mage::helper('catalog')->__('Shoppingflux Category Products'),
            'content' => $tabs->getLayout()->createBlock(
                    'profileolabs_shoppingflux/export_adminhtml_catalog_category_tab_default', 'shoppingflux.product.grid'
            )->toHtml(),
        ));
    }

    public function catalogProductAttributeUpdateBefore($observer) {
        $productIds = $observer->getEvent()->getProductIds();
        foreach ($productIds as $productId) {
            Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($productId);
        }
    }

    public function catalogProductSaveCommitAfter($observer) {
        $product = $observer->getEvent()->getProduct();
        Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($product->getId());
    }

    /**
     * update default category for selected products
     */
    public function saveShoppingfluxCategoryProducts($observer) {
        $category = $observer->getEvent()->getCategory();
        $request = $observer->getEvent()->getRequest();
        $postedProducts = $request->getParam('shoppingflux_category_products');
        $products = array();
        $storeId = intval($request->getParam('store', 0));
        parse_str($postedProducts, $products);
        if (isset($products['on']))
            unset($products['on']);
        $products = array_keys($products);
        if (!empty($products)) {
            $currentVersion = Mage::getVersion();
            $product = Mage::getModel('catalog/product');
            foreach ($products as $productId) {

                $product->setData(array());
                $product->setStoreId($storeId)
                        ->load($productId)
                        ->setIsMassupdate(true)
                        ->setExcludeUrlRewrite(true);

                if (!$product->getId()) {
                    continue;
                }

                $product->addData(array('shoppingflux_default_category' => $category->getId()));
                $dataChanged = $product->dataHasChangedFor('shoppingflux_default_category');

                if ($dataChanged) {
                    if (version_compare($currentVersion, '1.4.0') < 0) { 
                        $product->save();
                    } else {
                        //Note: we could use this directly, but it will put all updated products to "Need Update" even if no changes
                        Mage::getSingleton('catalog/product_action')
                            ->updateAttributes($products, array('shoppingflux_default_category' => $category->getId()), $storeId);
                    }
                }
            }
        }
    }
    
    
    /**
     * @deprecated Not used anymore. Caused problems and was not so helpfull..
     */
    public function fillMainCategory() {
        $productCollection = Mage::getModel('catalog/product')->getCollection();
        $productCollection->addAttributeToSelect('shoppingflux_default_category', 'left');
        $productCollection->addAttributeToFilter('shoppingflux_default_category', array(array('null'=>true), array('eq'=>'')));
        foreach($productCollection as $product) {
            $categories = $product->getCategoryIds();
            if(!empty($categories)) {
                shuffle($categories);
                $categoryId = array_shift($categories);
                Mage::getSingleton('catalog/product_action')
                    ->updateAttributes(array($product->getId()), array('shoppingflux_default_category' => $categoryId), 0);
            }
        }
    }

    public function manageUpdates() {
        $apiKeyManaged = array();
        foreach (Mage::app()->getStores() as $store) {
            $apiKey = $this->getConfig()->getApiKey($store->getId());
            if (!$apiKey || in_array($apiKey, $apiKeyManaged))
                continue;
            $apiKeyManaged[] = $apiKey;


            $updates = Mage::getModel('profileolabs_shoppingflux/export_updates')->getCollection();
            $updates->addFieldToFilter('store_id', $store->getId());

            $wsUri = $this->getConfig()->getWsUri();
            $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
            try {
                $service->updateProducts($updates);
                $updates->walk('delete');
            } catch (Exception $e) {
                
            }
        }
    }

    protected function _scheduleProductUpdate(array $data) {
        /*         * REALTIME* */
        $object = new Varien_Object();
        $object->setData($data);
        $collection = new Varien_Data_Collection();
        $collection->addItem($object);
        $apiKey = $this->getConfig()->getApiKey($data['store_id']);
        $wsUri = $this->getConfig()->getWsUri();
        try {
            $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
            $service->updateProducts($collection);
        } catch(Exception $e) {
            
        }
        /*         * SCHEDULED* */
        /*
          $data['updated_at'] = date('Y-m-d H:i:s');
          $updates = Mage::getModel('profileolabs_shoppingflux/export_updates');
          $updates->loadWithData($data);
          foreach ($data as $k => $v)
          $updates->setData($k, $v);
          $updates->save();
         * *
         */
    }

    /**
     * @param mixed $product product id, or Mage_Catalog_Model_Product
     */
    protected function _scheduleProductUpdates($product, array $forceData = array()) {
        if ($product) {
            if (is_numeric($product))
                $product = Mage::getModel('catalog/product')->load($product);
            $productStoresIds = $product->getStoreIds();
            $apiKeyManaged = array();
            foreach ($productStoresIds as $storeId) {
                $apiKey = $this->getConfig()->getApiKey($storeId);
                if (!$apiKey || in_array($apiKey, $apiKeyManaged))
                    continue;
                $apiKeyManaged[] = $apiKey;
                $storeProduct = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());

                $stock = $storeProduct->getStockItem()->getQty();
                if ($this->getConfig()->isExportFilteredByAttribute($storeId) && $storeProduct->getData('shoppingflux_product') != 1) {
                    $stock = 0;
                }
                if ($storeProduct->getStatus() != 1) {
                    $stock = 0;
                }
                $data = array(
                    'store_id' => $storeId,
                    'product_sku' => $storeProduct->getSku(),
                    'stock_value' => $stock,
                    'price_value' => $storeProduct->getFinalPrice(),
                    'old_price_value' => $storeProduct->getPrice()
                );
                foreach ($forceData as $key => $val) {
                    $data[$key] = $val;
                }
                $this->_scheduleProductUpdate($data);
            }
        }
    }

    /**
     * cataloginventory_stock_item_save_after (adminhtml,frontend)
     * @param type $observer
     */
    public function realtimeUpdateStock($observer) {
        Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($observer->getItem()->getProductId());
        if (!$this->getConfig()->isSyncEnabled())
            return;

        $oldStock = (int) $observer->getItem()->getOrigData('qty');
        $newStock = (int) $observer->getItem()->getData('qty');
        if ($oldStock != $newStock) {

            //Mage::log('realtimeUpdateStock');
            $productId = $observer->getItem()->getProductId();
            $this->_scheduleProductUpdates($productId);
        }
    }

    /**
     * catalog_product_save_after (adminhtml)
     * @param type $observer
     */
    public function realtimeUpdatePrice($observer) {
        if ($observer->getProduct()->getSku() != $observer->getProduct()->getOrigData('sku')) {
            Mage::getModel('profileolabs_shoppingflux/export_flux')->updateProductInFluxForAllStores($observer->getProduct()->getOrigData('sku'));
            Mage::getModel('profileolabs_shoppingflux/export_flux')->updateProductInFluxForAllStores($observer->getProduct()->getSku());
        }
        Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($observer->getProduct());


        if (!$this->getConfig()->isSyncEnabled())
            return;

        $product = $observer->getProduct();
        $storeId = $product->getStoreId();
        $attributesToCheck = array('price', 'tax_class_id', 'special_price', 'special_to_date', 'special_from_date');
      
        $somePriceChanged = false;
        foreach ($attributesToCheck as $attributeCode) {
            if ($product->getData($attributeCode) != $product->getOrigData($attributeCode)) {
                $somePriceChanged = true;
            }
        }

        if ($somePriceChanged) {
            //Mage::log('realtimeUpdatePrice');
            if ($storeId == 0) { // update for all stores
                $this->_scheduleProductUpdates($product);
            } else { // change happened in one store, update only this one
                $stock = $product->getStockItem()->getQty();
                $this->_scheduleProductUpdate(array(
                    'store_id' => $storeId,
                    'product_sku' => $product->getSku(),
                    'stock_value' => $stock,
                    'price_value' => $product->getFinalPrice(),
                    'old_price_value' => $product->getPrice()
                ));
            }
        }
    }

    /**
     * catalog_product_save_after (adminhtml)
     * @param type $observer
     */
    public function realtimeUpdateDeletedProduct($observer) {
        if (!$this->getConfig()->isSyncEnabled())
            return;

        $product = $observer->getProduct();
        $apiKeyManaged = array();
        foreach (Mage::app()->getStores() as $store) {
            $apiKey = $this->getConfig()->getApiKey($store->getId());
            if (!$apiKey || in_array($apiKey, $apiKeyManaged))
                continue;
            $apiKeyManaged[] = $apiKey;
            //Mage::log('realtimeUpdateDeletedProduct');

            $this->_scheduleProductUpdate(array(
                'store_id' => $store->getId(),
                'product_sku' => $product->getSku(),
                'stock_value' => 0,
                'price_value' => $product->getPrice(),
                'old_price_value' => $product->getPrice()
            ));
        }
    }

    /**
     * catalog_product_status_update (adminhtml)
     * @param type $observer
     */
    public function realtimeUpdateDisabledProduct($observer) {
        Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($observer->getProductId());
        if (!$this->getConfig()->isSyncEnabled())
            return;

        //Mage::log('realtimeUpdateDisabledProduct');
        $this->_scheduleProductUpdates($observer->getProductId());
    }

    /**
     * catalog_product_save_after (adminhtml)
     * @param type $observer
     */
    public function realtimeUpdateDisabledProductSave($observer) {
        if (!$this->getConfig()->isSyncEnabled())
            return;

        $product = $observer->getProduct();
        if ($product->getStatus() != $product->getOrigData('status')) {
            //Mage::log('realtimeUpdateDisabledProductSave');
            $this->_scheduleProductUpdates($product);
        }
    }

    /**
     * catalog_product_save_after (adminhtml)
     * @param type $observer
     */
    public function realtimeUpdateInSf($observer) {
        if (!$this->getConfig()->isSyncEnabled())
            return;

        $product = $observer->getProduct();
        if ($product->getData('shoppingflux_product') != 1 && $product->getOrigData('shoppingflux_product') == 1) {
            //Mage::log('realtimeUpdateInSf');
            $this->_scheduleProductUpdates($product, array('stock_value' => 0));
        }
    }

    /**
     * shoppingflux_mass_publish_save_item (adminhtml)
     * @param type $observer
     */
    public function realtimeUpdateInSfMass($observer) {
        Mage::getModel('profileolabs_shoppingflux/export_flux')->productNeedUpdate($observer->getProductId());
        if (!$this->getConfig()->isSyncEnabled())
            return;

        $productId = $observer->getProductId();
        $publish = $observer->getShoppingfluxProduct();
        if ($publish != 1) {
            //Mage::log('realtimeUpdateInSfMass');
            $this->_scheduleProductUpdates($productId, array('stock_value' => 0));
        }
    }
    

}
