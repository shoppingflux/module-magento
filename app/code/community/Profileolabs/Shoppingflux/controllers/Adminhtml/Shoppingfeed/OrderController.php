<?php

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_OrderController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initAction()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->loadLayout()
            ->_setActiveMenu('shoppingflux/manageorders/order')
            ->_addBreadcrumb($helper->__('ShoppingFlux orders'), $helper->__('ShoppingFlux orders'));

        return $this;
    }

    /**
     * @return Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Order_Grid
     */
    protected function _getOrdersGridBlock()
    {
        return $this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_order_grid');
    }

    public function indexAction()
    {
        $this->_initAction()->renderLayout();
    }

    public function gridAction()
    {
        $this->getResponse()->setBody($this->_getOrdersGridBlock()->toHtml());
    }

    public function exportCsvAction()
    {
        $this->_prepareDownloadResponse('orders_shoppingflux.csv', $this->_getOrdersGridBlock()->getCsvFile());
    }

    public function exportExcelAction()
    {
        $this->_prepareDownloadResponse('orders_shoppingflux.xml', $this->_getOrdersGridBlock()->getExcelFile());
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux/manageorders');
    }
}
