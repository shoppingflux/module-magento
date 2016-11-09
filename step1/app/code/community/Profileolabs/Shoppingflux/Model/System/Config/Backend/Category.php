<?php

/**
 * @deprecated
 * Shopping Flux Backend Model for Config Api key
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Model_System_Config_Backend_Category extends Profileolabs_Shoppingflux_Model_System_Config_Backend_Refresh {

     public function getConfig() {
        return Mage::getSingleton('profileolabs_shoppingflux/config');
    }

    protected function _afterSave() {
        try {
            if ($this->isValueChanged()) {
                
                $categoryAttribute = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'shoppingflux_default_category');
                
                $write = Mage::getSingleton('core/resource')->getConnection('core_write');
                $write->beginTransaction();
                 try {
                     if($this->getValue()) {
                         $query = "update " . Mage::getSingleton('core/resource')->getTableName('eav/attribute') . " set source_model = 'profileolabs_shoppingflux/attribute_source_category' where attribute_id = '" . $categoryAttribute->getId() . "'";
                         $write->query($query);

                         $query = "update " . Mage::getSingleton('core/resource')->getTableName('catalog/eav_attribute') . " set is_visible = '1' where attribute_id = '" . $categoryAttribute->getId() . "'";
                         $write->query($query);
                     } else {
                         $query = "update " . Mage::getSingleton('core/resource')->getTableName('eav/attribute') . " set source_model = '' where attribute_id = '" . $categoryAttribute->getId() . "'";
                         $write->query($query);

                         $query = "update " . Mage::getSingleton('core/resource')->getTableName('catalog/eav_attribute') . " set is_visible = '0' where attribute_id = '" . $categoryAttribute->getId() . "'";
                         $write->query($query);
                     }
                     $write->commit();
                 } catch (Exception $e) {
                     $write->rollback();
                 }
                
            }
        } catch (Exception $e) {
            
        }
        parent::_afterSave();
    }


}