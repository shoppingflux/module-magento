<?php

class Profileolabs_Shoppingflux_Manageorders_LaunchController extends Mage_Core_Controller_Front_Action {

  

    public function getordersAction() {
        $forceStore = $this->getRequest()->getParam('force_store', false);
        
        if($forceStore) {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            if ($appEmulation) { // not available in 1.4
                $appEmulation->startEnvironmentEmulation($forceStore);
            }
        }
    	Mage::getModel('profileolabs_shoppingflux/manageorders_order')->manageOrders();
    }

    public function updateordersAction() {
        $forceStore = $this->getRequest()->getParam('force_store', false);
        
        if($forceStore) {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            if ($appEmulation) { // not available in 1.4
                $appEmulation->startEnvironmentEmulation($forceStore);
            }
        }
    	Mage::getModel('profileolabs_shoppingflux/manageorders_observer')->sendScheduledShipments();
    }
    
    public function getupdateorderscountAction() {
    	die('Count : ' . Mage::getModel('profileolabs_shoppingflux/manageorders_export_shipments')->getCollection()->count());
    }
}
