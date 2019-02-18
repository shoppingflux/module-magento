<?php

class Profileolabs_Shoppingflux_Model_Mysql4_Export_Updates extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('profileolabs_shoppingflux/export_updates', 'update_id');
    }
}
