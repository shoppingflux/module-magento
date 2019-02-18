<?php

include_once 'Mage/Adminhtml/controllers/Catalog/CategoryController.php';

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Export_CategoryController extends Mage_Adminhtml_Catalog_CategoryController
{
    public function gridAction()
    {
        if (!$category = $this->_initCategory(true)) {
            return;
        }

        /** @var Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Tab_Default $categoryTabBlock */
        $categoryTabBlock = $this->getLayout()->createBlock(
            'profileolabs_shoppingflux/export_adminhtml_catalog_category_tab_default',
            'shoppingflux.product.grid'
        );

        $this->getResponse()->setBody($categoryTabBlock->toHtml());
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux/export');
    }
}
