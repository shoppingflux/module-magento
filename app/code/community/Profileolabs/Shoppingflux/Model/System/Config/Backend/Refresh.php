<?php

/**
 * @deprecated
 * Shopping Flux Backend Model for Config Api key
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Model_System_Config_Backend_Refresh extends Mage_Core_Model_Config_Data {

    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    protected function _afterSave() {
        try {
            if ($this->isValueChanged()) {

                $storeIds = array();

                $storeId = Mage::app()->getStore($this->getStoreCode())->getId();

                try {
                    $websiteId = Mage::app()->getWebsite($this->getWebsiteCode())->getId();
                } catch (Mage_Core_Exception $e) {
                    $websiteId = 0;
                }

                if ($storeId != 0) {
                    $storeIds[] = $storeId;
                } else if ($websiteId != 0) {
                    $stores = Mage::app()->getWebsite($this->getWebsiteCode())->getStores();
                    foreach ($stores as $strId => $str) {
                        $storeIds[] = $strId;
                    }
                } else {
                    $stores = Mage::app()->getStores();
                    foreach ($stores as $strId => $str) {
                        $storeIds[] = $strId;
                    }
                }

                $write = Mage::getSingleton('core/resource')->getConnection('core_write');
                foreach ($storeIds as $storeId) {
                    $write->beginTransaction();
                    try {
                        $query = "update " . Mage::getSingleton('core/resource')->getTableName('profileolabs_shoppingflux/export_flux') . " set update_needed = 1 where store_id = '" . $storeId . "'";
                        $write->query($query);
                        $write->commit();
                    } catch (Exception $e) {
                        $write->rollback();
                    }
                }
            }
        } catch (Exception $e) {
            
        }
    }

}