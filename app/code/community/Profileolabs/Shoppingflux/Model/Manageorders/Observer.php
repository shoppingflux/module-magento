<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Observer
{
    /**
     * @var array
     */
    protected $_trackingUrlCallbacks = array(
        'owebia' => '_extractOwebiaTrackingUrl',
        'dpdfrclassic' => '_extractDpdTrackingUrl',
        'dpdfrpredict' => '_extractDpdTrackingUrl',
        'dpdfrrelais' => '_extractDpdTrackingUrl',
    );

    /**
     * @return Profileolabs_Shoppingflux_Model_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('profileolabs_shoppingflux');
    }

    /**
     * @return Profileolabs_Shoppingflux_Helper_Sales
     */
    public function getSalesHelper()
    {
        return Mage::helper('profileolabs_shoppingflux/sales');
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function setCustomerTaxClassId($observer)
    {
        if (!$this->getConfig()->applyTax()
            && Mage::registry('is_shoppingfeed_import')
            && ($customerGroup = $observer->getEvent()->getData('object'))
            && ($customerGroup instanceof Varien_Object)
        ) {
            $customerGroup->setData('tax_class_id', 999);
        }
    }

    /**
     * @param Varien_Object $trackingInfo
     * @return string
     */
    protected function _extractOwebiaTrackingUrl(Varien_Object $trackingInfo)
    {
        return preg_match('%href="(.*?)"%i', $trackingInfo->getStatus(), $matches)
            ? $matches[1]
            : '';
    }

    /**
     * @param Varien_Object $trackingInfo
     * @return string
     */
    protected function _extractDpdTrackingUrl(Varien_Object $trackingInfo)
    {
        return preg_match('%iframe src="(.*?)"%i', $trackingInfo->getStatus(), $matches)
            ? $matches[1]
            : '';
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment_Track $shipmentTrack
     * @return string
     */
    protected function _getShipmentTrackingUrl($shipmentTrack)
    {
        $trackingUrl = '';
        $carrierCode = $shipmentTrack->getCarrierCode();
        $trackingNumber = $shipmentTrack->getData('number');

        if (preg_match('%^(owebia|(dpdfr)(classic|predict|relais))%i', $carrierCode, $matches)
            && isset($this->_trackingUrlCallbacks[$matches[1]])
        ) {
            /** @var Mage_Shipping_Model_Config $shippingConfig */
            $shippingConfig = Mage::getSingleton('shipping/config');
            $carrierInstance = $shippingConfig->getCarrierInstance($carrierCode);

            if ($carrierInstance
                && ($trackingInfo = $carrierInstance->getTrackingInfo($trackingNumber))
                && ($trackingInfo instanceof Varien_Object)
            ) {
                $trackingUrl = call_user_func(array($this, $this->_trackingUrlCallbacks[$matches[1]]), $trackingInfo);
            }
        } else {
            $trackingNumber = urlencode(trim($trackingNumber));

            if ('colissimoflexibilite' === $carrierCode) {
                $trackingUrl = 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . $trackingNumber;
            } elseif ('fedex' === $carrierCode) {
                $trackingUrl = 'https://www.fedex.com/apps/fedextrack/?action=track&tracknumbers=' . $trackingNumber;
            } elseif ('ups' === $carrierCode) {
                if (preg_match('%^1Z[a-z0-9]{16}$%i', $trackingNumber)) {
                    $trackingUrl = 'https://www.ups.com/track?tracknum=' . $trackingNumber . '/trackdetails';
                }
            } elseif ('usps' === $carrierCode) {
                $trackingUrl = 'https://tools.usps.com/go/TrackConfirmAction_input?qtc_tLabels1=' . $trackingNumber;
            } elseif ('dhl' === $carrierCode) {
                $trackingUrl = 'http://www.dhl.com/en/express/tracking.html?AWB=' . $trackingNumber;
            }
        }

        return $trackingUrl;
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return array|false
     */
    public function getShipmentTrackingNumber($shipment)
    {
        $salesHelper = $this->getSalesHelper();
        $storeId = $shipment->getStoreId();
        $result = false;
        $tracks = $shipment->getAllTracks();

        /** @var Mage_Sales_Model_Order_Shipment_Track $track */
        foreach ($tracks as $track) {
            if ('' !== trim($track->getData('number'))) {
                $carrierCode = trim($track->getCarrierCode());
                $trackTitle = $track->getData('title');

                if (('custom' !== $carrierCode)
                    && $salesHelper->isGoogleShoppingActionsOrder($shipment->getOrder())
                    && ($gsaTrackTitle = $this->getConfig()->getMappedGsaCarrierCodeFor($carrierCode, $storeId))
                ) {
                    $trackTitle = $gsaTrackTitle;
                }

                $result = array(
                    'trackUrl' => $this->_getShipmentTrackingUrl($track),
                    'trackId' => $track->getData('number'),
                    'trackTitle' => $trackTitle,
                );

                break;
            }
        }

        $dataObject = new Varien_Object(array('result' => $result, 'shipment' => $shipment));
        Mage::dispatchEvent('shoppingflux_get_shipment_tracking', array('data_object' => $dataObject));
        $result = $dataObject->getData('result');

        return is_array($result) ? $result : false;
    }

    /**
     * @param mixed $trackingData
     * @return bool
     */
    protected function _isSendableTrackingData($trackingData)
    {
        return is_array($trackingData)
            && isset($trackingData['trackId'])
            && isset($trackingData['trackTitle'])
            && ('' !== trim($trackingData['trackId']))
            && ('' !== trim($trackingData['trackTitle']));
    }

    /**
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function sendStatusCanceled($observer)
    {
        if (($order = $observer->getEvent()->getData('order'))
            && ($order instanceof Mage_Sales_Model_Order)
        ) {
            if (!$order->getFromShoppingflux()) {
                return $this;
            }

            $storeId = $order->getStoreId();
            $apiKey = $this->getConfig()->getApiKey($storeId);
            $wsUri = $this->getConfig()->getWsUri();
            $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

            $orderIdShoppingflux = $order->getOrderIdShoppingflux();
            $marketPlace = $order->getMarketplaceShoppingflux();

            try {
                $service->updateCanceledOrder(
                    $orderIdShoppingflux,
                    $marketPlace,
                    Profileolabs_Shoppingflux_Model_Service::ORDER_STATUS_CANCELED
                );
            } catch (Exception $e) {
            }

            $this->getHelper()->log(
                'Order ' . $orderIdShoppingflux . ' has been canceled. Information sent to ShoppingFlux.'
            );
        }

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function scheduleShipmentUpdate($observer)
    {
        $salesHelper = $this->getSalesHelper();

        if (
            ($shipment = $observer->getEvent()->getData('shipment'))
            && ($shipment instanceof Mage_Sales_Model_Order_Shipment)
            && !$salesHelper->isFulfilmentMarketplace((string) $shipment->getOrder()->getMarketplaceShoppingflux())
        ) {
            /** @var Profileolabs_Shoppingflux_Model_Manageorders_Export_Shipments $exporter */
            $exporter = Mage::getModel('profileolabs_shoppingflux/manageorders_export_shipments');
            $exporter->scheduleShipmentExport($shipment->getId());
        }
    }

    /**
     * @param string $message
     * @return bool
     */
    protected function _isApiConnectionErrorMessage($message)
    {
        return preg_match('%Result is not Varien_Simplexml_Element%', $message)
            || preg_match('%Error in cURL request: connect.. timed out%', $message);
    }

    public function sendScheduledShipments()
    {
        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Export_Shipments_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/manageorders_export_shipments_collection');

        foreach ($collection as $item) {
            try {
                /** @var Mage_Sales_Model_Order_Shipment $shipment */
                $storeId = null;
                $shipment = Mage::getModel('sales/order_shipment');
                $shipment->load($item->getShipmentId());
                $storeId = $shipment->getStoreId();

                if ((Mage::app()->getStore()->getCode() == 'admin') || (Mage::app()->getStore()->getId() == $storeId)) {
                    $trackingData = $this->getShipmentTrackingNumber($shipment);

                    if ($this->_isSendableTrackingData($trackingData)
                        || ($shipment->getUpdatedAt() < $this->getConfig()->getShipmentUpdateLimit())
                    ) {
                        $this->sendStatusShipped($shipment);
                        $item->delete();
                    }
                }
            } catch (Exception $e) {
                $exceptionMessage = $e->getMessage();
                $isConnectionError = $this->_isApiConnectionErrorMessage($exceptionMessage);

                if ($isConnectionError) {
                    $shouldNotifyError = $this->getConfig()
                        ->getConfigFlag('shoppingflux_mo/manageorders/notify_update_connection_errors', $storeId);
                } else {
                    $shouldNotifyError = $this->getConfig()
                        ->getConfigFlag('shoppingflux_mo/manageorders/notify_update_api_errors', $storeId);
                }

                if ($shouldNotifyError) {
                    if ($shipment->getId()) {
                        $message = 'Erreur de mise à jour de l\'expédition #'
                            . $shipment->getIncrementId()
                            . ' (commande #' . $shipment->getOrder()->getIncrementId() . ') : <br/>';
                    } else {
                        $message = 'Erreur de mise à jour d\'une expédition : <br/>';
                    }

                    $message .= $exceptionMessage
                        . '<br/><br/> Merci de vérifier les infos de votre commandes ou de contacter le support Shopping Flux ou celui de la place de marché';

                    $this->getHelper()->notifyError($message);
                }

                if ($item->getId() && !$isConnectionError) {
                    try {
                        $item->delete();
                    } catch (Exception $e) {
                    }
                }
            }
        }

        return $this;
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return $this
     */
    public function sendStatusShipped($shipment)
    {
        if (!$shipment->getId()) {
            return $this;
        }

        $order = $shipment->getOrder();
        $storeId = $order->getStoreId();
        $apiKey = $this->getConfig()->getApiKey($storeId);
        $wsUri = $this->getConfig()->getWsUri();

        if (!$order->getFromShoppingflux()) {
            return $this;
        }
        if ($order->getShoppingfluxShipmentFlag()) {
            return $this;
        }

        $trackingData = $this->getShipmentTrackingNumber($shipment);
        $orderIdShoppingflux = $order->getOrderIdShoppingflux();
        $marketplace = $order->getMarketplaceShoppingflux();

        $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

        $result = $service->updateShippedOrder(
            $orderIdShoppingflux,
            $marketplace,
            Profileolabs_Shoppingflux_Model_Service::ORDER_STATUS_SHIPPED,
            $trackingData ? $trackingData['trackId'] : '',
            $trackingData ? $trackingData['trackTitle'] : '',
            $trackingData ? $trackingData['trackUrl'] : ''
        );


        if ($result) {
            if ($result->Response->Orders->Order->StatusUpdated == 'False') {
                Mage::throwException('Error in update status shipped to shopping flux');
            } else {
                $status = $result->Response->Orders->Order->StatusUpdated;

                $order->setShoppingfluxShipmentFlag(1);
                $order->save();

                $this->getHelper()->log(
                    $this->getHelper()->__(
                        'Order %s has been updated in ShoppingFlux. Status returned : %s',
                        $orderIdShoppingflux,
                        $status
                    )
                );
            }
        } else {
            $message = $this->getHelper()->__('Error in update status shipped to ShoppingFlux');
            $this->getHelper()->log($message);
            Mage::throwException($message);
        }

        return $this;
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function observeSalesOrderPlaceAfter($observer)
    {
        if (($order = $observer->getEvent()->getData('order'))
            && ($order instanceof Mage_Sales_Model_Order)
        ) {
            $idTracking = $this->getConfig()->getIdTracking();

            if (!$idTracking || !$order || !$order->getId()) {
                return;
            }

            try {
                if (version_compare(Mage::getVersion(), '1.6.0') > 0) {
                    if (!$order->getRemoteIp() || $order->getFromShoppingflux()) {
                        return;
                    }
                } elseif ($order->getFromShoppingflux()) {
                    return;
                }

                /** @var Mage_Core_Helper_Http $httpHelper */
                $httpHelper = Mage::helper('core/http');
                $ip = $order->getRemoteIp() ? $order->getRemoteIp() : $httpHelper->getRemoteAddr(false);
                $grandTotal = $order->getBaseGrandTotal();
                $incrementId = $order->getIncrementId();

                $tagUrl = 'https://tag.shopping-flux.com/order/'
                    . base64_encode($idTracking . '|' . $incrementId . '|' . $grandTotal)
                    . '?ip=' . $ip;

                file_get_contents($tagUrl);
            } catch (Exception $ex) {
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function observeAdminhtmlBlockHtmlBefore($observer)
    {
        if (($block = $observer->getEvent()->getData('block'))
            && ($block instanceof Mage_Adminhtml_Block_Sales_Order_View)
        ) {
            if (method_exists($block, 'addButton')
                && $block->getOrderId()
                && $block->getOrder()
                && !$block->getOrder()->getShoppingfluxShipmentFlag()
                && $block->getOrder()->getFromShoppingflux()
                && $block->getOrder()->hasShipments()
            ) {
                /** @var Mage_Adminhtml_Helper_Data $adminHelper */
                $adminHelper = Mage::helper('adminhtml');

                $notifyUrl = $adminHelper->getUrl(
                    'adminhtml/shoppingfeed_order_import/sendShipment',
                    array('order_id' => $block->getOrder()->getId())
                );

                $block->addButton(
                    'shoppingflux_shipment',
                    array(
                        'label' => $this->getHelper()->__('Send notification to ShoppingFeed'),
                        'onclick' => 'setLocation(\'' . $notifyUrl . '\');',
                        'class' => 'shoppingflux-shipment-notification',
                    ),
                    0
                );
            }
        }
    }

    public function updateMarketplaceList()
    {
        $apiKeys = array();

        foreach (Mage::app()->getStores() as $store) {
            if ($apiKey = trim($this->getConfig()->getApiKey($store->getId()))) {
                $apiKeys[] = $apiKey;
            }
        }

        if (empty($apiKeys)) {
            return;
        }

        $wsUri = $this->getConfig()->getWsUri();

        foreach ($apiKeys as $apiKey) {
            $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

            try {
                $marketplaces = $service->getMarketplaces();

                if (count($marketplaces) > 5) {
                    $marketplaceCsvFile = Mage::getModuleDir('', 'Profileolabs_Shoppingflux')
                        . DS
                        . 'etc'
                        . DS
                        . 'marketplaces.csv';

                    $handle = fopen($marketplaceCsvFile, 'w+');

                    foreach ($marketplaces as $marketplace) {
                        fwrite($handle, $marketplace . "\n");
                    }

                    fclose($handle);
                    break;
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
    }
}
