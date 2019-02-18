<?php

class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Feed extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('profileolabs/shoppingflux/export/feed.phtml');
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @return string
     */
    public function getFeedUrl(Mage_Core_Model_Store $store)
    {
        return $this->_getHelper()->getFeedUrl($store);
    }

    /**
     * @param Mage_Core_Model_Store $store
     * @return bool
     */
    public function storeHasFeed(Mage_Core_Model_Store $store)
    {
        return $this->_getHelper()->getConfig()->isExportEnabled($store->getId());
    }
}
