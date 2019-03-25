<?php

abstract class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Order_Gsa_Shipment_Tracking_Scripts_Abstract extends Mage_Adminhtml_Block_Template
{
    /**
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if ($order = Mage::registry('current_order')) {
            return $order;
        } elseif ($invoice = $this->getInvoice()) {
            return $invoice->getOrder();
        }

        return $this->getShipment()->getOrder();
    }

    /**
     * @return Mage_Sales_Model_Order_Invoice|null
     */
    public function getInvoice()
    {
        return Mage::registry('current_invoice');
    }

    /**
     * @return Mage_Sales_Model_Order_Shipment|null
     */
    public function getShipment()
    {
        return Mage::registry('current_shipment');
    }

    /**
     * @return int
     */
    public function getStoreId()
    {
        return $this->getOrder()->getStoreId();
    }
    
    /**
     * @return string[]
     */
    public function getTrackableCarrierCodes()
    {
        /** @var Profileolabs_Shoppingflux_Model_Config $config */
        $config = Mage::getSingleton('profileolabs_shoppingflux/config');
        return array_keys($config->getGsaCarrierMapping($this->getStoreId()));
    }

    /**
     * @return string
     */
    public function getCustomCarrierTitleSelectId()
    {
        return 'shoppingflux-gsa-custom-carrier-title-select';
    }

    /**
     * @return string
     */
    public function getCustomCarrierTitleSelectHtml()
    {
        if (!$this->hasData('custom_carrier_title_select_html')) {
            /** @var Profileolabs_Shoppingflux_Model_System_Config_Source_Gsa_Carrier $gsaCarrierSource */
            $gsaCarrierSource = Mage::getSingleton('profileolabs_shoppingflux/system_config_source_gsa_carrier');

            /** @var Mage_Core_Block_Html_Select $selectBlock */
            $selectBlock = $this->getLayout()->createBlock('core/html_select');
            $selectBlock->setId('shoppingflux-gsa-custom-carrier-title-select');
            $selectBlock->setClass('select');
            $selectBlock->setOptions($gsaCarrierSource->toOptionArray());
            $this->setData('custom_carrier_title_select_html', $selectBlock->toHtml());
        }

        return $this->_getData('custom_carrier_title_select_html');
    }

    protected function _toHtml()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Sales $salesHelper */
        $salesHelper = Mage::helper('profileolabs_shoppingflux/sales');
        return $salesHelper->isGoogleShoppingActionsOrder($this->getOrder()) ? parent::_toHtml() : '';
    }
}
