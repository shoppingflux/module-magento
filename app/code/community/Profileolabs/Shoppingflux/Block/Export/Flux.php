<?php

class Profileolabs_Shoppingflux_Block_Export_Flux extends Mage_Core_Block_Abstract {

    protected function _loadCache() {
	return false;	
    }

    protected function _saveCache($data)
    {
        return;	
    }

    protected function _toHtml() {
       $this->displayOutput();
       return '';
    }
        
    
    public function displayOutput() {
        Profileolabs_Shoppingflux_Model_Export_Observer::checkStock();
        
        $useAllStores = $this->getForceMultiStores() || $this->getConfig()->getUseAllStoreProducts();
        if ($this->getProductSku() && $this->getRequest()->getParam('update') == 1) {
            if(!$this->getConfig()->getUseAllStoreProducts()) {
                Mage::getModel('profileolabs_shoppingflux/export_flux')->updateProductInFlux($this->getProductSku(), Mage::app()->getStore()->getId());
            } else {
                Mage::getModel('profileolabs_shoppingflux/export_flux')->updateProductInFluxForAllStores($this->getProductSku());
            }
        }
        
        $maxImportLimit = 1000;
        $memoryLimit = ini_get('memory_limit');
        if (preg_match('%M$%', $memoryLimit)) {
            $memoryLimit = intval($memoryLimit) * 1024 * 1024;
        } else if (preg_match('%G$%', $memoryLimit)) {
            $memoryLimit = intval($memoryLimit) * 1024 * 1024 * 1024;
        } else {
            $memoryLimit = false;
        }
        if($memoryLimit > 0) {
            if($memoryLimit <= 128 * 1024 * 1024) {
                $maxImportLimit = 100;
            } else if($memoryLimit <= 256 * 1024 * 1024) {
                $maxImportLimit = 500;
            } else if($memoryLimit >= 1024 * 1024 * 1024) {
                $maxImportLimit = 3000;
            }else if($memoryLimit >= 2048 * 1024 * 1024) {
                $maxImportLimit = 6000;
            }
        }
        
        Mage::getModel('profileolabs_shoppingflux/export_flux')->updateFlux($useAllStores?false:Mage::app()->getStore()->getId(), $this->getLimit() ? $this->getLimit() : $maxImportLimit);
        $collection = Mage::getModel('profileolabs_shoppingflux/export_flux')->getCollection();
        $collection->addFieldToFilter('should_export', 1);
        $withNotSalableRetention = $this->getConfig()->isNotSalableRetentionEnabled();
        
        if($useAllStores) {
            $collection->getSelect()->group(array('sku'));
        } else {
            $collection->addFieldToFilter('store_id', Mage::app()->getStore()->getId());
        }
        $sizeTotal = $collection->count();
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
        $collection->getSelect()->where("find_in_set(visibility, '" . implode(',', $visibilities) . "')");


        $xmlObj = Mage::getModel('profileolabs_shoppingflux/export_xml');
        echo $xmlObj->startXml(array('store_id'=>Mage::app()->getStore()->getId(),'generated-at' => date('d/m/Y H:i:s', Mage::getModel('core/date')->timestamp(time())), 'size-exportable' => $sizeTotal, 'size-xml' => $collection->count(), 'with-out-of-stock' => intval($this->getConfig()->isExportSoldout()), 'with-not-salable'=> intval($this->getConfig()->isExportNotSalable())  , 'selected-only' => intval($this->getConfig()->isExportFilteredByAttribute()), 'visibilities' => implode(',', $visibilities)));


        if ($this->getProductSku()) {
            $collection->addFieldToFilter('sku', $this->getProductSku());
        }
        if ($this->getLimit()) {
            $collection->getSelect()->limit($this->getLimit());
        }


        Mage::getSingleton('core/resource_iterator')
                ->walk($collection->getSelect(), array(array($this, 'displayProductXml')), array());
        echo $xmlObj->endXml();
        return;
    }

    public function displayProductXml($args) {
        if (Mage::app()->getRequest()->getActionName() == 'profile') {
            Mage::getModel('profileolabs_shoppingflux/export_flux')->updateProductInFlux($args['row']['sku'], Mage::app()->getStore()->getId());
        }
        echo $args['row']['xml'];
    }

    /**
     * @return Profileolabs_Shoppingflux_Model_Config
     */
    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

}
