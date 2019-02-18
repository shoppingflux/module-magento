<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Log extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('profileolabs_shoppingflux/manageorders_log');
    }

    /**
     * @param string $message
     * @param int|null $orderId
     * @return $this
     */
    public function log($message, $orderId = null)
    {
        $orderMessage = '';

        if ($orderId !== null) {
            /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
            $helper = Mage::helper('profileolabs_shoppingflux');

            $orderMessage = $helper->__(
                'ShoppingFlux Order ID : #%s (store %s)',
                $orderId,
                Mage::app()->getStore()->getId()
            );
        }

        $message = $orderMessage . ' / ' . $message;
        $this->setData('message', $message);
        return $this->save();
    }
}
