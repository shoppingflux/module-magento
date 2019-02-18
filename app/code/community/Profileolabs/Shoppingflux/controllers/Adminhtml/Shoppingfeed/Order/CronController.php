<?php

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Order_CronController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initAction()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->loadLayout()
            ->_setActiveMenu('shoppingflux/manageorders/crons')
            ->_addBreadcrumb($helper->__('Crons'), $helper->__('Crons'));

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()->renderLayout();
    }

    public function gridAction()
    {
        /** @var Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Cron_Grid $gridBlock */
        $gridBlock = $this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_cron_grid');
        $this->getResponse()->setBody($gridBlock->toHtml());
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux');
    }
}
