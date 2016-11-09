<?php
/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Model_Export_Source_Attributesprice
{
    
    
    public function toOptionArray()
    {
       $model = Mage::getResourceModel('catalog/product');
        $typeId = $model->getTypeId();

        $attributesCollection = Mage::getResourceModel('eav/entity_attribute_collection')
                ->setEntityTypeFilter($typeId)
                ->load();
        $attributes = array();
        $attributes[] = array('value' => '', 'label' => '');
        foreach ($attributesCollection as $attribute) {
            if($attribute->getFrontendInput() == 'price') {
                $code = $attribute->getAttributeCode();
                $attributes[] = array('value' => $code, 'label' => $code);
            }
        }

        return $attributes;
    }
}
