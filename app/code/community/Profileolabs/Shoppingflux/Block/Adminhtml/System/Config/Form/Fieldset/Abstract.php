<?php

class Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

    
     public function getConfig() {
        return Mage::helper('profileolabs_shoppingflux')->getConfig();
    }
    
    public function shouldRenderUnregistered() {
        $storeCode = Mage::app()->getRequest()->getParam('store', null);
        $store = Mage::app()->getStore($storeCode);
        $apiKey = $this->getConfig()->getApiKey($store->getId());
        $wsUri = $this->getConfig()->getWsUri($store->getId());
        $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
        return $service->isClient();      
    }
    
    
    public function render(Varien_Data_Form_Element_Abstract $element) {
        if($this->shouldRenderUnregistered()) {
            return parent::render($element);
        }
        
        return $this->renderUnregistered($element);
    }
    
    public function renderUnregistered(Varien_Data_Form_Element_Abstract $element) {
        
      if(Mage::registry('shoppingflux_unregistered_block')) {
        return '';
      }
      $block = Mage::app()->getLayout()->createBlock('adminhtml/template');
      $block->setTemplate('profileolabs/shoppingflux/register.phtml');

      Mage::register('shoppingflux_unregistered_block', true);
      return $block->toHtml();
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
    

}