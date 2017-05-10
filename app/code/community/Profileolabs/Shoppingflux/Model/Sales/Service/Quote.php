<?php

/**
This rewrite is necessary for some very old magento versions compatibility. In newer versions, it is useless.
 */
class Profileolabs_Shoppingflux_Model_Sales_Service_Quote extends Mage_Sales_Model_Service_Quote {

  protected function _validate()
    {
      if(!Mage::registry('is_shoppingfeed_import')) {
          return parent::_validate();
      }
        $helper = Mage::helper('sales');
        if (!$this->getQuote()->isVirtual()) {
            $address = $this->getQuote()->getShippingAddress();
            $addressValidation = $address->validate();
            if ($addressValidation !== true) {
                Mage::throwException(
                    $helper->__('Please check shipping address information. %s', implode(' ', $addressValidation))
                );
            }
            
        }

        $addressValidation = $this->getQuote()->getBillingAddress()->validate();
        if ($addressValidation !== true) {
            Mage::throwException(
                $helper->__('Please check billing address information. %s', implode(' ', $addressValidation))
            );
        }
        
        return $this;
    }

}