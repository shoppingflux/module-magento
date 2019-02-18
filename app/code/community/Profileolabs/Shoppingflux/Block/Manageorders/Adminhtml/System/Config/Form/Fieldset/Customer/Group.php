<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_System_Config_Form_Fieldset_Customer_Group extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Marketplace
{
    protected function _getConfigSectionKey()
    {
        return 'shoppingflux_mo';
    }

    protected function _getConfigGroupKey()
    {
        return 'import_customer';
    }

    protected function _getFieldSuffix()
    {
        return 'group';
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
        /** @var Mage_Adminhtml_Model_System_Config_Source_Customer_Group $groupSource */
        $groupSource = Mage::getSingleton('adminhtml/system_config_source_customer_group');

        return array(
            'label' => $helper->__('%s customer group', $this->_beautifyMarketplaceName($marketplace)),
            'comment' => $helper->__('Leave empty to use default group'),
            'values' => $groupSource->toOptionArray(),
        );
    }
}
