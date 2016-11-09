<?php
/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Model_Export_Source_Visibility
{
    
   
    public function toOptionArray()
    {
       $options =  Mage_Catalog_Model_Product_Visibility::getOptionArray();
       $result = array();
       foreach ($options as $optionValue=>$optionLabel) {
           $result[] = array('label'=>$optionLabel, 'value'=>$optionValue);
       }
       return $result;
    }
}
