<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_System_Config_Form_Fieldset_Shipping_Method_Extra extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract {

    public function render(Varien_Data_Form_Element_Abstract $element) {
        if(!$this->shouldRenderUnregistered()) {
            return parent::render($element);
        }
        
        $html = $this->_getHeaderHtml($element);
        
        $collection = Mage::getModel('profileolabs_shoppingflux/manageorders_shipping_method')->getCollection();
        
        if($collection->count()<=0) {
            $this->_addEmptyField($element);
        }
        
        $i = 1;
        foreach($collection as $shippingMethod) {
            $this->_addShippingMethodField($element, $shippingMethod->getFullShippingMethodCode(), 10*$i++);
        }


        foreach ($element->getSortedElements() as $field) {
            $html .= $field->toHtml();
        }

        $html .= $this->_getFooterHtml($element);

        return $html;
    }

    protected function _getHeaderHtml($element) {
        $html = parent::_getHeaderHtml($element);
        return $html;
    }

    protected function _getFieldRenderer() {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }
        return $this->_fieldRenderer;
    }

    protected function _getDummyElement() {
        if (empty($this->_dummyElement)) {
            $this->_dummyElement = new Varien_Object(array('show_in_default' => 1, 'show_in_website' => 1, 'show_in_store' => 0));
        }
        return $this->_dummyElement;
    }
    
    protected function _getNiceName($index) {
        return ucwords(str_replace('_', ' ', $index));
    }


    protected function _addShippingMethodField($fieldset, $shippingMethod, $sortOrder) {
        $shippingMethod = preg_replace('%[^a-zA-Z0-9_]%', '', $shippingMethod);
        $configData = $this->getConfigData();
        $path = 'shoppingflux_mo/advanced_shipping_method/' . $shippingMethod;
        $data = '';
        $inherit = false;
        if (isset($configData[$path])) {
            $data = $configData[$path];
            $inherit = false;
        } else {
            if($this->getForm()->getConfigRoot()) {
                $data = (string) $this->getForm()->getConfigRoot()->descend($path);
                $inherit = true;
            }
        }
        $e = $this->_getDummyElement();
        $fieldset->addField($shippingMethod, 'select', array(
                    'name' => 'groups[advanced_shipping_method][fields][' . $shippingMethod . '][value]',
                    'label' => Mage::helper('profileolabs_shoppingflux')->__('Shipping Method for %s', $this->_getNiceName($shippingMethod)),
                    'value' => $data,
                    'values' => Mage::getSingleton('adminhtml/system_config_source_shipping_allmethods')->toOptionArray(),
                    'sort_order' => $sortOrder,
                    'inherit' => $inherit,
                    'can_use_default_value' => $this->getForm()->canUseDefaultValue($e),
                    'can_use_website_value' => $this->getForm()->canUseWebsiteValue($e),
                ))->setRenderer($this->_getFieldRenderer());

    }


    protected function _addEmptyField($fieldset) {
        $configData = $this->getConfigData();
        $path = 'shoppingflux_mo/advanced_shipping_method/zzzzzz';
        $e = $this->_getDummyElement();
        $fieldset->addField('zzzzzz', 'note', array(
                    'name' => 'groups[advanced_shipping_method][fields][zzzzzz][value]',
                    'label' => Mage::helper('profileolabs_shoppingflux')->__('There is no marketplace shipping method registered yet.'),
                ))->setRenderer($this->_getFieldRenderer());

    }

}