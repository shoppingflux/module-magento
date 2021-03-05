<?php

class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Order_ImportController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return $this
     */
    protected function _initAction()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->loadLayout()
            ->_setActiveMenu('shoppingflux/manageorders/import')
            ->_addBreadcrumb($helper->__('ShoppingFlux order import'), $helper->__('ShoppingFlux order import'));

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()->renderLayout();
    }

    public function importOrdersAction()
    {
        try {
            error_reporting(E_ALL | E_STRICT);
            ini_set('display_errors', 1);

            /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
            $helper = Mage::helper('profileolabs_shoppingflux');

            /** @var Profileolabs_Shoppingflux_Model_ManageOrders_Order $importer */
            $importer = Mage::getModel('profileolabs_shoppingflux/manageorders_order');
            $importer->manageOrders();

            $importedCount = $importer->getImportedOrderCount();

            if (null === $importedCount) {
                $this->_getSession()->addNotice($helper->__('An import is already running.'));
            } else {
                $this->_getSession()->addSuccess(
                    $helper->__(
                        '%d orders have been imported',
                        (int) $importedCount
                    )
                );
            }

            if (($result = $importer->getSentOrdersResult()) != '') {
                $this->_getSession()->addSuccess($helper->__('Orders sent result : %s', $result));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }


    public function sendShipmentAction()
    {
        if (!$orderId = $this->getRequest()->getParam('order_id')) {
            $this->_redirect('adminhtml/sales_order/index');
            return;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order');
        $order->load($orderId);

        /** @var Mage_Sales_Model_Order_Shipment $shipment */
        if ($order->getId()
            && $order->hasShipments()
            && ($shipment = $order->getShipmentsCollection()->getFirstItem())
        ) {
            /** @var Profileolabs_Shoppingflux_Model_Manageorders_Observer $observer */
            $observer = Mage::getModel('profileolabs_shoppingflux/manageorders_observer');
            $observer->sendStatusShipped($shipment);
        }

        $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
    }

    protected function _isAllowed()
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        return $session->isAllowed('shoppingflux/manageorders');
    }
}
