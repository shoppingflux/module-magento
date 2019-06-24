<?php

class Profileolabs_Shoppingflux_Model_Export_Flux extends Mage_Core_Model_Abstract
{
    /**
     * @var int|null
     */
    protected $_memoryLimit = null;

    /**
     * @var int|null
     */
    protected $_maxExecutionTime = null;

    /**
     * @var array
     */
    protected $_attributesFromConfig = array();

    /**
     * @var array
     */
    protected $_attributes = array();

    /**
     * @var array
     */
    protected $_attributesConfigurable = array();

    /**
     * @var array
     */
    protected $_storeCategories = array();

    /**
     * @var array
     */
    protected $_excludedNotSalableProductsIds = array();

    protected function _construct()
    {
        $this->_init('profileolabs_shoppingflux/export_flux');
    }

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
     * @param string $productSku
     * @param int $storeId
     * @return Profileolabs_Shoppingflux_Model_Export_Flux
     */
    public function getEntry($productSku, $storeId)
    {
        $collection = $this->getCollection();
        $collection->addFieldToFilter('sku', $productSku);
        $collection->addFieldToFilter('store_id', $storeId);

        if ($collection->getSize() > 0) {
            $collection->setCurPage(1);
            $collection->setPageSize(1);
            return $collection->getFirstItem();
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');

        /** @var Profileolabs_Shoppingflux_Model_Export_Flux $entryModel */
        $entryModel = Mage::getModel('profileolabs_shoppingflux/export_flux');
        $entryModel->setStoreId($storeId);
        $entryModel->setSku($productSku);
        $entryModel->setProductId($product->getIdBySku($productSku));
        $entryModel->setUpdateNeeded(0);

        return $entryModel;
    }

    /**
     * @param Mage_Catalog_Model_Product|int $product
     * @param int $storeId
     * @return Mage_Catalog_Model_Product|false
     */
    protected function _getProduct($product, $storeId)
    {
        if ($product instanceof Mage_Catalog_Model_Product) {
            $productId = $product->getId();
        } else {
            $productId = (int) $product;
        }

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');

        $select = $read->select()
            ->distinct()
            ->from($resource->getTableName('catalog/product_website'), array('website_id'))
            ->where('product_id = ?', $productId);

        $result = $read->fetchAll($select);
        $websiteIds = array();

        foreach ($result as $row) {
            $websiteIds[] = $row['website_id'];
        }

        if (!in_array(Mage::app()->getStore($storeId)->getWebsiteId(), $websiteIds)) {
            return false;
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        $product->setStoreId($storeId);
        $product->load($productId);

        if (!$product->getId()) {
            return false;
        }

        return $product;
    }

    /**
     * @param string $productSku
     * @param int $storeId
     * @return Mage_Catalog_Model_Product|false
     */
    protected function _getProductBySku($productSku, $storeId)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        $productId = $product->getIdBySku($productSku);
        return ($productId ? $this->_getProduct($productId, $storeId) : false);
    }

    /**
     * @param array $args
     */
    public function addMissingProduct(array $args)
    {
        $storeId = $args['store_id'];
        $this->updateProductInFlux($args['row']['sku'], $storeId);
    }

    /**
     * @param array $args
     */
    public function removeDeletedProduct($args)
    {
        /** @var Profileolabs_Shoppingflux_Model_Export_Flux $fluxItem */
        $fluxItem = Mage::getModel('profileolabs_shoppingflux/export_flux');
        $fluxItem->load($args['row']['id']);
        $fluxItem->delete();
    }

    public function checkForDeletedProducts()
    {
        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux_Collection $collection */
        $collection = $this->getCollection();
        $select = $collection->getSelect();
        $adapter = $select->getAdapter();

        $select->joinLeft(
            array('cp_table' => $collection->getTable('catalog/product')),
            'cp_table.entity_id = main_table.product_id',
            array()
        );

        $existingStoreIds = array();

        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores() as $store) {
            $existingStoreIds[] = $store->getId();
        }

        $select->where(
            'cp_table.entity_id IS NULL or main_table.store_id NOT IN (' . $adapter->quote($existingStoreIds) . ')'
        );

        /** @var Mage_Core_Model_Resource_Iterator $iterator */
        $iterator = Mage::getSingleton('core/resource_iterator');
        $iterator->walk($select, array(array($this, 'removeDeletedProduct')));
    }

    /**
     * @param int|false $inStoreId
     * @param int $maxImport
     */
    public function checkForMissingProducts($inStoreId = false, $maxImport = 1000)
    {
        ini_set('display_errors', 1);
        error_reporting(-1);

        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();

            if (!$this->getConfig()->isExportEnabled($storeId)) {
                continue;
            }

            if (!$inStoreId || ($storeId == $inStoreId)) {
                /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
                $productCollection = Mage::getResourceModel('catalog/product_collection');
                $productCollection->addStoreFilter($storeId);
                $productCollection->setStoreId($storeId);
                $productCollection->addAttributeToSelect('sku', 'left');

                $fluxTableName = $productCollection->getTable('profileolabs_shoppingflux/export_flux');
                $select = $productCollection->getSelect();
                $adapter = $select->getAdapter();

                $productCollection->getSelect()
                    ->joinLeft(
                        array('sf' => $fluxTableName),
                        'entity_id = sf.product_id and store_id = ' . $adapter->quote($storeId),
                        array('skusf' => 'sku')
                    );

                $productCollection->setPage(1, $maxImport);
                $select->where('sf.product_id IS NULL');

                /** @var Mage_Core_Model_Resource_Iterator $iterator */
                $iterator = Mage::getSingleton('core/resource_iterator');
                $iterator->walk($select, array(array($this, 'addMissingProduct')), array('store_id' => $storeId));
            }
        }

    }

    /**
     * @param int|false $forStoreId
     * @param int $maxImportLimit
     * @param bool $shouldExportOnly
     */
    public function updateFlux($forStoreId = false, $maxImportLimit = 1000, $shouldExportOnly = false)
    {
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $isCurrentStore = (Mage::app()->getStore()->getId() == $storeId);

            if (!$forStoreId || ($forStoreId == $storeId)) {
                if (!$isCurrentStore) {
                    /** @var Mage_Core_Model_App_Emulation $appEmulation */
                    if ($appEmulation = Mage::getSingleton('core/app_emulation')) {
                        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
                    }
                }

                try {
                    /** @var Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux_Collection $collection */
                    $collection = $this->getCollection();
                    $collection->addFieldToFilter('update_needed', 1);
                    $collection->addFieldToFilter('store_id', $storeId);
                    $collection->getSelect()->order('rand()');

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

    /**
     * @param int $storeId
     * @return int[]
     */
    protected function _getExcludedNotSalableProductsIds($storeId)
    {
        if (!isset($this->_excludedNotSalableProductsIds[$storeId])) {
            if ($seconds = $this->getConfig()->getNotSalableRetentionDuration($storeId)) {
                $resource = $this->getResource();
                $connection = $resource->getReadConnection();

                $this->_excludedNotSalableProductsIds[$storeId] = $connection->fetchCol(
                    $connection->select()
                        ->from(
                            $resource->getTable('profileolabs_shoppingflux/not_salable_product'),
                            array('product_id')
                        )
                        ->where('UNIX_TIMESTAMP(not_salable_from) <= ?', time() - $seconds)
                );
            } else {
                $this->_excludedNotSalableProductsIds[$storeId] = array();
            }
        }

        return $this->_excludedNotSalableProductsIds[$storeId];
    }

    /**
     * @param int $productId
     * @param int $storeId
     * @param bool $ignoreRelations
     */
    public function productNeedUpdateForStore($productId, $storeId, $ignoreRelations = false)
    {
        $product = $this->_getProduct($productId, $storeId);

        if ($product && $product->getId()) {
            $fluxEntry = $this->getEntry($product->getSku(), $storeId);

            if (!$fluxEntry->getData('update_needed')) {
                $fluxEntry->setData('update_needed', 1);
                $fluxEntry->save();
            }

            if (!$ignoreRelations) {
                /** @var Mage_Catalog_Model_Product_Type_Configurable $configurableModel */
                $configurableModel = Mage::getSingleton('catalog/product_type_configurable');
                $parentIds = $configurableModel->getParentIdsByChild($product->getId());

                foreach ($parentIds as $parentId) {
                    $this->productNeedUpdateForStore($parentId, $storeId, true);
                }

                if ($product->getTypeId() == 'configurable') {
                    $childProducts = $configurableModel->getUsedProducts(null, $product);

                    /** @var Mage_Catalog_Model_Product $childProduct */
                    foreach ($childProducts as $childProduct) {
                        if ($childProduct->getTypeId() == 'simple') {
                            $this->productNeedUpdateForStore($childProduct->getId(), $storeId, true);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param int $productId
     */
    public function productNeedUpdate($productId)
    {
        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $this->productNeedUpdateForStore($productId, $storeId);
        }
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return bool
     */
    protected function _shouldUpdate($product, $storeId)
    {
        if (!$this->getConfig()->isExportEnabled($storeId)
            || ($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
            || in_array($product->getTypeId(), array('bundle', 'grouped', 'virtual'))
        ) {
            return false;
        }

        $productId = $product->getId();
        $exportNotSalable = $this->getConfig()->isExportNotSalable();
        $retainNotSalable = $this->getConfig()->isNotSalableRetentionEnabled();

        if (!$product->isSalable()) {
            if ((!$exportNotSalable && !$retainNotSalable)
                || ($retainNotSalable && in_array($productId, $this->_getExcludedNotSalableProductsIds($storeId)))
            ) {
                return false;
            }
        }

        if ($product->getTypeId() == 'simple') {
            /** @var Mage_Catalog_Model_Resource_Product_Type_Configurable $configurableResource */
            $configurableResource = Mage::getResourceSingleton('catalog/product_type_configurable');
            $parentIds = $configurableResource->getParentIdsByChild($productId);

            if (!empty($parentIds)) {
                /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
                $collection = Mage::getResourceModel('catalog/product_collection');
                $collection->addStoreFilter($storeId);
                $collection->addIdFilter($parentIds);
                $parentIds = $collection->getAllIds();
            }

            if (!empty($parentIds)) {
                return false;
            }
        }

        $store = Mage::app()->getStore($storeId);

        if (!in_array($store->getWebsiteId(), $product->getWebsiteIds())) {
            return false;
        }

        return true;
    }

    /**
     * @param string $attributeCode
     * @param int|null $storeId
     * @return Mage_Eav_Model_Entity_Attribute_Abstract|false
     */
    protected function _getAttribute($attributeCode, $storeId = null)
    {
        if (!isset($this->_attributes[$attributeCode])) {
            /** @var Mage_Eav_Model_Config $eavConfig */
            $eavConfig = Mage::getSingleton('eav/config');
            $this->_attributes[$attributeCode] = $eavConfig->getAttribute('catalog_product', $attributeCode);

            if ($storeId) {
                $this->_attributes[$attributeCode]->setStoreId($storeId);
            }
        }

        return $this->_attributes[$attributeCode];
    }

    /**
     * @param string $dataKey
     * @param string $attributeCode
     * @param Mage_Catalog_Model_Product $product
     * @param int|null $storeId
     * @return mixed
     */
    protected function _getAttributeDataForProduct($dataKey, $attributeCode, $product, $storeId = null)
    {
        if (!$attributeCode) {
            return '';
        }

        $attribute = $this->_getAttribute($attributeCode, $storeId);
        $data = $product->getData($attributeCode);

        if ($attribute) {
            if ($attribute->getFrontendInput() === 'date') {
                return $data;
            }

            if ($attribute->usesSource()) {
                if (is_array($data = $attribute->getSource()->getOptionText($data))) {
                    $data = implode(', ', $data);
                }
            }

            if ($attribute->getFrontendInput() === 'weee') {
                /** @var Mage_Weee_Model_Tax $weeeTaxModel */
                $weeeTaxModel = Mage::getSingleton('weee/tax');
                $weeeAttributes = $weeeTaxModel->getProductWeeeAttributes($product);

                if (isset($data[0]) && isset($data[0]['value'])) {
                    $data = $data[0]['value'];
                }

                foreach ($weeeAttributes as $weeeAttribute) {
                    if ($weeeAttribute->getCode() == $attributeCode) {
                        $data = round($weeeAttribute->getAmount(), 2);
                        break;
                    }
                }
            }
        }

        if ($dataKey === 'shipping_delay') {
            if (empty($data)) {
                $data = $this->getConfig()
                    ->getConfigData(
                        'shoppingflux_export/general/default_shipping_delay',
                        $storeId
                    );
            }
        } elseif ($dataKey === 'quantity') {
            $data = round($data);
        }

        if (is_array($data)) {
            $data = implode(',', $data);
        }

        return trim($data);
    }

    protected function _checkMemory()
    {
        $request = Mage::app()->getRequest();

        if (($request->getControllerName() === 'export_flux') && ($request->getActionName() === 'index')) {
            if ($this->_memoryLimit === null) {
                $memoryLimit = ini_get('memory_limit');

                if (preg_match('%M$%', $memoryLimit)) {
                    $this->_memoryLimit = (int) $memoryLimit * 1024 * 1024;
                } else if (preg_match('%G$%', $memoryLimit)) {
                    $this->_memoryLimit = (int) $memoryLimit * 1024 * 1024 * 1024;
                } else {
                    $this->_memoryLimit = false;
                }

                $configTimeLimit = intval(Mage::getStoreConfig('shoppingflux_export/general/execution_time_limit'));
                $iniTimeLimit = ini_get('max_execution_time');

                if ($configTimeLimit > 1) {
                    $this->_maxExecutionTime = $configTimeLimit;
                } elseif ($iniTimeLimit > 1) {
                    $this->_maxExecutionTime = $iniTimeLimit;
                } else {
                    $this->_maxExecutionTime = 600;
                }

                // Take into account the 10-minutes timeout on Shopping Feed side
                $this->_maxExecutionTime = min($this->_maxExecutionTime, 9 * 60);
            }

            $isTimeToDie = (microtime(true) - Mage::registry('export_feed_start_at') > $this->_maxExecutionTime);

            if ($this->_memoryLimit > 0 || $isTimeToDie) {
                $currentMemoryUsage = memory_get_usage(true);

                if ($isTimeToDie || ($this->_memoryLimit - 15 * 1024 * 1024 <= $currentMemoryUsage)) {
                    $reasons = array();

                    if ($isTimeToDie) {
                        $reasons[] = 'Is Time to die : Execution time : ' .
                            round(microtime(true) - Mage::registry('export_feed_start_at'), 2)
                            . ' - Max execution time : '
                            . $this->_maxExecutionTime;
                    }

                    if ($this->_memoryLimit - 10 * 1024 * 1024 <= $currentMemoryUsage) {
                        $reasons[] = 'Memory limit : Used ' . $currentMemoryUsage . ' of ' . $this->_memoryLimit;
                    }

                    throw new Profileolabs_ShoppingFlux_Model_Export_Flux_Exception(implode(',', $reasons));
                }
            }
        }
    }

    /**
     * @param string $productSku
     */
    public function updateProductInFluxForAllStores($productSku)
    {
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $isCurrentStore = (Mage::app()->getStore()->getId() == $storeId);

            try {
                if (!$isCurrentStore) {
                    /** @var Mage_Core_Model_App_Emulation $appEmulation */
                    if ($appEmulation = Mage::getSingleton('core/app_emulation')) {
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

    /**
     * @param string $productSku
     * @param int $storeId
     */
    public function updateProductInFlux($productSku, $storeId)
    {
        $product = $this->_getProductBySku($productSku, $storeId);

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getSingleton('core/date');

        if (!$product || !$product->getSku()) {
            $fluxEntry = $this->getEntry($productSku, $storeId);
            $fluxEntry->setData('should_export', 0);
            $fluxEntry->setData('update_needed', 0);
            $fluxEntry->setData('updated_at', date('Y-m-d H:i:s', $dateModel->timestamp(time())));
            $fluxEntry->save();
            return;
        }

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = $product->getStockItem();

        if (!$this->_shouldUpdate($product, $storeId)) {
            $fluxEntry = $this->getEntry($product->getSku(), $storeId);
            $fluxEntry->setData('should_export', 0);
            $fluxEntry->setData('update_needed', 0);
            $fluxEntry->setData('stock_value', $stockItem->getQty());
            $fluxEntry->setData('updated_at', date('Y-m-d H:i:s', $dateModel->timestamp(time())));
            $fluxEntry->save();
            return;
        }

        /** @var Profileolabs_Shoppingflux_Model_Export_Xml $xmlObject */
        $xmlObject = Mage::getModel('profileolabs_shoppingflux/export_xml');
        $xml = '';

        if ($this->getConfig()->useManageStock()) {
            $configManageStock = Mage::getStoreConfigFlag(
                Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK,
                $storeId
            );
            $manageStock = $stockItem->getUseConfigManageStock() ? $configManageStock : $stockItem->getManageStock();
        } else {
            $manageStock = true;
        }

        $transformQtyIncrements = $this->getConfig()->getTransformQtyIncrements($product);
        $configQtyIncrements = $this->getConfig()->getQtyIncrements($product);

        $data = array(
            'id' => $product->getId(),
            'last-feed-update' => date('Y-m-d H:i:s'),
            'mage-sku' => $product->getSku(),
            'product-url' => $this->cleanUrl($product->getProductUrl(false)),
            'is-in-stock' => $manageStock ? $product->getStockItem()->getIsInStock() : 1,
            'salable' => (int) $product->isSalable(),
            'qty' => $product->isSalable() ? ($manageStock ? round($stockItem->getQty()) : 100) : 0,
            'qty-increments' => $transformQtyIncrements ? 1 : $configQtyIncrements,
            'tax-rate' => $product->getTaxPercent(),
        );

        if ($transformQtyIncrements) {
            $data['qty'] = $data['qty'] / $configQtyIncrements;
        }

        foreach ($this->getConfig()->getMappingAttributes($storeId) as $dataKey => $attributeCode) {
            $data[$dataKey] = $this->_getAttributeDataForProduct(
                $dataKey,
                $attributeCode,
                $product,
                $storeId
            );

            if ($transformQtyIncrements) {
                if ($dataKey === 'name') {
                    $data[$dataKey] = $data[$dataKey] . $this->getHelper()->__(' - Set of %d', $configQtyIncrements);
                } else if ($dataKey === 'sku') {
                    $data[$dataKey] = '_SFQI_' . $configQtyIncrements . '_' . $data[$dataKey];
                } else if ($dataKey === 'weight') {
                    $data[$dataKey] = $configQtyIncrements * $data[$dataKey];
                }
            }
        }

        $data = $this->_getPrices($data, $product, $storeId);
        $data = $this->getImages($data, $product, $storeId);
        $data = $this->_getCategories($data, $product, $storeId);
        $data = $this->_getShippingData($data, $product, $storeId);

        if ($this->getConfig()->getManageConfigurables()) {
            $data = $this->_getConfigurableAttributes($data, $product, $storeId);
        }

        foreach ($this->getConfig()->getAdditionalAttributes($storeId) as $attributeCode) {
            $data[$attributeCode] = $this->_getAttributeDataForProduct(
                $attributeCode,
                $attributeCode,
                $product,
                $storeId
            );
        }

        if (!isset($data['shipping_delay']) || empty($data['shipping_delay'])) {
            $data['shipping_delay'] = $this->getConfig()
                ->getConfigData('shoppingflux_export/general/default_shipping_delay');
        }

        if ($this->getConfig()->getEnableEvents()) {
            $dataObject = new Varien_Object(array('entry' => $data, 'store_id' => $storeId, 'product' => $product));
            Mage::dispatchEvent('shoppingflux_before_update_entry', array('data_object' => $dataObject));
            $entry = $dataObject->getData('entry');
        } else {
            $entry = $data;
        }

        $xml .= $xmlObject->addEntry($entry);
        $fluxEntry = $this->getEntry($product->getSku(), $storeId);

        $fluxEntry->addData(
            array(
                'updated_at' => date('Y-m-d H:i:s', $dateModel->timestamp(time())),
                'xml' => $xml,
                'update_needed' => 0,
                'product_id' => $product->getId(),
                'stock_value' => $stockItem->getQty(),
                'price_value' => $product->getFinalPrice(),
                'is_in_stock' => $data['is-in-stock'],
                'salable' => (int) $product->isSalable(),
                'is_in_flux' => (int) $product->getData('shoppingflux_product'),
                'type' => $product->getTypeId(),
                'visibility' => $product->getVisibility(),
                'should_export' => 1,
            )
        );

        $fluxEntry->save();
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    protected function _getPrices(array $data, $product, $storeId)
    {
        $priceAttributeCode = $this->getConfig()
            ->getConfigData('shoppingflux_export/attributes_mapping/price', $storeId);
        $specialPriceAttributeCode = $this->getConfig()
            ->getConfigData('shoppingflux_export/attributes_mapping/special_price', $storeId);

        if (!$product->getData($priceAttributeCode)) {
            $priceAttributeCode = 'price';
            $specialPriceAttributeCode = 'special_price';
        }

        $discountAmount = 0;
        $finalPrice = $product->getData($priceAttributeCode);
        $priceBeforeDiscount = $product->getData($priceAttributeCode);

        if (($product->getData($specialPriceAttributeCode) > 0)
            && ($product->getData($specialPriceAttributeCode) < $finalPrice)
        ) {
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
                Mage::dispatchEvent('catalog_product_get_final_price', array('product' => $product));
                $finalPrice = $product->getFinalPrice();
                $discountAmount = $priceBeforeDiscount - $finalPrice;
                $discountFromDate = '';
                $discountToDate = '';
            }
        } elseif ($this->getConfig()->getManageCatalogRules()) {
            /** @var Mage_catalogRule_Model_Rule $ruleModel */
            $ruleModel = Mage::getModel('catalogrule/rule');
            $catalogPriceRulePrice = $ruleModel->calcProductPriceRule($product, $product->getPrice());

            if (($catalogPriceRulePrice > 0) && ($catalogPriceRulePrice < $finalPrice)) {
                $finalPrice = $catalogPriceRulePrice;
                $discountAmount = $priceBeforeDiscount - $catalogPriceRulePrice;
                $discountFromDate = '';
                $discountToDate = '';
            }
        }

        if ($this->getConfig()->getTransformQtyIncrements($product)) {
            $qtyIncrements = $this->getConfig()->getQtyIncrements($product);
            $finalPrice *= $qtyIncrements;
            $priceBeforeDiscount *= $qtyIncrements;
        }

        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');

        $data['price-ttc'] = $taxHelper->getPrice($product, $finalPrice, true);
        $data['price-before-discount'] = $taxHelper->getPrice($product, $priceBeforeDiscount, true);
        $data['discount-amount'] = $product->getTypeId() != 'bundle' ? $discountAmount : 0;
        $data['discount-percent'] = $this->_getDiscountPercent($product);
        $data['start-date-discount'] = '';
        $data['end-date-discount'] = '';

        if ($discountFromDate) {
            $data['start-date-discount'] = $discountFromDate;
        }

        if ($discountToDate) {
            $data['end-date-discount'] = $discountToDate;
        }

        unset($data['price']);
        unset($data['special_price']);
        return $data;
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    protected function _getCategories(array $data, $product, $storeId)
    {
        if ($product->getData('shoppingflux_default_category')
            && ($product->getData('shoppingflux_default_category') > 0)
        ) {
            $data = $this->_getCategoriesViaShoppingfluxCategory($data, $product);
        } else {
            $data = $this->_getCategoriesViaProductCategories($data, $product);
        }

        $productId = $product->getId();

        if (!$data['category-breadcrumb']) {
            /** @var Mage_Bundle_Model_Product_Type $bundleModel */
            $bundleModel = Mage::getSingleton('bundle/product_type');
            /** @var Mage_Catalog_Model_Product_Type_Configurable $configurableModel */
            $configurableModel = Mage::getSingleton('catalog/product_type_configurable');
            /** @var Mage_Catalog_Model_Product_Type_Grouped $groupedModel */
            $groupedModel = Mage::getSingleton('catalog/product_type_grouped');

            $parentIds = array_unique(
                array_merge(
                    $bundleModel->getParentIdsByChild($productId),
                    $configurableModel->getParentIdsByChild($productId),
                    $groupedModel->getParentIdsByChild($productId)
                )
            );

            foreach ($parentIds as $parentId) {
                if (!$data['category-breadcrumb']
                    && ($parentProduct = $this->_getProduct($parentId, $storeId))
                    && $parentProduct->getId()
                ) {
                    $data = $this->_getCategories($data, $parentProduct, $storeId);
                }
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @param array $categories
     * @param int $categoryId
     * @param int $maxLevel
     * @return array
     */
    protected function _getCategoriesData(array $data, array $categories, $categoryId, $maxLevel = 5)
    {
        $names = explode(' > ', $categories['name'][$categoryId]);
        $metaTitles = explode(' > ', $categories['meta_title'][$categoryId]);
        $metaDescriptions = explode(' > ', $categories['meta_description'][$categoryId]);
        $metaKeywords = explode(' > ', $categories['meta_keywords'][$categoryId]);
        $urls = explode(' > ', $categories['url'][$categoryId]);

        // Drop the root category (useless here)
        array_shift($names);
        array_shift($metaTitles);
        array_shift($metaDescriptions);
        array_shift($metaKeywords);
        array_shift($urls);

        $data['category-breadcrumb'] = trim(implode(' > ', $names));
        $data['category-main'] = isset($names[0]) ? trim($names[0]) : '';
        $data['category-url-main'] = isset($urls[0]) ? $urls[0] : '';
        $data['category-metatitle-main'] = isset($metaTitles[0]) ? $metaTitles[0] : '';
        $data['category-metadescription-main'] = isset($metaDescriptions[0]) ? $metaDescriptions[0] : '';
        $data['category-metakeywords-main'] = isset($metaKeywords[0]) ? $metaKeywords[0] : '';

        for ($i = 1; $i <= $maxLevel; $i++) {
            $data['category-sub-' . $i] = isset($names[$i]) ? trim($names[$i]) : '';
            $data['category-url-sub-' . $i] = isset($urls[$i]) ? $urls[$i] : '';
            $data['category-metatitle-sub-' . $i] = isset($metaTitles[$i]) ? $metaTitles[$i] : '';
            $data['category-metadescription-sub-' . $i] = isset($metaDescriptions[$i]) ? $metaDescriptions[$i] : '';
            $data['category-metakeywords-sub-' . $i] = isset($metaKeywords[$i]) ? $metaKeywords[$i] : '';
        }

        return $data;
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getCategoriesViaShoppingfluxCategory(array $data, $product)
    {
        $categoryId = $product->getData('shoppingflux_default_category');

        if (!$categoryId && (!$categoryId = $product->getData('main_category'))) {
            return $this->_getCategoriesViaProductCategories($data, $product);
        }

        $categories = $this->getHelper()->getCategoriesWithParents(false, $product->getStoreId());

        if (!isset($categories['name'][$categoryId])) {
            return $this->_getCategoriesViaProductCategories($data, $product);
        }

        return $this->_getCategoriesData($data, $categories, $categoryId);
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _getCategoriesViaProductCategories(array $data, $product)
    {
        if (!$this->getConfig()->getUseOnlySFCategory()) {
            $categories = $this->getHelper()
                ->getCategoriesWithParents(
                    false,
                    $product->getStoreId(),
                    false,
                    false
                );

            $maxCategoryLevel = $this->getConfig()->getMaxCategoryLevel();

            if ($maxCategoryLevel <= 0) {
                $maxCategoryLevel = 5;
            }

            $categoryIds = $product->getCategoryIds();
            $chosenCategoryId = false;
            $chosenCategoryLevel = 0;

            foreach ($categoryIds as $categoryId) {
                if (isset($categories['name'][$categoryId])) {
                    $categoryNames = explode(' > ', $categories['name'][$categoryId]);
                    $categoryLevel = count($categoryNames);

                    if ($categoryLevel > $chosenCategoryLevel) {
                        $chosenCategoryId = $categoryId;
                        $chosenCategoryLevel = $categoryLevel;
                    }
                }
            }

            if ($chosenCategoryId) {
                $data = $this->_getCategoriesData($data, $categories, $chosenCategoryId, $maxCategoryLevel);
            }
        }

        if (!isset($data['category-main'])) {
            $data['category-breadcrumb'] = '';
            $data['category-main'] = '';
            $data['category-url-main'] = '';
        }

        for ($i = 1; $i <= 5; $i++) {
            if (!isset($data['category-sub-' . $i])) {
                $data['category-sub-' . $i] = '';
                $data['category-url-sub-' . $i] = '';
            }
        }

        return $data;
    }

    /**
     * @param string $url
     * @return string
     */
    public function cleanUrl($url)
    {
        return preg_replace('%(.*)\?.*$%i', '$1', str_replace('index.php/', '', $url));
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @param bool $checkParentIfNone
     * @return array
     */
    public function getImages(array $data, $product, $storeId, $checkParentIfNone = true)
    {
        $mediaUrl = Mage::getBaseUrl('media') . 'catalog/product';
        $exportCount = $this->getConfig()->getExportedImageCount();
        $exportedCount = 1;
        $exportedUrls = array();
        $baseImage = $product->getData('image');
        $baseImageUrl = $mediaUrl . $baseImage;

        if (!empty($baseImage) && ($baseImage !== 'no_selection')) {
            $data['image-url-' . $exportedCount] = $baseImageUrl;
            $data['image-label-' . $exportedCount] = $product->getData('image_label');
            $exportedUrls[] = $baseImageUrl;
            $exportedCount++;
        }

        if ($this->getConfig()->getManageMediaGallery()) {
            $mediaConfig = $product->getMediaConfig();
            $mediaGallery = $product->getResource()->getAttribute('media_gallery');
            $mediaGallery->getBackend()->afterLoad($product);

            foreach ($product->getMediaGallery('images') as $image) {
                $imageUrl = $mediaConfig->getMediaUrl($image['file']);

                if ($image['disabled'] || in_array($imageUrl, $exportedUrls, true)) {
                    continue;
                }

                $data['image-url-' . $exportedCount] = $imageUrl;
                $data['image-label-' . $exportedCount] = $image['label'];
                $exportedUrls[] = $imageUrl;
                $exportedCount++;

                if (($exportCount !== false) && ($exportedCount >= $exportCount)) {
                    break;
                }
            }
        }

        if ((!isset($data['image-url-1']) || !$data['image-url-1']) && $checkParentIfNone) {
            /** @var Mage_Catalog_Model_Resource_Product_Link $linkResource */
            $linkResource = Mage::getResourceSingleton('catalog/product_link');

            $groupedParentsIds = $linkResource->getParentIdsByChild(
                $product->getId(),
                Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED
            );

            $parentId = current($groupedParentsIds);
            $parentProduct = $this->_getProduct($parentId, $storeId);

            if ($parentProduct && $parentProduct->getId()) {
                return $this->getImages($data, $parentProduct, $storeId, false);
            }
        }

        return $data;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    protected function _getDiscountPercent($product)
    {
        $price = round($product->getPrice(), 2);

        if ($price == '0') {
            $price = round($product->getMinimalPrice(), 2);
        }

        if ($price == '0') {
            return 0;
        }

        $specialPrice = round($product->getFinalPrice(), 2);
        $discountAmount = ($price - $specialPrice) * 100 / $price;
        return round($discountAmount);
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    protected function _getShippingData(array $data, $product, $storeId)
    {
        $data['shipping-name'] = '';
        $data['shipping-price'] = '';

        $carrier = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_method');

        if (empty($carrier)) {
            $data['shipping-price'] = $this->getConfig()
                ->getConfigData('shoppingflux_export/general/default_shipping_price');
            return $data;
        }

        $carrierData = explode('_', $carrier);
        list(, $methodCode) = $carrierData;
        $data['shipping-name'] = ucfirst($methodCode);
        $shippingPrice = 0;

        if ($this->getConfig()->getConfigData('shoppingflux_export/general/try_use_real_shipping_price')) {
            $countryCode = $this->getConfig()->getConfigData('shoppingflux_export/general/shipping_price_based_on');
            $shippingPrice = $this->getHelper()->getShippingPrice($product, $carrier, $countryCode);
        }

        if (!$shippingPrice) {
            $shippingPrice = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_price');
        }

        $data['shipping-price'] = $shippingPrice;
        return $data;
    }

    /**
     * @param bool $checkIfExists
     * @param bool $withAdditional
     * @param int|null $storeId
     * @return array
     */
    protected function _getAttributesFromConfig($checkIfExists = false, $withAdditional = true, $storeId = null)
    {
        if (!isset($this->_attributesFromConfig[$storeId])) {
            $this->_attributesFromConfig[$storeId] = array();
            $attributes = $this->getConfig()->getMappingAttributes($storeId);

            if ($withAdditional) {
                $additionalAttributes = $this->getConfig()->getAdditionalAttributes($storeId);

                foreach ($additionalAttributes as $attributeCode) {
                    $attributes[$attributeCode] = trim($attributeCode);
                }
            }

            if ($checkIfExists) {
                foreach ($attributes as $key => $code) {
                    $attribute = $this->_getAttribute($code);

                    if (($attribute instanceof Mage_Catalog_Model_Resource_Eav_Attribute)
                        && $attribute->getId()
                        && ($attribute->getFrontendInput() !== 'weee')
                    ) {
                        $this->_attributesFromConfig[$storeId][$key] = $code;
                    }
                }
            } else {
                $this->_attributesFromConfig[$storeId] = $attributes;
            }
        }

        return $this->_attributesFromConfig[$storeId];
    }

    /**
     * @param array $data
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    protected function _getConfigurableAttributes(array $data, $product, $storeId)
    {
        $data['configurable_attributes'] = '';
        $data['childs_product'] = '';
        $images = array();
        $helper = $this->getHelper();

        if ($product->getTypeId() == 'configurable') {
            $attributes = $helper->getConfigurableAttributes($product);
            $attributesToOptions = array();
            $attributesFromConfig = $this->_getAttributesFromConfig(true, true, $storeId);

            foreach ($attributes as $attribute) {
                $attributesToOptions[$attribute['attribute_code']] = array();
            }

            $hasSimpleConfigurables = $helper->isModuleInstalled('DerModPro_BCP')
                || $helper->isModuleInstalled('OrganicInternet_SimpleConfigurableProducts');

            /** @var Mage_Catalog_Model_Product_Type_Configurable $configurableType */
            $configurableType = $product->getTypeInstance(true);
            $configurableAttributes = $configurableType->getConfigurableAttributesAsArray($product);
            $usedProducts = $configurableType->getUsedProductCollection($product);
            $usedProductsArray = array();
            $isParentSalable = false;

            /** @var Mage_Core_Model_Resource $resource */
            $resource = Mage::getSingleton('core/resource');
            $connection = $resource->getConnection('core_read');

            // Circumvent a problem with old Magento versions
            foreach ($connection->fetchAll($usedProducts->getSelect()) as $usedProduct) {
                $usedProduct = $this->_getProduct($usedProduct['entity_id'], $storeId);

                if (!$usedProduct
                    || ($usedProduct->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED)
                ) {
                    continue;
                }

                $isParentSalable = $isParentSalable || $usedProduct->isSalable();

                if ($hasSimpleConfigurables) {
                    $priceData = $this->_getPrices(array(), $usedProduct, $storeId);
                    $price = $priceData['price-ttc'] > 0 ? $priceData['price-ttc'] : $data['price-ttc'];

                    if ($data['price-ttc'] <= 0 || ($price > 0 && $price < $data['price-ttc'])) {
                        $data['price-ttc'] = $price;
                    }

                    $priceBeforeDiscount = $priceData['price-before-discount'];
                    $discountAmount = $priceData['discount-amount'];
                    $startDateDiscount = $priceData['start-date-discount'];
                    $endDateDiscount = $priceData['end-date-discount'];
                } else {
                    $price = $data['price-ttc'];
                    $priceBeforeDiscount = $data['price-before-discount'];
                    $discountAmount = $data['discount-amount'];
                    $startDateDiscount = $data['start-date-discount'];
                    $endDateDiscount = $data['end-date-discount'];

                    foreach ($configurableAttributes as $configurableAttribute) {
                        $attributeCode = $configurableAttribute['attribute_code'];

                        foreach ($configurableAttribute['values'] as $value) {
                            if ($value['pricing_value']
                                && ($usedProduct->getData($attributeCode) == $value['value_index'])
                            ) {
                                if ($value['is_percent']) {
                                    $price += $data['price-ttc'] * $value['pricing_value'] / 100;
                                    $beforeDiscount = $data['price-before-discount'] * $value['pricing_value'] / 100;
                                    $priceBeforeDiscount += $beforeDiscount;
                                } else {
                                    $price += $value['pricing_value'];
                                    $priceBeforeDiscount += $value['pricing_value'];
                                }
                            }
                        }
                    }
                }

                $discountPercent = 0;

                if ($priceBeforeDiscount) {
                    $discountPercent = round(($priceBeforeDiscount - $price) * 100 / $priceBeforeDiscount);
                }

                $isInStock = 0;
                $qty = 0;

                /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
                if ($stockItem = $usedProduct->getStockItem()) {
                    if ($this->getConfig()->useManageStock()) {
                        $configManageStock = Mage::getStoreConfigFlag(
                            Mage_CatalogInventory_Model_Stock_Item::XML_PATH_MANAGE_STOCK,
                            $storeId
                        );

                        $manageStock = $stockItem->getUseConfigManageStock()
                            ? $configManageStock
                            : $stockItem->getManageStock();
                    } else {
                        $manageStock = true;
                    }

                    $isInStock = $manageStock ? $stockItem->getIsInStock() : 1;

                    if ($usedProduct->isSalable()) {
                        $qty = $manageStock ? $stockItem->getQty() : 100;
                    } else {
                        $qty = 0;
                    }
                }

                $usedProductId = $usedProduct->getId();

                $usedProductsArray[$usedProductId]['child'] = array(
                    'id' => $usedProductId,
                    'sku' => $usedProduct->getSku(),
                    'ean' => '',
                    'child-url' => $this->cleanUrl($usedProduct->getProductUrl(false)),
                    'price-ttc' => $price,
                    'price-before-discount' => $priceBeforeDiscount,
                    'discount-amount' => $discountAmount,
                    'discount-percent' => $discountPercent,
                    'start-date-discount' => $startDateDiscount,
                    'end-date-discount' => $endDateDiscount,
                    'is-in-stock' => $isInStock,
                    'qty' => round($qty),
                    'tax-rate' => $usedProduct->getTaxPercent(),
                );

                if (isset($attributesFromConfig['ean'])) {
                    $eanValue = $usedProduct->getData($attributesFromConfig['ean']);
                    $usedProductsArray[$usedProductId]['child']['ean'] = $eanValue;
                }

                if (!$data['tax-rate'] && $usedProductsArray[$usedProductId]['child']['tax-rate']) {
                    $data['tax-rate'] = $usedProductsArray[$usedProductId]['child']['tax-rate'];
                }

                if (($qty > 0) && ($qty > $data['qty'])) {
                    $data['qty'] = round($qty);
                }

                $images = $this->getImages($images, $usedProduct, $storeId, false);

                if (!$images['image-url-1']) {
                    $images = $this->getImages($images, $product, $storeId);
                }

                foreach ($images as $key => $value) {
                    $usedProductsArray[$usedProductId]['child'][$key] = trim($value);
                }

                foreach ($attributesFromConfig as $dataKey => $attributeCode) {
                    if ($attributeCode) {
                        $usedProductsArray[$usedProductId]['child'][$dataKey] = $this->_getAttributeDataForProduct(
                            $dataKey,
                            $attributeCode,
                            $usedProduct,
                            $storeId
                        );
                    }
                }

                foreach ($attributes as $attribute) {
                    $attributeCode = $attribute['attribute_code'];
                    $attributeId = $attribute['attribute_id'];

                    if (!isset($this->_attributesConfigurable[$attributeId])) {
                        $this->_attributesConfigurable[$attributeId] = $product->getResource()
                            ->getAttribute($attributeId);
                    }

                    $attributeModel = $this->_attributesConfigurable[$attributeId];
                    $value = '';

                    if ($usedProduct->getData($attributeCode)) {
                        $value = $attributeModel->getFrontend()->getValue($usedProduct);
                    }

                    if (!isset($attributesToOptions[$attributeCode])
                        || !in_array($value, $attributesToOptions[$attributeCode])
                    ) {
                        $attributesToOptions[$attributeCode][] = $value;
                    }

                    $usedProductsArray[$usedProductId]['child'][$attributeCode] = trim($value);
                }


                if (!isset($usedProductsArray[$usedProductId]['child']['shipping_delay'])
                    || !$usedProductsArray[$usedProductId]['child']['shipping_delay']
                ) {
                    $usedProductsArray[$usedProductId]['child']['shipping_delay'] = $this->getConfig()
                        ->getConfigData('shoppingflux_export/general/default_shipping_delay');
                }

                unset($usedProductsArray[$usedProductId]['child']['price']);
                unset($usedProductsArray[$usedProductId]['child']['special_price']);
            }

            $data['is-in-stock'] = (int) $isParentSalable;

            foreach ($attributesToOptions as $attributeCode => $value) {
                $data['configurable_attributes'][$attributeCode] = implode(',', $value);
            }

            $data['childs_product'] = $usedProductsArray;
            unset($usedProducts);
            unset($usedProductsArray);
        }

        return $data;
    }
}
