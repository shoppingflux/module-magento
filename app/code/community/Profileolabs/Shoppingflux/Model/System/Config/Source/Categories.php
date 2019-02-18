<?php

class Profileolabs_Shoppingflux_Model_System_Config_Source_Categories
{
    static protected $_optionArray = null;

    public function toOptionArray()
    {
        if (self::$_optionArray === null) {
            self::$_optionArray = array();
            $categories = Mage::helper('profileolabs_shoppingflux')
                ->getCategoriesWithParents('name', Mage_Core_Model_App::ADMIN_STORE_ID);

            foreach ($categories as $id => $name) {
                self::$_optionArray[] = array(
                    'value' => $id,
                    'label' => $name,
                );
            }
        }
        return self::$_optionArray;
    }
}
