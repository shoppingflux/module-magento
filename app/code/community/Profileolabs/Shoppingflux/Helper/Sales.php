<?php

class Profileolabs_Shoppingflux_Helper_Sales extends Mage_Core_Helper_Abstract
{
    /**
     * @param string $marketplace
     * @return bool
     */
    public function isFulfilmentMarketplace($marketplace)
    {
        return in_array(
            strtolower($marketplace),
            array(
                'amazon fba', // Amazon
                'epmm', // Monechelle
                'clogistique', // Cdiscount
            )
        );
    }    

    /**
     * @param int|null $storeId
     * @return array
     */
    public function getTrackableCarriersOptionHash($storeId = null)
    {
        /** @var Mage_Shipping_Model_Config $shippingConfig */
        $shippingConfig = Mage::getSingleton('shipping/config');
        $carrierInstances = $shippingConfig->getAllCarriers($storeId);
        $carrierHash = array();

        /** @var Mage_Shipping_Model_Carrier_Abstract $carrier */
        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carrierHash[$carrier->getCarrierCode()] = $carrier->getConfigData('title');
            }
        }

        return $carrierHash;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return bool
     */
    public function isGoogleShoppingActionsOrder(Mage_Sales_Model_Order $order)
    {
        return trim($order->getData('marketplace_shoppingflux')) === 'googleshoppingaction';
    }
}
