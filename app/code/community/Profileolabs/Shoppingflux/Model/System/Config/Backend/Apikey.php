<?php

/**
 * @deprecated
 * Shopping Flux Backend Model for Config Api key
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Model_System_Config_Backend_Apikey extends Mage_Core_Model_Config_Data {

    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }
    
    protected function _beforeSave() {
        parent::_beforeSave();

     /*
        if ($this->isValueChanged()) {
            $storeId = 0;
            switch($this->getScope()) {
                case 'stores':
                    $storeId    = $this->getScopeId();
                    break;
            }
            $apiKey = $this->getConfig()->getApiKey($storeId);
            $wsUri = $this->getConfig()->getWsUri();
            
            $service = new Profileolabs_Shoppingflux_Model_Service($apiKey, $wsUri);

            if ((boolean) $this->getFieldsetDataValue('enabled') && !$service->checkApiKey($this->getValue()))
                Mage::throwException(Mage::helper('profileolabs_shoppingflux')->__('API key (Token) not valid'));
        }
        
        */
    }

}