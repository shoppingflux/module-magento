<?php

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_GeneralController extends Mage_Adminhtml_Controller_Action
{
    public function userdefinedAction()
    {
        /** @var Mage_Catalog_Model_Resource_Setup $installer */
        $installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
        $installer->updateAttribute('catalog_product', 'shoppingflux_default_category', 'is_user_defined', 1);
        $installer->updateAttribute('catalog_product', 'shoppingflux_product', 'is_user_defined', 1);
    }


    public function nuserdefinedAction()
    {
        /** @var Mage_Catalog_Model_Resource_Setup $installer */
        $installer = Mage::getResourceModel('catalog/setup', 'profileolabs_shoppingflux_setup');
        $installer->updateAttribute('catalog_product', 'shoppingflux_default_category', 'is_user_defined', 0);
        $installer->updateAttribute('catalog_product', 'shoppingflux_product', 'is_user_defined', 0);
    }

    public function testAction()
    {
        $this->getResponse()->setBody('OK');
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux');
    }
}
