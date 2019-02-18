<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_System_Config_Form_Fieldset_Shipping_Method extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Marketplace
{
    protected function _getConfigSectionKey()
    {
        return 'shoppingflux_mo';
    }

    protected function _getConfigGroupKey()
    {
        return 'shipping_method';
    }

    protected function _getFieldSuffix()
    {
        return 'method';
    }

    protected function _getFieldType()
    {
        return 'select';
    }

    /**
     * @param string $marketplace
     * @return array
     */
    protected function _getFieldConfig($marketplace)
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');
        /** @var Mage_Adminhtml_Model_System_Config_Source_Shipping_Allmethods $methodSource */
        $methodSource = Mage::getSingleton('adminhtml/system_config_source_shipping_allmethods');

        return array(
            'label' => $helper->__('Shipping Method for %s', $this->_beautifyMarketplaceName($marketplace)),
            'comment' => $helper->__('Leave empty to use default shipping method.'),
            'values' => $methodSource->toOptionArray(),
        );
    }
}
