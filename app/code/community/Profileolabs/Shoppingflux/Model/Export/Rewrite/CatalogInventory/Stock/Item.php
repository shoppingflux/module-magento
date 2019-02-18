<?php

if (Mage::helper('core')->isModuleEnabled('MDN_AdvancedStock')
    && class_exists('MDN_AdvancedStock_Model_CatalogInventory_Stock_Item')
) {
    include_once __DIR__ . '/Item/Compatibility/AdvancedStock.php';
} else {
    include_once __DIR__ . '/Item/Compatibility/Default.php';
}

class Profileolabs_Shoppingflux_Model_Export_Rewrite_CatalogInventory_Stock_Item extends Profileolabs_Shoppingflux_Model_Export_Rewrite_CatalogInventory_Stock_Item_Compatibility
{
    protected function _beforeSave()
    {
        if (version_compare(Mage::getVersion(), '1.4.0.0') >= 0) {
            parent::_beforeSave();
            Mage::dispatchEvent('cataloginventory_stock_item_save_before', array('item' => $this));
            return $this;
        } else {
            return parent::_beforeSave();
        }
    }
}
