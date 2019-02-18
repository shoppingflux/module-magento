<?php

class Profileolabs_Shoppingflux_Model_Export_Convert_Parser_Product extends Mage_Catalog_Model_Convert_Parser_Product
{
    /**
     * @return array
     */
    public function getExternalAttributes()
    {
        $catalogModuleDir = Mage::getModuleDir(null, 'Mage_Catalog');

        if (file_exists($catalogModuleDir . 'Model/Resource/Eav/Mysql4/Product/Attribute/Collection')) {
            $productAttributes = Mage::getResourceModel('catalog/product_attribute_collection')->load();
        } else {
            /** @var Mage_Eav_Model_Config $eavConfig */
            $eavConfig = Mage::getSingleton('eav/config');
            $entityTypeId = $eavConfig->getEntityType('catalog_product')->getId();
            /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $productAttributes */
            $productAttributes = Mage::getResourceModel('eav/entity_attribute_collection');
            $productAttributes->setEntityTypeFilter($entityTypeId)->load();
        }

        $attributes = $this->_externalFields;

        foreach ($productAttributes as $attribute) {
            $code = $attribute->getAttributeCode();

            if (in_array($code, $this->_internalFields) || ($attribute->getFrontendInput() === 'hidden')) {
                continue;
            }

            $attributes[$code] = $code;
        }

        foreach ($this->_inventoryFields as $field) {
            $attributes[$field] = $field;
        }

        return $attributes;
    }
}
