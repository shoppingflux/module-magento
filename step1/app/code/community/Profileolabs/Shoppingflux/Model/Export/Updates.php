<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Export_Updates  extends Mage_Core_Model_Abstract {

    protected function _construct() {
        $this->_init('profileolabs_shoppingflux/export_updates');
    }

    public function loadWithData($data) {
        if (!isset($data['product_sku']) || !isset($data['store_id']))
            return;
        $productSku = $data['product_sku'];
        $storeId = $data['store_id'];

        $collection = $this->getCollection();
        $select = $collection->getSelect();
        $select->where('product_sku = ?', $productSku);
        $select->where('store_id = ?', $storeId);
        $collection->load();
        if ($collection->getSize() <= 0)
            return;
        $this->load($collection->getFirstItem()->getId());
        return;
    }

}