<?php

class Profileolabs_Shoppingflux_Block_Export_Flux extends Mage_Core_Block_Abstract
{
    /** @var array $productNodes */
    protected $productNodes = array();

    protected function _loadCache()
    {
        return false;
    }

    protected function _saveCache($data)
    {
        return $this;
    }

    /**
     * @return Profileolabs_Shoppingflux_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    protected function _toHtml()
    {
        Profileolabs_Shoppingflux_Model_Export_Observer::checkStock();
        $useAllStores = $this->getForceMultiStores() || $this->getConfig()->getUseAllStoreProducts();

        /** @var Profileolabs_Shoppingflux_Model_Export_Flux $fluxModel */
        $fluxModel = Mage::getModel('profileolabs_shoppingflux/export_flux');

        if ($this->getProductSku() && ($this->getRequest()->getParam('update') == 1)) {
            if (!$this->getConfig()->getUseAllStoreProducts()) {
                $fluxModel->updateProductInFlux(
                    $this->getProductSku(),
                    Mage::app()->getStore()->getId()
                );
            } else {
                $fluxModel->updateProductInFluxForAllStores($this->getProductSku());
            }
        }

        $maxImportLimit = 1000;
        $memoryLimit = ini_get('memory_limit');

        if (preg_match('%M$%', $memoryLimit)) {
            $memoryLimit = (int) $memoryLimit * 1024 * 1024;
        } else if (preg_match('%G$%', $memoryLimit)) {
            $memoryLimit = (int) $memoryLimit * 1024 * 1024 * 1024;
        } else {
            $memoryLimit = false;
        }

        if ($memoryLimit > 0) {
            if ($memoryLimit <= 128 * 1024 * 1024) {
                $maxImportLimit = 100;
            } else if ($memoryLimit <= 256 * 1024 * 1024) {
                $maxImportLimit = 500;
            } else if ($memoryLimit >= 1024 * 1024 * 1024) {
                $maxImportLimit = 3000;
            } else if ($memoryLimit >= 2048 * 1024 * 1024) {
                $maxImportLimit = 6000;
            }
        }

        $fluxModel->updateFlux(
            $useAllStores ? false : Mage::app()->getStore()->getId(),
            $this->getLimit() ? $this->getLimit() : $maxImportLimit
        );

        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/export_flux_collection');
        $collection->addFieldToFilter('should_export', 1);
        $withNotSalableRetention = $this->getConfig()->isNotSalableRetentionEnabled();

        if ($useAllStores) {
            $collection->getSelect()->group(array('sku'));
        } else {
            $collection->addFieldToFilter('store_id', Mage::app()->getStore()->getId());
        }

        $totalSize = $collection->getSize();
        $collection->clear();

        if (!$this->getConfig()->isExportNotSalable() && !$withNotSalableRetention) {
            $collection->addFieldToFilter('salable', 1);
        }

        if (!$this->getConfig()->isExportSoldout() && !$withNotSalableRetention) {
            $collection->addFieldToFilter('is_in_stock', 1);
        }

        if ($this->getConfig()->isExportFilteredByAttribute()) {
            $collection->addFieldToFilter('is_in_flux', 1);
        }

        $visibilities = $this->getConfig()->getVisibilitiesToExport();
        $visibilities = array_filter($visibilities);
        $collection->getSelect()->where('FIND_IN_SET(visibility, ?)', implode(',', $visibilities));


        /** @var Profileolabs_Shoppingflux_Model_Export_Xml $xmlObject */
        $xmlObject = Mage::getModel('profileolabs_shoppingflux/export_xml');

        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');

        $fluxHeader = $xmlObject->startXml(
            array(
                'store_id' => Mage::app()->getStore()->getId(),
                'generated-at' => date('d/m/Y H:i:s', $dateModel->timestamp(time())),
                'size-exportable' => $totalSize,
                'size-xml' => $collection->count(),
                'with-out-of-stock' => (int) $this->getConfig()->isExportSoldout(),
                'with-not-salable' => (int) $this->getConfig()->isExportNotSalable(),
                'selected-only' => (int) $this->getConfig()->isExportFilteredByAttribute(),
                'visibilities' => implode(',', $visibilities)
            )
        );

        if ($this->getProductSku()) {
            $collection->addFieldToFilter('sku', $this->getProductSku());
        }

        if ($this->getLimit()) {
            $collection->getSelect()->limit($this->getLimit());
        }

        /** @var Mage_Core_Model_Resource_Iterator $iterator */
        $iterator = Mage::getSingleton('core/resource_iterator');
        $iterator->walk($collection->getSelect(), array(array($this, 'appendProductNode')), array());

        return $fluxHeader . implode('', $this->productNodes) . $xmlObject->endXml();
    }

    /**
     * @param array $args
     */
    public function appendProductNode(array $args)
    {
        $this->productNodes[] = $args['row']['xml'];
    }
}
