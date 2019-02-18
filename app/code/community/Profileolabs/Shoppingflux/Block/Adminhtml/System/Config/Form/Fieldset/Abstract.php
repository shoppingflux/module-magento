<?php

class Profileolabs_Shoppingflux_Block_Adminhtml_System_Config_Form_Fieldset_Abstract extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * @var Mage_Adminhtml_Block_System_Config_Form_Field|null
     */
    protected $_fieldRenderer = null;

    /**
     * @var Varien_Object|null
     */
    protected $_dummyElement = null;

    /**
     * @return Profileolabs_Shoppingflux_Model_Config
     */
    public function getConfig()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');
        return $helper->getConfig();
    }

    /**
     * @return bool
     */
    public function shouldRenderUnregistered()
    {
        $storeCode = Mage::app()->getRequest()->getParam('store', null);
        $store = Mage::app()->getStore($storeCode);
        $apiKey = $this->getConfig()->getApiKey($store->getId());
        $wsUri = $this->getConfig()->getWsUri();
        $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);
        return !$service->isClient();
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->shouldRenderUnregistered() ? $this->renderUnregistered($element) : parent::render($element);
    }

    /**
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function renderUnregistered(Varien_Data_Form_Element_Abstract $element)
    {
        if (Mage::registry('shoppingflux_unregistered_block')) {
            return '';
        }

        /** @var Mage_Adminhtml_Block_Template $registerBlock */
        $registerBlock = Mage::app()->getLayout()->createBlock('adminhtml/template');
        $registerBlock->setTemplate('profileolabs/shoppingflux/register.phtml');
        Mage::register('shoppingflux_unregistered_block', true);

        return $registerBlock->toHtml();
    }

    /**
     * @return Mage_Adminhtml_Block_System_Config_Form_Field
     */
    protected function _getFieldRenderer()
    {
        if ($this->_fieldRenderer === null) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }

        return $this->_fieldRenderer;
    }

    /**
     * @return Varien_Object
     */
    protected function _getDummyElement()
    {
        if ($this->_dummyElement === null) {
            $this->_dummyElement = new Varien_Object(
                array(
                    'show_in_default' => 0,
                    'show_in_website' => 0,
                    'show_in_store' => 1,
                )
            );
        }

        return $this->_dummyElement;
    }
}
