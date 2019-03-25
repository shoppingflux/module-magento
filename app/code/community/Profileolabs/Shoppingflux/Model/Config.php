<?php

class Profileolabs_Shoppingflux_Model_Config extends Varien_Object
{
    /**
     * @param string $key
     * @param int|null $storeId
     * @return mixed
     */
    public function getConfigData($key, $storeId = null)
    {
        $dataKey = str_replace('/', '_', 'conf/' . $key . '/' . (($storeId === null) ? '0' : $storeId));

        if (!$this->hasData($dataKey)) {
            $value = Mage::getStoreConfig($key, $storeId);
            $this->setData($dataKey, $value);
        }

        return $this->getData($dataKey);
    }

    /**
     * @param string $key
     * @param int|null $storeId
     * @return bool
     */
    public function getConfigFlag($key, $storeId = null)
    {
        $dataKey = str_replace('/', '_', 'flag/' . $key . '/' . (($storeId === null) ? '0' : $storeId));

        if (!$this->hasData($dataKey)) {
            $value = Mage::getStoreConfigFlag($key, $storeId);
            $this->setData($dataKey, $value);
        }

        return $this->getData($dataKey);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return trim($this->getConfigData('shoppingflux/configuration/api_key', $storeId));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getIdClient($storeId = null)
    {
        return trim($this->getConfigData('shoppingflux/configuration/login', $storeId));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getIdTracking($storeId = null)
    {
        return trim($this->getConfigData('shoppingflux/configuration/id_tracking', $storeId));
    }

    /**
     * @return bool
     */
    public function isSandbox()
    {
        return $this->getConfigFlag('shoppingflux/configuration/is_sandbox');
    }

    /**
     * @return string
     */
    public function getWsUri()
    {
        return $this->isSandbox()
            ? trim($this->getConfigData('shoppingflux/configuration/ws_uri_sandbox'))
            : trim($this->getConfigData('shoppingflux/configuration/ws_uri_prod'));
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isExportEnabled($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/active', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isExportFilteredByAttribute($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/filter_by_attribute', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isExportSoldout($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/export_soldout', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isExportNotSalable($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/export_not_salable', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return int|false
     */
    public function getNotSalableRetentionDuration($storeId = null)
    {
        $seconds = 0;

        if (!$this->isExportSoldout($storeId) && !$this->isExportNotSalable($storeId)) {
            if ($this->getConfigFlag('shoppingflux_export/general/enable_not_salable_retention', $storeId)) {
                $hours = $this->getConfigData('shoppingflux_export/general/not_salable_retention_duration', $storeId);
                $seconds = max(0, min((int) trim($hours), 168)) * 3600;
            }
        }

        return ($seconds > 0 ? $seconds : false);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isNotSalableRetentionEnabled($storeId = null)
    {
        return ($this->getNotSalableRetentionDuration($storeId) !== false);
    }

    /**
     * @param int|null $storeId
     * @return int[]
     */
    public function getVisibilitiesToExport($storeId = null)
    {
        return array_filter(
            array_map(
                'intval',
                explode(',', $this->getConfigData('shoppingflux_export/general/export_visibility', $storeId))
            )
        );
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getExportProductLimit($storeId = null)
    {
        return (int) $this->getConfigData('shoppingflux_export/general/limit_product', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getUseAllStoreCategories($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/category/all_store_categories', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getUseAllStoreProducts($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/all_store_products', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getUseOnlySFCategory($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/category/use_only_shoppingflux_category', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return mixed
     */
    public function getMaxCategoryLevel($storeId = null)
    {
        return $this->getConfigData('shoppingflux_export/category/max_category_level', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getEnableEvents($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/enable_events', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getManageConfigurables($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/manage_configurable', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getManageCatalogRules($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/manage_catalog_rules', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getManageMediaGallery($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/manage_media_gallery', $storeId);
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return int
     */
    public function getQtyIncrements(Mage_Catalog_Model_Product $product)
    {
        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = $product->getStockItem();
        return $stockItem->getData('enable_qty_increments') ? max(1, (int) $stockItem->getData('qty_increments')) : 1;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @param int|null $storeId
     * @return bool
     */
    public function getTransformQtyIncrements(Mage_Catalog_Model_Product $product, $storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/transform_qty_increments', $storeId)
            ? ($this->getQtyIncrements($product) > 1)
            : false;
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getMappingAttributes($storeId = null)
    {
        $data = $this->getConfigData('shoppingflux_export/attributes_mapping', $storeId);

        if (isset($data['additional'])) {
            unset($data['additional']);
        }

        if (isset($data['additional,'])) {
            unset($data['additional,']);
        }

        return $data;
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getAdditionalAttributes($storeId = null)
    {
        $additional = $this->getConfigData('shoppingflux_export/attributes_mapping/additional', $storeId);
        $additional = array_filter(explode(',', $additional));
        $additional = array_diff($additional, $this->getMappingAttributes($storeId));
        return $additional;
    }

    /**
     * @return int
     */
    public function getMemoryLimit()
    {
        $memoryLimit = (int) $this->getConfigData('shoppingflux_export/general/memory_limit');
        return ($memoryLimit > 10 ? $memoryLimit : 2048);
    }

    /**
     * @return bool
     */
    public function isSyncEnabled()
    {
        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores() as $store) {
            if ($this->getConfigFlag('shoppingflux_export/general/enable_sync', $store->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function useManageStock($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_export/general/use_manage_stock', $storeId);
    }

    /**
     * @param null $storeId
     * @return int|false
     */
    public function getExportedImageCount($storeId = null)
    {
        $exportAll = $this->getConfigFlag('shoppingflux_export/general/export_all_images', $storeId);
        $count = (int) $this->getConfigData('shoppingflux_export/general/exported_image_count', $storeId);
        return (!$exportAll && ($count > 0) ? $count : false);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isOrdersEnabled($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_mo/manageorders/enabled', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return int
     */
    public function getLimitOrders($storeId = null)
    {
        $limit = (int) $this->getConfigData('shoppingflux_mo/manageorders/limit_orders', $storeId);
        return ($limit < 1 ? 10 : $limit);
    }

    /**
     * @param int|null $storeId
     * @return int|false
     */
    public function getAddressLengthLimit($storeId = null)
    {
        $limit = (int) $this->getConfigData('shoppingflux_mo/import_customer/limit_address_length', $storeId);
        return ($limit < 20 ? false : $limit);
    }

    /**
     * @param string|false $marketplace
     * @param int|null $storeId
     * @return int|false
     */
    public function getCustomerGroupIdFor($marketplace = false, $storeId = null)
    {
        if ($marketplace) {
            $marketplace = strtolower($marketplace);
            $groupId = $this->getConfigData('shoppingflux_mo/import_customer/' . $marketplace . '_group', $storeId);

            if ($groupId) {
                return (int) $groupId;
            }
        }

        $groupId = $this->getConfigData('shoppingflux_mo/import_customer/default_group', $storeId);
        return ($groupId ? (int) $groupId : false);
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    protected function _getDefaultShippingMethod($storeId = null)
    {
        return trim(Mage::getStoreConfig('shoppingflux_mo/shipping_method/default_method', $storeId));
    }

    /**
     * @param string $marketplace
     * @param int|null $storeId
     * @return string
     */
    protected function _getMarketplaceShippingMethod($marketplace, $storeId = null)
    {
        return trim(Mage::getStoreConfig('shoppingflux_mo/shipping_method/' . $marketplace . '_method', $storeId));
    }

    /**
     * @param string $code
     * @param int|null $storeId
     * @return string
     */
    protected function _getSfShippingMethod($code, $storeId = null)
    {
        return trim(Mage::getStoreConfig('shoppingflux_mo/advanced_shipping_method/' . $code, $storeId));
    }

    /**
     * @param string|false $marketplace
     * @param string|false $sfShippingMethod
     * @param int|null $storeId
     * @return string
     */
    public function getShippingMethodFor($marketplace = false, $sfShippingMethod = false, $storeId = null)
    {
        $defaultMethod = $this->_getDefaultShippingMethod();

        if (!$marketplace) {
            return $defaultMethod;
        }

        $marketplace = strtolower($marketplace);
        $marketplaceMethod = $this->_getMarketplaceShippingMethod($marketplace, $storeId);

        if (!$sfShippingMethod) {
            return $marketplaceMethod ? $marketplaceMethod : $defaultMethod;
        }

        /** @var Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Method $shippingMethodModel */
        $shippingMethodModel = Mage::getModel('profileolabs_shoppingflux/manageorders_shipping_method');
        $sfMethodCode = $shippingMethodModel->getFullShippingMethodCodeFor($marketplace, $sfShippingMethod);
        $sfMethodCode = preg_replace('%[^a-zA-Z0-9_]%', '', $sfMethodCode);
        $sfMethod = $this->_getSfShippingMethod($sfMethodCode);

        return ($sfMethod ? $sfMethod : ($marketplaceMethod ? $marketplaceMethod : $defaultMethod));
    }

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getShipmentUpdateLimit($storeId = null)
    {
        $hoursLimit = (int) Mage::getStoreConfig('shoppingflux_mo/shipment_update/limit_hours', $storeId);
        return $hoursLimit
            ? date('Y-m-d H:i:s', strtotime('-' . $hoursLimit . ' hours'))
            : date('Y-m-d H:i:s', strtotime('-20 minutes'));
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function createInvoice($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_mo/manageorders/create_invoice', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function applyTax($storeId = null)
    {
        return true;
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function preferMobilePhone($storeId = null)
    {
        return $this->getConfigFlag('shoppingflux_mo/import_customer/prefer_mobile_phone', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function getMobilePhoneAttribute($storeId = null)
    {
        return $this->getConfigData('shoppingflux_mo/import_customer/mobile_attribute', $storeId);
    }

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getGsaCarrierMapping($storeId = null)
    {
        $dataKey = 'conf_gsa_carrier_mapping_' . (($storeId === null) ? '0' : $storeId);

        if (!$this->hasData($dataKey)) {
            /** @var Profileolabs_Shoppingflux_Helper_Sales $salesHelper */
            $salesHelper = Mage::helper('profileolabs_shoppingflux/sales');
            $trackableCarrierHash = $salesHelper->getTrackableCarriersOptionHash($storeId);
            $gsaCarrierMapping = array();

            foreach ($trackableCarrierHash as $mageCarrierCode => $title) {
                $gsaCarrierCode = trim(
                    $this->getConfigData('shoppingflux_mo/gsa_carrier_mapping/' . $mageCarrierCode, $storeId)
                );

                if ('' !== $gsaCarrierCode) {
                    $gsaCarrierMapping[$mageCarrierCode] = $gsaCarrierCode;
                }
            }

            $this->setData($dataKey, $gsaCarrierMapping);
        }

        return $this->_getData($dataKey);
    }

    /**
     * @param string $mageCarrierCode
     * @param int|null $storeId
     * @return string|null
     */
    public function getMappedGsaCarrierCodeFor($mageCarrierCode, $storeId = null)
    {
        $mapping = $this->getGsaCarrierMapping($storeId);
        return isset($mapping[$mageCarrierCode]) ? $mapping[$mageCarrierCode] : null;
    }
}
