<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_System_Config_Form_Fieldset_Customer_Group extends Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract {

    public function render(Varien_Data_Form_Element_Abstract $element) {
        if(!$this->shouldRenderUnregistered()) {
            return parent::render($element);
        }
        
        $html = $this->_getHeaderHtml($element);
        
         
        $marketplaceCsvFile = Mage::getModuleDir( '', 'Profileolabs_Shoppingflux' ) . DS . 'etc' . DS . 'marketplaces.csv';
        $marketplaces = file($marketplaceCsvFile);
        
        
        
        $i = 1;
        foreach($marketplaces as $marketplace) {
            $this->_addCustomerGroupField($element, $marketplace, 10*$i++);
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


    protected function _addCustomerGroupField($fieldset, $marketplace, $sortOrder) {
        $customerGroup = strtolower(preg_replace('%[^a-zA-Z0-9_]%', '', $marketplace))."_group";
        $configData = $this->getConfigData();
        $path = 'shoppingflux_mo/import_customer/' . $customerGroup;
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
        $fieldset->addField($customerGroup, 'select', array(
                    'name' => 'groups[import_customer][fields][' . $customerGroup . '][value]',
                    'label' => Mage::helper('profileolabs_shoppingflux')->__('%s customer group', $this->_getNiceName($marketplace)),
                    'comment' => Mage::helper('profileolabs_shoppingflux')->__('Leave empty to use default group'),
                    'value' => $data,
                    'values' => Mage::getSingleton('adminhtml/system_config_source_customer_group')->toOptionArray(),
                    'sort_order' => $sortOrder,
                    'inherit' => $inherit,
                    'can_use_default_value' => 0,
                    'can_use_website_value' => 0,
                ))->setRenderer($this->_getFieldRenderer());

    }


}