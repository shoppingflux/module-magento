<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Observer {

    public function setCustomerTaxClassId($observer) {
        if (!$this->getConfig()->applyTax() && Mage::registry('is_shoppingfeed_import')/*Mage::getSingleton('checkout/session')->getIsShoppingFlux()*/) {
            $customerGroup = $observer->getEvent()->getObject();
            $customerGroup->setData('tax_class_id', 999);
        }
    }

    public function manageOrders($observer=false) {
        try {
            set_time_limit(0);
            Mage::getModel('profileolabs_shoppingflux/manageorders_order')->manageOrders();
        } catch (Exception $e) {
            Mage::throwException($e);
        }

        return $this;
    }

    public function getShipmentTrackingNumber($shipment) {
        $result = false;
        $tracks = $shipment->getAllTracks();
        $trackUrl = '';
        if (is_array($tracks) && !empty($tracks)) {
            $firstTrack = array_shift($tracks);
            if($firstTrack) {
                $carrierInstance = Mage::getSingleton('shipping/config')->getCarrierInstance($firstTrack->getCarrierCode());
                if ($carrierInstance) {
                    $trackingInfo = $carrierInstance->getTrackingInfo($firstTrack->getData('number'));
                    $status = $trackingInfo->getStatus();
                    if (preg_match('%href="(.*)"%i', $status, $regs)) {
                        $trackUrl = $regs[1];
                    }
                }
            }
            if (trim($firstTrack->getData('number'))) {
                $result = array('trackUrl' => $trackUrl, 'trackId' => $firstTrack->getData('number'), 'trackTitle' => $firstTrack->getData('title'));
            }
        }
        $dataObj = new Varien_Object(array('result' => $result, 'shipment'=>$shipment));
        Mage::dispatchEvent('shoppingflux_get_shipment_tracking', array('data_obj' => $dataObj));
        $result = $dataObj->getResult();
        
        return $result;
    }
    
    public function sendStatusCanceled($observer) {
        $order = $observer->getEvent()->getOrder();
        if (!$order->getFromShoppingflux())
            return $this;
        
        
        $storeId = $order->getStoreId();

        $apiKey = $this->getConfig()->getApiKey($storeId);
        $wsUri = $this->getConfig()->getWsUri();
        
        
        $orderIdShoppingflux = $order->getOrderIdShoppingflux();
        $marketPlace = $order->getMarketplaceShoppingflux();

        $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

        try {
            $service->updateCanceledOrder(
                    $orderIdShoppingflux, $marketPlace, Profileolabs_Shoppingflux_Model_Service::ORDER_STATUS_CANCELED
            );
        } catch(Exception $e) {
            
        }
        
        Mage::helper('profileolabs_shoppingflux')->log('Order ' . $orderIdShoppingflux . ' has been canceled. Information sent to ShoppingFlux.');

        
    }
    
    
    public function scheduleShipmentUpdate($observer) {
        $shipment = $observer->getShipment();
        Mage::getModel('profileolabs_shoppingflux/manageorders_export_shipments')->scheduleShipmentExport($shipment->getId());
    }

    public function sendScheduledShipments() {
        $collection = Mage::getModel('profileolabs_shoppingflux/manageorders_export_shipments')->getCollection();
        //$collection->addFieldToFilter('updated_at', array('lt'=>new Zend_Db_Expr("DATE_SUB('".date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time()))."', INTERVAL 60 MINUTE)")));
        foreach($collection as $item) {
            try {
                $shipment = Mage::getModel('sales/order_shipment')->load($item->getShipmentId());
                if(Mage::app()->getStore()->getCode() == 'admin' || Mage::app()->getStore()->getId() == $shipment->getStoreId()) {
                    $trakingInfos = $this->getShipmentTrackingNumber($shipment);
                    if($trakingInfos || $shipment->getUpdatedAt() < $this->getConfig()->getShipmentUpdateLimit()) {
                        $this->sendStatusShipped($shipment);
                        $item->delete();
                    }
                }
            } catch(Exception $e) {
                $shipment = Mage::getModel('sales/order_shipment')->load($item->getShipmentId());
                $message = 'Erreur de mise à jour de l\'expédition #'.$shipment->getIncrementId().' (commande #'.$shipment->getOrder()->getIncrementId().') : <br/>' . $e->getMessage();
                $message .= "<br/><br/> Merci de vérifier les infos de votre commandes ou de contacter le support Shopping Flux ou celui de la place de marché";
                $this->getHelper()->notifyError($message);
                if($item->getId() && !preg_match('%Error in cURL request: connect.. timed out%', $message) && !preg_match('%Result is not Varien_Simplexml_Element%', $message)) {
                    try {
                        $item->delete();
                    } catch(Exception $e) {}
                }
            }
        }
        return $this;
    }
    
    public function sendStatusShipped($shipment) {
        if(!$shipment->getId())
            return $this;
        
        $shipmentId = $shipment->getId();
        $order = $shipment->getOrder();
        $storeId = $order->getStoreId();

        $apiKey = $this->getConfig()->getApiKey($storeId);
        $wsUri = $this->getConfig()->getWsUri();

        //Mage::log("order = ".print_r($order->debug(),true),null,'debug_update_status_sf.log');

        if (!$order->getFromShoppingflux())
            return $this;
        
        if($order->getShoppingfluxShipmentFlag()) { 
            return $this;
        }

        $trakingInfos = $this->getShipmentTrackingNumber($shipment);



        $orderIdShoppingflux = $order->getOrderIdShoppingflux();
        $marketPlace = $order->getMarketplaceShoppingflux();


        //Mage::log("OrderIdSF = ".$orderIdShoppingflux." MP: ".$marketPlace,null,'debug_update_status_sf.log');

        /* @var $service Profileolabs_Shoppingflux_Model_Service */
        $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
        $result = $service->updateShippedOrder(
                $orderIdShoppingflux, $marketPlace, Profileolabs_Shoppingflux_Model_Service::ORDER_STATUS_SHIPPED, $trakingInfos ? $trakingInfos['trackId'] : '', $trakingInfos ? $trakingInfos['trackTitle'] : '', $trakingInfos ? $trakingInfos['trackUrl'] : ''
        );


        if ($result) {
            if ($result->Response->Orders->Order->StatusUpdated == 'False') {
                Mage::throwException('Error in update status shipped to shopping flux');
            } else {
                $status = $result->Response->Orders->Order->StatusUpdated;
                //Mage::log("status = ".$status,null,'debug_update_status_sf.log');

                $order->setShoppingfluxShipmentFlag(1);
                $order->save();
                $this->getHelper()->log($this->getHelper()->__("Order %s has been updated in ShoppingFlux. Status returned : %s", $orderIdShoppingflux, $status));
            }
        } else {
            $this->getHelper()->log($this->getHelper()->__("Error in update status shipped to ShoppingFlux"));
            Mage::throwException($this->getHelper()->__("Error in update status shipped to ShoppingFlux"));
        }

        return $this;
    }

    /**
     * Retrieve config
     * @return Profileolabs_Shoppingflux_Model_Manageorders_Config
     */
    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    /**
     * Get Helper
     * @return Profileolabs_Shoppingflux_Model_Manageorders_Helper_Data
     */
    public function getHelper() {
        return Mage::helper('profileolabs_shoppingflux');
    }
    
    
    public function observeSalesOrderPlaceAfter($observer) {
        $order = $observer->getEvent()->getOrder();
        $idTracking = Mage::getSingleton('profileolabs_shoppingflux/config')->getIdTracking();

        if (!$idTracking || !$order || !$order->getId()) {
            return;
        }
        try {
            if(version_compare(Mage::getVersion(), '1.6.0') > 0) {
                if(!$order->getRemoteIp() || $order->getFromShoppingflux()) {
                    //backend order
                    return;
                }
            } else if ($order->getFromShoppingflux()) {
                //backend order
                return;
            }
            $ip = $order->getRemoteIp()?$order->getRemoteIp():Mage::helper('core/http')->getRemoteAddr(false);
            $grandTotal = $order->getBaseGrandTotal();
            $incrementId = $order->getIncrementId();
            $tagUrl = 'https://tag.shopping-flux.com/order/'.base64_encode($idTracking.'|'.$incrementId.'|'.$grandTotal).'?ip='.$ip;
            file_get_contents($tagUrl);
        } catch (Exception $ex) {

        }
    }
    
    public function observeAdminhtmlBlockHtmlBefore($observer) {
        $block = $observer->getEvent()->getBlock();
        if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
           if (method_exists($block, 'addButton') && $block->getOrderId() && $block->getOrder() && $block->getOrder()->getShoppingfluxShipmentFlag() == 0 && $block->getOrder()->getFromShoppingflux() == 1 && $block->getOrder()->hasShipments()) {
                $block->addButton('shoppingflux_shipment', array(
                    'label' => $this->getHelper()->__('Send notification to ShoppingFeed'),
                    'onclick' => "setLocation('" . Mage::helper('adminhtml')->getUrl('adminhtml/shoppingfeed_order_import/sendShipment', array('order_id'=>$block->getOrder()->getId())) . "')",
                    'class' => 'shoppingflux-shipment-notification',
                        ), 0);
            }
        }
    }
    
    public function updateMarketplaceList() {
        $apiKey = false;
        foreach(Mage::app()->getStores() as $store) {
            if(!$apiKey) {
                $apiKey = $this->getConfig()->getApiKey($store->getId());
                $wsUri = $this->getConfig()->getWsUri();
            }
        }
    
        $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

        try {
            $marketplaces = $service->getMarketplaces();
            if(count($marketplaces) > 5) {
                $marketplaceCsvFile = Mage::getModuleDir( '', 'Profileolabs_Shoppingflux' ) . DS . 'etc' . DS . 'marketplaces.csv';
                $handle = fopen($marketplaceCsvFile, 'w+');
                foreach($marketplaces as $marketplace) {
                    fwrite($handle, $marketplace."\n");
                }
                fclose($handle);
            }
        } catch(Exception $e) {
            echo $e->getMessage();
        }
    }

}