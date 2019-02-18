<?php

class Profileolabs_Shoppingflux_Export_FluxController extends Mage_Core_Controller_Front_Action
{
    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @return Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux
     */
    protected function _getFluxResource()
    {
        return Mage::getResourceModel('profileolabs_shoppingflux/export_flux');
    }

    public function refreshAllAction()
    {
        $storeId = Mage::app()->getStore()->getId();
        $this->_getFluxResource()->markExportableAsUpdatable($storeId);
        $this->getResponse()->setBody($this->_getHelper()->__('The cache of this feed has been purged'));
    }

    public function refreshAllAllStoresAction()
    {
        $this->_getFluxResource()->markExportableAsUpdatable();
        $this->getResponse()->setBody($this->_getHelper()->__('The cache of this feed has been purged'));
    }

    public function refreshEverythingAction()
    {
        $storeId = Mage::app()->getStore()->getId();
        $this->_getFluxResource()->markAllAsUpdatable($storeId);
        $this->getResponse()->setBody($this->_getHelper()->__('The cache of this feed has been purged'));
    }

    public function refreshEverythingAllStoresAction()
    {
        $this->_getFluxResource()->markAllAsUpdatable();
        $this->getResponse()->setBody($this->_getHelper()->__('The cache of this feed has been purged'));
    }

    public function statusAction()
    {
        ini_set('display_errors', 1);
        error_reporting(-1);

        $response = $this->getResponse();
        $storeId = Mage::app()->getStore()->getId();

        /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
        $productCollection = Mage::getResourceModel('catalog/product_collection');
        $productCollection->addStoreFilter($storeId);
        $productCollection->setStoreId($storeId);
        $productCount = $productCollection->getSize();

        $fluxState = $this->_getFluxResource()->getStoreState($storeId);

        if ($response->canSendHeaders()) {
            $response->setHeader('Content-type', 'text/xml; charset=UTF-8', true);
        }

        $response->setBody(
            '<status version="' . $this->_getHelper()->getModuleVersion() . '" m="' . ini_get('memory_limit') . '">'
            . '<feed_generation>'
            . '<product_count>' . $productCount . '</product_count>'
            . '<feed_count>' . $fluxState['total'] . '</feed_count>'
            . '<feed_not_export_count>' . $fluxState['total_not_exportable'] . '</feed_not_export_count>'
            . '<feed_export_count>' . $fluxState['total_exportable'] . '</feed_export_count>'
            . '<feed_update_needed_count>' . $fluxState['total_updatable'] . '</feed_update_needed_count>'
            . '</feed_generation>'
            . '</status>'
        );
    }

    public function indexAction()
    {
        Mage::register('export_feed_start_at', microtime(true));

        /** @var Profileolabs_Shoppingflux_Model_Config $config */
        $config = Mage::getSingleton('profileolabs_shoppingflux/config');

        error_reporting(-1);
        ini_set('display_errors', 1);
        set_time_limit(0);
        ini_set('memory_limit', $config->getMemoryLimit() . 'M');

        $response = $this->getResponse();
        $limit = $this->getRequest()->getParam('limit');
        $productSku = $this->getRequest()->getParam('product_sku');
        $forceMultiStore = $this->getRequest()->getParam('force_multi_stores', false);
        $forceStore = $this->getRequest()->getParam('force_store', false);

        if ($forceStore) {
            /** @var Mage_Core_Model_App_Emulation $appEmulation */
            $appEmulation = Mage::getSingleton('core/app_emulation');

            if ($appEmulation) { // not available in 1.4
                $appEmulation->startEnvironmentEmulation($forceStore);
            }
        }

        if ($response->canSendHeaders()) {
            $response->setHeader('Content-type', 'text/xml; charset=UTF-8', true);
        }

        /** @var Profileolabs_Shoppingflux_Block_Export_Flux $block */
        $block = $this->getLayout()->createBlock('profileolabs_shoppingflux/export_flux', 'sf.export.flux');

        if ($limit) {
            $block->setLimit($limit);
        }

        if ($productSku) {
            $block->setProductSku($productSku);
        }

        if ($forceMultiStore) {
            $block->setForceMultiStores(true);
        }

        try {
            $this->getResponse()->setBody($block->toHtml());
        } catch (Profileolabs_ShoppingFlux_Model_Export_Flux_Exception $e) {
            $response = $this->getResponse();

            if ($response->canSendHeaders()) {
                $response->setHeader('Refresh', '0');
            }

            $response->setBody($e->getMessage());
        }
    }
}
