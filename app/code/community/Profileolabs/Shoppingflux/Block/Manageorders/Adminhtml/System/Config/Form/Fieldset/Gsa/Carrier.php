<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_System_Config_Form_Fieldset_Gsa_Carrier extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if ($this->shouldRenderUnregistered()) {
            return parent::render($element);
        }

        $html = $this->_getHeaderHtml($element);

        /** @var Profileolabs_Shoppingflux_Helper_Sales $salesHelper */
        $salesHelper = Mage::helper('profileolabs_shoppingflux/sales');
        $carrierHash = $salesHelper->getTrackableCarriersOptionHash($this->_getStoreId());
        $i = 1;

        /** @var Mage_Shipping_Model_Carrier_Abstract $carrier */
        foreach ($carrierHash as $code => $title) {
            $this->_addCarrierField($element, $code, $title, $i++ * 10);
        }

        foreach ($element->getSortedElements() as $field) {
            $html .= $field->toHtml();
        }

        $html .= $this->_getFooterHtml($element);
        return $html;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $fieldset
     * @param string $mageCarrierCode
     * @param string $mageCarrierLabel
     * @param int $sortOrder
     */
    protected function _addCarrierField(
        Varien_Data_Form_Element_Abstract $fieldset,
        $mageCarrierCode,
        $mageCarrierLabel,
        $sortOrder
    ) {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $configPath = 'shoppingflux_mo/gsa_carrier_mapping/' . $mageCarrierCode;
        $configData = $this->getConfigData();
        $fieldValue = '';
        $isInheriting = false;

        if (isset($configData[$configPath])) {
            $fieldValue = $configData[$configPath];
            $isInheriting = false;
        } elseif ($this->getForm()->getConfigRoot()) {
            $fieldValue = (string) $this->getForm()->getConfigRoot()->descend($configPath);
            $isInheriting = true;
        }

        /** @var Profileolabs_Shoppingflux_Model_System_Config_Source_Gsa_Carrier $gsaCarrierSource */
        $gsaCarrierSource = Mage::getSingleton('profileolabs_shoppingflux/system_config_source_gsa_carrier');
        $gsaCarriers = $gsaCarrierSource->toOptionArray();
        array_unshift($gsaCarriers, array('value' => '', 'label' => ''));

        /** @var Mage_Adminhtml_Block_System_Config_Form $form */
        $form = $this->getData('form');

        $field = $fieldset->addField(
            $mageCarrierCode,
            'select',
            array(
                'name' => 'groups[gsa_carrier_mapping][fields][' . $mageCarrierCode . '][value]',
                'label' => $helper->__('GSA carrier for %s', $mageCarrierLabel),
                'value' => $fieldValue,
                'values' => $gsaCarriers,
                'sort_order' => $sortOrder,
                'inherit' => $isInheriting,
                'scope' => $form->getScope(),
                'scope_id' => $form->getScopeId(),
                'scope_label' => $form->getScopeLabel($this->_getDummyElement()),
                'can_use_default_value' => 0,
                'can_use_website_value' => 0,
            )
        );

        $field->setRenderer($this->_getFieldRenderer());
    }
}
