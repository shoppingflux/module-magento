<?php

class Profileolabs_Shoppingflux_Model_Export_Source_Attributesprice
{
    /**
     * @var array|null
     */
    protected $_attributes = null;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->_attributes === null) {
            $this->_attributes = array(array('value' => '', 'label' => ''));

            /** @var Mage_Catalog_Model_Resource_Product $productResource */
            $productResource = Mage::getResourceModel('catalog/product');
            $typeId = $productResource->getTypeId();

            /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $attributeCollection */
            $attributeCollection = Mage::getResourceModel('eav/entity_attribute_collection');
            $attributeCollection->setEntityTypeFilter($typeId)->load();

            foreach ($attributeCollection as $attribute) {
                if ($attribute->getFrontendInput() == 'price') {
                    $code = $attribute->getAttributeCode();
                    $this->_attributes[] = array('value' => $code, 'label' => $code);
                }
            }
        }

        return $this->_attributes;
    }
}
