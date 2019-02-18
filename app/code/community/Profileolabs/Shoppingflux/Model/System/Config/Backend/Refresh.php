<?php

class Profileolabs_Shoppingflux_Model_System_Config_Backend_Refresh extends Mage_Core_Model_Config_Data
{
    public function getConfig()
    {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    protected function _afterSave()
    {
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
                    $storeIds = array_keys($stores);
                } else {
                    $stores = Mage::app()->getStores();
                    $storeIds = array_keys($stores);
                }

                if (!empty($storeIds)) {
                    /** @var Mage_Core_Model_Resource $resource */
                    $resource = Mage::getSingleton('core/resource');
                    $write = $resource->getConnection('core_write');
                    $fluxTable = $resource->getTableName('profileolabs_shoppingflux/export_flux');

                    $write->beginTransaction();

                    try {
                        $write->update(
                            $fluxTable,
                            array('update_needed' => 1),
                            $write->quoteInto('store_id IN(?)', $storeIds)
                        );

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
