<?php

class Profileolabs_Shoppingflux_Model_Export_Updates extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('profileolabs_shoppingflux/export_updates');
    }

    /**
     * @param array $data
     * @return $this
     */
    public function loadWithData(array $data)
    {
        if (!isset($data['product_sku']) || !isset($data['store_id'])) {
            return $this;
        }

        $collection = $this->getCollection();
        $collection->addFieldToFilter('product_sku', $data['product_sku']);
        $collection->addFieldToFilter('store_id', $data['store_id']);

        if ($collection->getSize() === 0) {
            return $this;
        }

        $collection->setCurPage(1);
        $collection->setPageSize(1);
        return $this->load($collection->getFirstItem()->getId());
    }
}
