<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Product extends Mage_Catalog_Model_Product {
   
    public function getOptions() {
        return array();
    }
    
    public function getHasOptions() {
        return false;
    }
}