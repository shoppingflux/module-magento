<?php
      
class Profileolabs_Shoppingflux_Model_Catalog_Resource_Eav_Attribute extends Mage_Eav_Model_Entity_Attribute {

    public function getSourceModel() {
        if($this->getAttributeCode() == 'shoppingflux_default_category') {
            return 'profileolabs_shoppingflux/attribute_source_category';
        }
        return parent::getSourceModel();
    }

}