<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_System_Config_Form_Fieldset_Shipping_Method_Extra extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if ($this->shouldRenderUnregistered()) {
            return parent::render($element);
        }

        $html = $this->_getHeaderHtml($element);

        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Shipping_Method_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/manageorders_shipping_method_collection');
        $i = 1;

        foreach ($collection as $shippingMethod) {
            $this->_addShippingMethodField($element, $shippingMethod->getFullShippingMethodCode(), 10 * $i++);
        }

        if ($i === 1) {
            $this->_addEmptyField($element);
        }

        foreach ($element->getSortedElements() as $field) {
            $html .= $field->toHtml();
        }

        $html .= $this->_getFooterHtml($element);
        return $html;
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $fieldset
     * @param string $shippingMethod
     * @param int $sortOrder
     */
    protected function _addShippingMethodField(Varien_Data_Form_Element_Abstract $fieldset, $shippingMethod, $sortOrder)
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $shippingMethod = preg_replace('%[^a-zA-Z0-9_]%', '', $shippingMethod);
        $configPath = 'shoppingflux_mo/advanced_shipping_method/' . $shippingMethod;
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

        /** @var Mage_Adminhtml_Model_System_Config_Source_Shipping_Allmethods $methodSource */
        $methodSource = Mage::getSingleton('adminhtml/system_config_source_shipping_allmethods');

        /** @var Mage_Adminhtml_Block_System_Config_Form $form */
        $form = $this->getData('form');

        $field = $fieldset->addField(
            $shippingMethod,
            'select',
            array(
                'name' => 'groups[advanced_shipping_method][fields][' . $shippingMethod . '][value]',
                'label' => $helper->__('Shipping Method for %s', ucwords(str_replace('_', ' ', $shippingMethod))),
                'value' => $fieldValue,
                'values' => $methodSource->toOptionArray(),
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

    /**
     * @param Varien_Data_Form_Element_Abstract $fieldset
     */
    protected function _addEmptyField(Varien_Data_Form_Element_Abstract $fieldset)
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $field = $fieldset->addField(
            '__shopping_feed_empty__',
            'note',
            array(
                'name' => 'groups[advanced_shipping_method][fields][__shopping_feed_empty__][value]',
                'label' => $helper->__('There is no marketplace shipping method registered yet.'),
            )
        );

        $field->setRenderer($this->_getFieldRenderer());
    }
}
