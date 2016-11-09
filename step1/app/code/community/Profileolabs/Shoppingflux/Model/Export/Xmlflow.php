<?php
// V1 DEPRECATED
class Profileolabs_Shoppingflux_Model_Export_Xmlflow {

   
    public function __construct() {
        
    }

    public function _addEntries($entries) {
        foreach($entries as $entry) {
            $this->_addEntry($entry);
        }
    }

    public function _addEntry($entry) {
        echo "<product>" . chr(10);
        echo $this->arrayToNode($entry);
        echo "</product>" . chr(10);
    }

   
    public function getVersion() {
        return Mage::getConfig()->getModuleConfig("Profileolabs_Shoppingflux")->version;
    }

    public function startXml($size) {
        echo '<?xml version="1.0" encoding="utf-8"?>' . chr(10);
        echo '<products version="' . $this->getVersion() . '">' . chr(10);
    }

    public function endXml() {
        echo "</products>" . chr(10);
    }

    protected $_attributes = array();
    protected function _getAttribute($attributeCode) {
        if(!isset($this->_attributes[$attributeCode])) {
            $this->_attributes[$attributeCode] = Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeCode);
        }
        return $this->_attributes[$attributeCode];
    }
    
    public function extractData($nameNode, $attributeCode, $product) {

        $_helper = Mage::helper('catalog/output');

        $data = $product->getData($attributeCode);

        $attribute = $this->_getAttribute($attributeCode);
        if ($attribute) {
            $data = $attribute->getFrontend()->getValue($product);
            $data = $_helper->productAttribute($product, $data, $attributeCode);

            if ($nameNode == 'ecotaxe' && $attribute->getFrontendInput() == 'weee') {
                $weeeAttributes = Mage::getSingleton('weee/tax')->getProductWeeeAttributes($product);

                foreach ($weeeAttributes as $wa) {
                    if ($wa->getCode() == $attributeCode) {
                        $data = round($wa->getAmount(), 2);
                        break;
                    }
                }
            }
        }




        //$_helper = Mage::helper('catalog/output');
        //if($nameNode == 'description' || $nameNode == 'short_description')
        //$data = $_helper->productAttribute($product, $data, $attributeCode);	
        //Synthetize it
        /* $method = "get".ucfirst($attributeCode);
          if(method_exists($product,$method))
          $data = $product->$method(); */

        //TODO remove this
        if ($data == "No" || $data == "Non")
            $data = "";

        //Exceptions data
        if ($nameNode == 'shipping_delay' && empty($data))
            $data = $this->getConfig()->getConfigData('shoppingflux_export/general/default_shipping_delay');

        if ($nameNode == 'quantity')
            $data = round($data);

        return $data;
    }

    /**
     * Get singleton config for Export
     * @return Profileolabs_Shoppingflux_Model_Export_Config
     */
    public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    protected function arrayToNode($entry) {
        $node = "";

        foreach ($entry as $key => $value) {

            if (is_array($value)) {
                if (is_string($key))
                    $node.= $this->getNode($key, $this->arrayToNode($value), 0);
                elseif (is_string(($subKey = current($value))))
                    $node.= $this->getNode($subKey, $this->arrayToNode($value), 0);
                else
                    $node.= $this->arrayToNode($value);
            }
            else
                $node .= $this->getNode($key, $value);
        }


        return $node;
    }

    protected function getNode($name, $value, $withCDATA = 1) {
        $value = $this->getHelper()->cleanNotUtf8($value);
        $openCDATA = "";
        $closeCDATA = "";
        if ($withCDATA) {
            $openCDATA = "<![CDATA[";
            $closeCDATA = "]]>";
        }
        return "<{$name}>{$openCDATA}{$value}{$closeCDATA}</{$name}>" . chr(10);
    }

    /**
     * Return Shoppingflu Helper
     * @return Profileolabs_Shoppingflux_Helper_Data
     */
    protected function getHelper() {
        return Mage::helper('profileolabs_shoppingflux');
    }

}