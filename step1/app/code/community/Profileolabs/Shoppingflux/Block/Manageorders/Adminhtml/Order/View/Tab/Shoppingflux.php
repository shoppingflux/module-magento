<?php
/**
 * Adminhtml sales orders shopping flux grid
 *
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Order_View_Tab_Shoppingflux
    extends Mage_Adminhtml_Block_Sales_Order_Abstract
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('profileolabs/shoppingflux/manageorders/sales/order/view/tab/shoppingflux.phtml');
    }
    
    
    public function getOrder()
    {
        return Mage::registry('current_order');
    }
    
    public function getTabTitle()
    {
        return $this->getTabLabel();
    }
    
    public function getTabLabel()
    {
        return Mage::helper('profileolabs_shoppingflux')->__('Shopping Flux');
    }
    
     public function canShowTab()
    {
        return $this->getOrder()->getFromShoppingflux();
    }

    
    public function isHidden()
    {
        return false;
    }


}
