<?php

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Order_LogController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initAction()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->loadLayout()
            ->_setActiveMenu('shoppingflux/manageorders/log')
            ->_addBreadcrumb($helper->__('ShoppingFlux orders log'), $helper->__('ShoppingFlux orders log'));

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()->renderLayout();
    }

    public function deleteAction()
    {
        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Log_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/manageorders_log_collection');

        /** @var Profileolabs_Shoppingflux_Model_Manageorders_Log $log */
        foreach ($collection as $log) {
            $log->delete();
        }

        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');
        $this->_getSession()->addSuccess($helper->__('Log is empty.'));
        $this->_redirect('*/*/index');

    }

    public function gridAction()
    {
        /** @var Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Log_Grid $gridBlock */
        $gridBlock = $this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_log_grid');
        $this->getResponse()->setBody($gridBlock->toHtml());
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux/manageorders');
    }
}
