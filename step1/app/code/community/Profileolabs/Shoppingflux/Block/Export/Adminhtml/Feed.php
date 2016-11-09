<?php
/**
 * Shoppingflux select products block
 *
 * @category   Profileolabs
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait kassim@profileo.com
 */
class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Feed extends Mage_Adminhtml_Block_Widget_Container
{
    /**
     * Set template
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('profileolabs/shoppingflux/export/feed.phtml');
    }

    public function getFeedUrl($store) {
        return Mage::helper('profileolabs_shoppingflux')->getFeedUrl($store);
    }
    
    public function storeHasFeed($store) {
       return Mage::getSingleton('profileolabs_shoppingflux/config')->isExportEnabled($store->getId());
    }
}
