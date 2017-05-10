<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author vincent enjalbert @ web-cooking.net
 */


if (Mage::helper('core')->isModuleEnabled('MDN_AdvancedStock') && class_exists('MDN_AdvancedStock_Model_CatalogInventory_Stock_Item')) {
    class Profileolabs_Shoppingflux_Model_Export_Rewrite_CatalogInventory_Stock_Item_Compatibility extends MDN_AdvancedStock_Model_CatalogInventory_Stock_Item {}
} else {
    class Profileolabs_Shoppingflux_Model_Export_Rewrite_CatalogInventory_Stock_Item_Compatibility extends Mage_CatalogInventory_Model_Stock_Item {}
}

class Profileolabs_Shoppingflux_Model_Export_Rewrite_CatalogInventory_Stock_Item
	extends Profileolabs_Shoppingflux_Model_Export_Rewrite_CatalogInventory_Stock_Item_Compatibility {
		
	/**
	 * cataloginventory_stock_item_save_before simply ceases to exist on Magento 1.4.0.0 and up
	 */
	protected function _beforeSave() {
		if(version_compare(Mage::getVersion(),'1.4.0.0') >= 0) {
			parent::_beforeSave();
			Mage::dispatchEvent('cataloginventory_stock_item_save_before', array('item' => $this));
        	return $this;
		} else {
			return parent::_beforeSave();
		}
	}		
		
}