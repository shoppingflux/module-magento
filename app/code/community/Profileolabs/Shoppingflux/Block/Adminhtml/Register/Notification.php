<?php

class Profileolabs_Shoppingflux_Block_Adminhtml_Register_Notification extends Mage_Adminhtml_Block_Template {

    protected function _toHtml() {
        if(Mage::helper('profileolabs_shoppingflux')->isRegistered()) {
           return ''; 
        }
        return parent::_toHtml();
    }

}
