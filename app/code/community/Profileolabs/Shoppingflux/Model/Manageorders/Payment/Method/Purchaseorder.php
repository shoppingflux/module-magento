<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Payment_Method_Purchaseorder extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'shoppingflux_purchaseorder';
    protected $_infoBlockType = 'profileolabs_shoppingflux/manageorders_payment_info_purchaseorder';

    public function assignData($data)
    {
        if (!$data instanceof Varien_Object) {
            $data = new Varien_Object($data);
        }
        $this->getInfoInstance()->setAdditionalData($data->getMarketplace());
        return $this;
    }

    public function isAvailable($quote = null)
    {
        return Mage::registry('is_shoppingfeed_import') || parent::isAvailable($quote);
    }
}
