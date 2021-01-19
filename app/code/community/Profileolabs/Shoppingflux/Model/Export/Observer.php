<?php

class Profileolabs_Shoppingflux_Model_Export_Observer
{
    /**
     * @return Profileolabs_Shoppingflux_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @return Profileolabs_Shoppingflux_Model_Export_Flux
     */
    public function getFluxModel()
    {
        return Mage::getSingleton('profileolabs_shoppingflux/export_flux');
    }

    /**
     * @param int|false $storeId
     */
    public static function checkStock($storeId = false)
    {
        if (!$storeId || !is_numeric($storeId)) {
            $storeId = Mage::app()->getStore()->getId();
        }

        /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
        $productCollection = Mage::getResourceModel('catalog/product_collection');

        $select = $productCollection->getSelect();
        $adapter = $select->getAdapter();

        $select->join(
            array('sf_stock' => $productCollection->getTable('cataloginventory/stock_item')),
            'e.entity_id = sf_stock.product_id',
            array('qty', 'actual_qty' => 'qty')
        );

        $select->join(
            array('flux' => $productCollection->getTable('profileolabs_shoppingflux/export_flux')),
            'e.entity_id = flux.product_id and flux.store_id = ' . $adapter->quote($storeId),
            array('stock_value', 'sku')
        );

        $select->where('CAST(sf_stock.qty AS SIGNED) != flux.stock_value');

        if (Mage::getStoreConfigFlag(Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK, $storeId)) {
            $select->where(
                '(sf_stock.use_config_manage_stock = 0 and sf_stock.manage_stock = 1)'
                . ' OR '
                . '(sf_stock.use_config_manage_stock = 1)'
            );
        } else {
            $select->where(
                '(sf_stock.use_config_manage_stock = 0 AND sf_stock.manage_stock = 1)'
            );
        }

        $select->where('flux.update_needed = 0');
        $select->group('e.entity_id');

        /** @var Profileolabs_Shoppingflux_Model_Export_Flux $fluxModel */
        $fluxModel = Mage::getModel('profileolabs_shoppingflux/export_flux');

        foreach ($productCollection as $product) {
            $fluxModel->productNeedUpdate($product);
        }
    }

    public function updateFlux()
    {
        $helper = $this->getHelper();

        if (Mage::getStoreConfigFlag('shoppingflux_export/general/enable_cron')) {
            foreach (Mage::app()->getStores() as $store) {
                if ($feedUrl = $helper->getFeedUrl($store)) {
                    file_get_contents($feedUrl);
                }
            }
        }
    }

    /**
     * @param int $storeId
     */
    protected function generateFluxInFileForStore($storeId)
    {
        $filePath = Mage::getBaseDir('media') . DS . 'shoppingflux_' . $storeId . '.xml';
        $handle = fopen($filePath, 'a');
        ftruncate($handle, 0);

        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/export_flux_collection');
        $collection->addFieldToFilter('should_export', 1);
        $collection->addFieldToFilter('store_id', $storeId);
        $sizeTotal = $collection->getSize();
        $collection->clear();
        $withNotSalableRetention = $this->getConfig()->isNotSalableRetentionEnabled($storeId);

        if (!$this->getConfig()->isExportNotSalable($storeId) && !$withNotSalableRetention) {
            $collection->addFieldToFilter('salable', 1);
        }

        if (!$this->getConfig()->isExportSoldout($storeId) && !$withNotSalableRetention) {
            $collection->addFieldToFilter('is_in_stock', 1);
        }

        if ($this->getConfig()->isExportFilteredByAttribute($storeId)) {
            $collection->addFieldToFilter('is_in_flux', 1);
        }

        $visibilities = $this->getConfig()->getVisibilitiesToExport($storeId);
        $collection->getSelect()->where('FIND_IN_SET(visibility, ?)', implode(',', $visibilities));

        /** @var Profileolabs_Shoppingflux_Model_Export_Xml $xmlObject */
        $xmlObject = Mage::getModel('profileolabs_shoppingflux/export_xml');

        $xmlStart = $xmlObject->startXml(
            array(
                'size-exportable' => $sizeTotal,
                'size-xml' => $collection->getSize(),
                'with-out-of-stock' => (int) $this->getConfig()->isExportSoldout(),
                'with-not-salable' => (int) $this->getConfig()->isExportNotSalable(),
                'selected-only' => (int) $this->getConfig()->isExportFilteredByAttribute(),
                'visibilities' => implode(',', $visibilities),
            )
        );

        fwrite($handle, $xmlStart);

        /** @var Mage_Core_Model_Resource_Iterator $iterator */
        $iterator = Mage::getSingleton('core/resource_iterator');
        $iterator->walk($collection->getSelect(), array(array($this, 'writeProductXml')), array('handle' => $handle));

        $endXml = $xmlObject->endXml();
        fwrite($handle, $endXml);
        fclose($handle);
    }

    /**
     * @param array $args
     */
    public function writeProductXml(array $args)
    {
        fwrite($args['handle'], $args['row']['xml']);
    }

    public function generateFluxInFile()
    {
        $this->generateFluxInFileForStore(Mage::app()->getDefaultStoreView()->getId());
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function addShoppingfluxProductsTab($observer)
    {
        if (($tabs = $observer->getEvent()->getData('tabs'))
            && ($tabs instanceof Mage_Adminhtml_Block_Widget_Tabs)
        ) {
            /** @var Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Tab_Default $productsTab */
            $productsTab = $tabs->getLayout()->createBlock(
                'profileolabs_shoppingflux/export_adminhtml_catalog_category_tab_default',
                'shoppingflux.product.grid'
            );

            $tabs->addTab(
                'shoppingflux_products',
                array(
                    'label' => $this->getHelper()->__('Shoppingflux Category Products'),
                    'content' => $productsTab->toHtml(),
                )
            );
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductAttributeUpdateBefore($observer)
    {
        if (is_array($productIds = $observer->getEvent()->getData('product_ids'))) {
            foreach ($productIds as $productId) {
                $this->getFluxModel()->productNeedUpdate($productId);
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductSaveCommitAfter($observer)
    {
        if (($product = $observer->getEvent()->getData('product'))
            && ($product instanceof Mage_Catalog_Model_Product)
        ) {
            $this->getFluxModel()->productNeedUpdate($product->getId());
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function catalogProductDeleteAfter($observer)
    {
        if (($product = $observer->getEvent()->getData('product'))
            && ($product instanceof Mage_Catalog_Model_Product)
        ) {
            $sku = trim($product->getSku());

            if ('' !== $sku) {
                $fluxModel = $this->getFluxModel();

                try {
                    /** @var Mage_Core_Model_Store $store */
                    foreach (Mage::app()->getStores() as $store) {
                        $storeId = $store->getId();
                        $fluxEntry = $fluxModel->getEntry($sku, $storeId);

                        if ($fluxEntry->getId()) {
                            $fluxEntry->setData('should_export', 0);
                            $fluxEntry->setData('update_needed', 1);
                            $fluxEntry->save();
                        }
                    }
                } catch (Exception $e) {
                    // Let the "remove_old_products" cron job clean up the entry later.
                }
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function saveShoppingfluxCategoryProducts($observer)
    {
        if (($category = $observer->getEvent()->getData('category'))
            && ($category instanceof Mage_Catalog_Model_Category)
            && ($request = $observer->getEvent()->getData('request'))
            && ($request instanceof Zend_Controller_Request_Http)
        ) {
            $postedProducts = $request->getParam('shoppingflux_category_products');
            $storeId = (int) $request->getParam('store', 0);

            /** @var Profileolabs_Shoppingflux_Helper_String $stringHelper */
            $stringHelper = Mage::helper('profileolabs_shoppingflux/string');
            $products = $stringHelper->parseQueryStr($postedProducts);

            if (isset($products['on'])) {
                unset($products['on']);
            }

            $products = array_keys($products);

            if (!empty($products)) {
                $currentVersion = Mage::getVersion();
                /** @var Mage_Catalog_Model_Product $product */
                $product = Mage::getModel('catalog/product');
                /** @var Mage_Catalog_Model_Product_Action $actionModel */
                $actionModel = Mage::getSingleton('catalog/product_action');

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
                            $actionModel->updateAttributes(
                                $products,
                                array('shoppingflux_default_category' => $category->getId()),
                                $storeId
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array $data
     */
    protected function _scheduleProductUpdate(array $data)
    {
        $object = new Varien_Object();
        $object->setData($data);
        $collection = new Varien_Data_Collection();
        $collection->addItem($object);
        $apiKey = $this->getConfig()->getApiKey($data['store_id']);
        $wsUri = $this->getConfig()->getWsUri();

        try {
            $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
            $service->updateProducts($collection);
        } catch (Exception $e) {
        }
    }

    /**
     * @param int $productId
     * @param array $forceData
     */
    protected function _scheduleProductUpdates($productId, array $forceData = array())
    {
        if ($productId) {
            /** @var Mage_Catalog_Model_Product $product */
            $product = Mage::getModel('catalog/product');
            $product->load($productId);
            $productStoreIds = $product->getStoreIds();
            $handledApiKeys = array();

            foreach ($productStoreIds as $storeId) {
                $apiKey = $this->getConfig()->getApiKey($storeId);

                if (!$apiKey || in_array($apiKey, $handledApiKeys)) {
                    continue;
                }

                $handledApiKeys[] = $apiKey;

                /** @var Mage_Catalog_Model_Product $storeProduct */
                $storeProduct = Mage::getModel('catalog/product');
                $storeProduct->setStoreId($storeId);
                $storeProduct->load($product->getId());

                /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
                $stockItem = $storeProduct->getStockItem();
                $stock = $stockItem->getQty();

                if (!$storeProduct->getData('shoppingflux_product')
                    && $this->getConfig()->isExportFilteredByAttribute($storeId)
                ) {
                    $stock = 0;
                } elseif ($storeProduct->getStatus() != Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                    $stock = 0;
                }

                $data = array(
                    'store_id' => $storeId,
                    'product_sku' => $storeProduct->getSku(),
                    'stock_value' => $stock,
                    'price_value' => $storeProduct->getFinalPrice(),
                    'old_price_value' => $storeProduct->getPrice()
                );

                foreach ($forceData as $key => $value) {
                    $data[$key] = $value;
                }

                $this->_scheduleProductUpdate($data);
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdateStock($observer)
    {
        /** @var Mage_CatalogInventory_Model_Stock_Item $item */
        if ($item = $observer->getEvent()->getData('item')) {
            $this->getFluxModel()->productNeedUpdate($item->getProductId());

            if (!$this->getConfig()->isSyncEnabled()) {
                return;
            }

            $oldStock = (int) $item->getOrigData('qty');
            $newStock = (int) $item->getData('qty');

            if ($oldStock != $newStock) {
                $productId = $item->getProductId();
                $this->_scheduleProductUpdates($productId);
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdatePrice($observer)
    {
        if (($product = $observer->getEvent()->getData('product'))
            && ($product instanceof Mage_Catalog_Model_Product)
        ) {
            $fluxModel = $this->getFluxModel();

            if ($product->getSku() != $product->getOrigData('sku')) {
                $fluxModel->updateProductInFluxForAllStores($product->getOrigData('sku'));
                $fluxModel->updateProductInFluxForAllStores($product->getSku());
            }

            $fluxModel->productNeedUpdate($product);

            if (!$this->getConfig()->isSyncEnabled()) {
                return;
            }

            $storeId = $product->getStoreId();
            $checkableAttributes = array(
                'price',
                'tax_class_id',
                'special_price',
                'special_to_date',
                'special_from_date'
            );

            $anyPriceChanged = false;

            foreach ($checkableAttributes as $attributeCode) {
                if ($product->getData($attributeCode) != $product->getOrigData($attributeCode)) {
                    $anyPriceChanged = true;
                    break;
                }
            }

            if ($anyPriceChanged) {
                if ($storeId == 0) {
                    $this->_scheduleProductUpdates($product->getId());
                } else {
                    $this->_scheduleProductUpdate(
                        array(
                            'store_id' => $storeId,
                            'product_sku' => $product->getSku(),
                            'stock_value' => $product->getStockItem()->getQty(),
                            'price_value' => $product->getFinalPrice(),
                            'old_price_value' => $product->getPrice()
                        )
                    );
                }
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdateDeletedProduct($observer)
    {
        if (!$this->getConfig()->isSyncEnabled()) {
            return;
        }

        if (($product = $observer->getEvent()->getData('product'))
            && ($product instanceof Mage_Catalog_Model_Product)
        ) {
            $handledApiKeys = array();

            /** @var Mage_Core_Model_Store $store */
            foreach (Mage::app()->getStores() as $store) {
                $apiKey = $this->getConfig()->getApiKey($store->getId());

                if (!$apiKey || in_array($apiKey, $handledApiKeys)) {
                    continue;
                }

                $this->_scheduleProductUpdate(
                    array(
                        'store_id' => $store->getId(),
                        'product_sku' => $product->getSku(),
                        'stock_value' => 0,
                        'price_value' => $product->getPrice(),
                        'old_price_value' => $product->getPrice()
                    )
                );
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdateDisabledProduct($observer)
    {
        if ($productId = $observer->getEvent()->getData('product_id')) {
            $this->getFluxModel()->productNeedUpdate($productId);

            if (!$this->getConfig()->isSyncEnabled()) {
                return;
            }

            $this->_scheduleProductUpdates($productId);
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdateDisabledProductSave($observer)
    {
        if (!$this->getConfig()->isSyncEnabled()) {
            return;
        }

        if (($product = $observer->getEvent()->getData('product'))
            && ($product instanceof Mage_Catalog_Model_Product)
            && ($product->getStatus() != $product->getOrigData('status'))
        ) {
            $this->_scheduleProductUpdates($product->getId());
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdateInSf($observer)
    {
        if (!$this->getConfig()->isSyncEnabled()) {
            return;
        }
        if (($product = $observer->getEvent()->getData('product'))
            && ($product instanceof Mage_Catalog_Model_Product)
            && !$product->getData('shoppingflux_product')
            && $product->getOrigData('shoppingflux_product') == 1
        ) {
            $this->_scheduleProductUpdates($product->getId(), array('stock_value' => 0));
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function realtimeUpdateInSfMass($observer)
    {
        if ($productId = $observer->getEvent()->getData('product_id')) {
            $this->getFluxModel()->productNeedUpdate($productId);

            if (!$this->getConfig()->isSyncEnabled()) {
                return;
            }

            if (!$observer->getEvent()->getData('shoppingflux_product')) {
                $this->_scheduleProductUpdates($productId, array('stock_value' => 0));
            }
        }
    }

    /**
     * @param int $storeId
     * @param string $stockAlias
     * @return Zend_Db_Select
     */
    protected function _getSalableProductsSelect($storeId, $stockAlias)
    {
        /** @var Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = Mage::getResourceModel('catalog/product');

        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        $collection->joinTable(
            array($stockAlias => $productResource->getTable('cataloginventory/stock_item')),
            'product_id=entity_id',
            array('product_id')
        );

        $select = $collection->getSelect();

        if (Mage::getStoreConfigFlag('cataloginventory/item_options/manage_stock')) {
            $select->where(
                '(' . $stockAlias . '.use_config_manage_stock = 0 AND ' . $stockAlias . '.manage_stock = 0)'
                . ' OR '
                . $stockAlias . '.is_in_stock = 1'
            );
        } else {
            $select->where(
                $stockAlias . '.use_config_manage_stock = 1'
                . ' OR '
                . $stockAlias . '.manage_stock = 0'
                . ' OR '
                . $stockAlias . '.is_in_stock = 1'
            );
        }

        return $select;
    }

    /**
     * @param int $storeId
     */
    protected function _refreshNotSalableProducts($storeId)
    {
        /** @var Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = Mage::getResourceModel('catalog/product');
        $readConnection = $productResource->getReadConnection();
        $writeConnection = $productResource->getWriteConnection();

        $salableGlobalSelect = $this->_getSalableProductsSelect($storeId, '_ciss');
        $typeConditions = array();

        // Specific conditions for configurable products

        $inStockChildrenSelect = $this->_getSalableProductsSelect($storeId, '_cciss');

        $inStockChildrenSelect->reset(Varien_Db_Select::COLUMNS)
            ->columns(array('count' => new Zend_Db_Expr('COUNT(*)')))
            ->joinInner(
                array('_ccsl' => $productResource->getTable('catalog/product_super_link')),
                '_ccsl.product_id = e.entity_id',
                array()
            )
            ->where('_ccsl.parent_id = e.entity_id');

        $typeConditions[] = '('
            . $readConnection->quoteInto('(e.type_id != ?)', Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE)
            . ' OR '
            . '(' . $inStockChildrenSelect->assemble() . ' > 0)'
            . ')';

        // Global select finalization

        $salableGlobalSelect->reset(Varien_Db_Select::COLUMNS)
            ->columns(array('e.entity_id'))
            ->where(implode(' OR ', $typeConditions));

        $notSalableGlobalSelect = $readConnection->select()
            ->from(
                array('_main_table' => $productResource->getTable('catalog/product')),
                array(
                    'product_id' => '_main_table.entity_id',
                    'store_id' => new Zend_Db_Expr($storeId),
                    'not_salable_from' => new Zend_Db_Expr($writeConnection->quote(now())),
                )
            )
            ->where('_main_table.entity_id NOT IN (' . $salableGlobalSelect->assemble() . ')');

        $nowTime = time();
        $writeConnection->beginTransaction();

        try {
            // Retrieve products that were already updated in the flux

            $updatedIds = $readConnection->fetchCol(
                $readConnection->select()
                    ->from(
                        $productResource->getTable('profileolabs_shoppingflux/updated_not_salable_product'),
                        array('product_id')
                    )
                    ->where('store_id = ?', $storeId)
            );

            // Remember new not salable products

            $writeConnection->query(
                $notSalableGlobalSelect->insertIgnoreFromSelect(
                    $productResource->getTable('profileolabs_shoppingflux/not_salable_product'),
                    array('product_id', 'store_id', 'not_salable_from')
                )
            );

            // Forget products that are salable again

            $writeConnection->delete(
                $productResource->getTable('profileolabs_shoppingflux/not_salable_product'),
                'product_id IN (' . $salableGlobalSelect->assemble() . ')'
                . ' AND ' . $writeConnection->quoteInto('store_id = ?', $storeId)
            );

            if ($seconds = $this->getConfig()->getNotSalableRetentionDuration($storeId)) {
                // Retrieve products that are updatable, no matter if they have already been,
                // because they have been not salable long enough

                $updatableSelect = $readConnection->select()
                    ->from(
                        $productResource->getTable('profileolabs_shoppingflux/not_salable_product'),
                        array('product_id', 'store_id')
                    )
                    ->where('store_id = ?', $storeId)
                    ->where('UNIX_TIMESTAMP(not_salable_from) <= ?', $nowTime - $seconds);

                $updatableIds = $readConnection->fetchCol($updatableSelect);

                // Remember the products that will soon be updated in the flux

                $writeConnection->query(
                    $updatableSelect->insertFromSelect(
                        $productResource->getTable('profileolabs_shoppingflux/updated_not_salable_product'),
                        array('product_id', 'store_id')
                    )
                );
            } else {
                $updatableIds = array();
            }

            // Reset the products that had exceeded delay, but do not anymore because of a change in the configuration

            $resettableIdsSelect = $readConnection->select()
                ->from(
                    $productResource->getTable('profileolabs_shoppingflux/not_salable_product'),
                    array('product_id')
                )
                ->where('store_id = ?', $storeId);

            if ($seconds) {
                $resettableIdsSelect->where('UNIX_TIMESTAMP(not_salable_from) >= ?', $nowTime - $seconds);
            }

            $writeConnection->delete(
                $productResource->getTable('profileolabs_shoppingflux/updated_not_salable_product'),
                'product_id IN (' . $resettableIdsSelect->assemble() . ')'
                . ' AND ' . $writeConnection->quoteInto('store_id = ?', $storeId)
            );

            // Ensure that will be updated products that were updated because they had exceeded not salable delay,
            // but that either are now salable again, either now have a delay shorter than the newly configured one, ..

            $fluxUpdatableIds = array_diff($updatedIds, $updatableIds);

            // .. and new not salable products

            $fluxUpdatableIds = array_unique(
                array_merge(
                    $fluxUpdatableIds,
                    array_diff($updatableIds, $updatedIds)
                )
            );

            /*
             * Example:
             * $updatedIds   = [1, 2, 3, 4] - IDs that had previously exceeded not salable delay
             * $updatableIds = [3, 4, 5, 6] - IDs that currently exceed not salable delay
             * 
             * First diff  = [1, 2] => products that are salable again, or do not exceed delay anymore
             * Second diff = [5, 6] => products that were not exceeding delay, but now do
             * Remaining   = [3, 4] => products that have not changed since last time
             **/

            // Mark the necessary flux products as updatable

            $sliceSize = 500;

            $productsSkus = $readConnection->fetchPairs(
                $readConnection->select()
                    ->from(
                        $productResource->getTable('catalog/product'),
                        array('entity_id', 'sku')
                    )
            );

            $sliceCount = ceil(count($fluxUpdatableIds) / $sliceSize);

            for ($i = 0; $i < $sliceCount; $i++) {
                $productsIds = array_slice($fluxUpdatableIds, $i * $sliceSize, $sliceSize);
                $updatedSkus = array();

                foreach ($productsIds as $productId) {
                    if (isset($productsSkus[$productId])) {
                        $updatedSkus[] = $productsSkus[$productId];
                    }
                }

                $writeConnection->update(
                    $productResource->getTable('profileolabs_shoppingflux/export_flux'),
                    array('update_needed' => 1),
                    $writeConnection->quoteInto('sku IN (?)', $updatedSkus)
                    . ' AND ' . $writeConnection->quoteInto('store_id = ?', $storeId)
                );
            }

            $writeConnection->commit();

            /** @var Mage_Core_Model_Config $mageConfig */
            $mageConfig = Mage::getSingleton('core/config');

            $mageConfig->saveConfig(
                'shoppingflux_export/not_salable_retention/last_refresh',
                time(),
                'stores',
                $storeId
            );
        } catch (Exception $e) {
            $writeConnection->rollback();
        }
    }

    public function refreshNotSalableProducts()
    {
        /** @var Mage_Core_Model_Config $mageConfig */
        $config = $this->getConfig();
        $storeLastRefreshes = array();

        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores(false) as $store) {
            $storeId = $store->getId();

            if ($config->isExportEnabled($storeId) && $config->isNotSalableRetentionEnabled($storeId)) {
                $storeLastRefreshes[$store->getId()] = (int) Mage::getStoreConfig(
                    'shoppingflux_export/not_salable_retention/last_refresh',
                    $storeId
                );
            }
        }

        if (!empty($storeLastRefreshes)) {
            asort($storeLastRefreshes, SORT_NUMERIC);
            reset($storeLastRefreshes);
            $storeId = key($storeLastRefreshes);
            $this->_refreshNotSalableProducts($storeId);
        }
    }
}
