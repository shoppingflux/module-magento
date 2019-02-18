<?php

class Profileolabs_Shoppingflux_Model_Catalog_Resource_Eav_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    public function getSourceModel()
    {
        return ($this->getAttributeCode() == 'shoppingflux_default_category')
            ? 'profileolabs_shoppingflux/attribute_source_category'
            : parent::getSourceModel();
    }
}
