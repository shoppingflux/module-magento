<?php
/**
 * @category    ShoppingFlux
 * @package     Profileolabs_ShoppingFlux
 * @author kassim belghait
 */


class Profileolabs_Shoppingflux_Block_Manageorders_Payment_Info_Purchaseorder extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('profileolabs/shoppingflux/manageorders/payment/info/purchaseorder.phtml');
    }

}
