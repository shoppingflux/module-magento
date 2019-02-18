<?php

class Profileolabs_Shoppingflux_Block_Adminhtml_Register_Notification extends Mage_Adminhtml_Block_Template
{
    protected function _toHtml()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');
        return $helper->isRegistered() ? '' : parent::_toHtml();
    }
}
