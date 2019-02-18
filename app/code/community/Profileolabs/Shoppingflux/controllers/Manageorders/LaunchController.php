<?php

class Profileolabs_Shoppingflux_Manageorders_LaunchController extends Mage_Core_Controller_Front_Action
{
    public function getordersAction()
    {
        $forceStore = $this->getRequest()->getParam('force_store', false);

        if ($forceStore) {
            /** @var Mage_Core_Model_App_Emulation $appEmulation */
            $appEmulation = Mage::getSingleton('core/app_emulation');

            if ($appEmulation) { // not available in 1.4
                $appEmulation->startEnvironmentEmulation($forceStore);
            }
        }

        /** @var Profileolabs_Shoppingflux_Model_Manageorders_Order $orderManager */
        $orderManager = Mage::getModel('profileolabs_shoppingflux/manageorders_order');
        $orderManager->manageOrders();
    }

    public function updateordersAction()
    {
        $forceStore = $this->getRequest()->getParam('force_store', false);

        if ($forceStore) {
            /** @var Mage_Core_Model_App_Emulation $appEmulation */
            $appEmulation = Mage::getSingleton('core/app_emulation');

            if ($appEmulation) { // not available in 1.4
                $appEmulation->startEnvironmentEmulation($forceStore);
            }
        }

        /** @var Profileolabs_Shoppingflux_Model_Manageorders_Observer $orderObserver */
        $orderObserver = Mage::getModel('profileolabs_shoppingflux/manageorders_observer');
        $orderObserver->sendScheduledShipments();
    }

    public function getupdateorderscountAction()
    {
        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Export_Shipments_Collection $shipmentCollection */
        $shipmentCollection = Mage::getResourceModel(
            'profileolabs_shoppingflux/manageorders_export_shipments_collection'
        );

        $this->getResponse()->setBody('Count : ' . $shipmentCollection->getSize());
    }
}
