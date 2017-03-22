<?php

/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Order_ImportController extends Mage_Adminhtml_Controller_Action {

    protected function _initAction() {
        $this->loadLayout()
                ->_setActiveMenu('shoppingflux/manageorders/import')
                ->_addBreadcrumb(Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux order import'), Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux order import'));

        return $this;
    }

    public function indexAction() {
        $this->_initAction()
                ->renderLayout();

        return $this;
    }

    public function importOrdersAction() {
        try {

            error_reporting(E_ALL | E_STRICT);
            ini_set("display_errors", 1);

            /* @var $model Profileolabs_Shoppingflux_ManageOrders_Model_Order */


            $model = Mage::getModel('profileolabs_shoppingflux/manageorders_order')->manageOrders();

            $this->_getSession()->addSuccess(Mage::helper('profileolabs_shoppingflux')->__("%d orders have been imported", $model->getNbOrdersImported()));

            if ($model->getResultSendOrder() != "") {
                $this->_getSession()->addSuccess(Mage::helper('profileolabs_shoppingflux')->__("Orders sent result : %s", $model->getResultSendOrder()));
            }
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }

        $this->_redirect("*/*/index");
    }
    
    
     public function sendShipmentAction() {
            $orderId = $this->getRequest()->getParam('order_id');
            $order = Mage::getModel('sales/order')->load($orderId);
            if($order->getId() && $order->hasShipments()) {
                $shipment = $order->getShipmentsCollection()->getFirstItem();
                Mage::getModel('profileolabs_shoppingflux/manageorders_observer')->sendStatusShipped($shipment);
            }
            $this->_redirect('adminhtml/sales_order/view', array('order_id' => $orderId));
        }

         protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('shoppingflux/manageorders');
    }
}