<?php

class Profileolabs_Shoppingflux_Model_Export_Source_Visibility
{
    public function toOptionArray()
    {
        $options = Mage_Catalog_Model_Product_Visibility::getOptionArray();
        $result = array();

        foreach ($options as $value => $label) {
            $result[] = array('label' => $label, 'value' => $value);
        }

        return $result;
    }
}
