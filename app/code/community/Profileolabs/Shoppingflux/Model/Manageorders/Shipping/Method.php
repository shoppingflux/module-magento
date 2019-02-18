<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Method extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('profileolabs_shoppingflux/manageorders_shipping_method');
    }

    /**
     * @param string $marketplace
     * @param string $shippingMethod
     * @return string
     */
    public function getFullShippingMethodCodeFor($marketplace, $shippingMethod)
    {
        $code = $marketplace . '_' . $shippingMethod;
        $code = strtolower($code);
        $code = preg_replace('%\s+%i', '_', $code);
        return $code;
    }

    /**
     * @return string
     */
    public function getFullShippingMethodCode()
    {
        return $this->getFullShippingMethodCodeFor($this->getMarketplace(), $this->getShippingMethod());
    }

    /**
     * @param string $marketplace
     * @param string $shippingMethod
     * @return Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Method
     */
    public function loadByMethod($marketplace, $shippingMethod)
    {
        $collection = $this->getCollection();
        $collection->addFieldToFilter('marketplace', $marketplace);
        $collection->addFieldToFilter('shipping_method', $shippingMethod);

        if ($collection->getSize() === 0) {
            $this->setId(null);
            $this->setMarketplace($marketplace);
            $this->setShippingMethod($shippingMethod);
            return $this;
        }

        $collection->setCurPage(1);
        $collection->setPageSize(1);
        return $collection->getFirstItem();
    }

    /**
     * @param string $marketplace
     * @param string $shippingMethod
     */
    public function saveShippingMethod($marketplace, $shippingMethod)
    {
        /** @var Mage_Core_Model_Date $dateModel */
        $dateModel = Mage::getModel('core/date');
        $method = $this->loadByMethod($marketplace, $shippingMethod);
        $method->setLastSeenAt(date('Y-m-d H:i:s', $dateModel->timestamp(time())));
        $method->save();
    }
}
