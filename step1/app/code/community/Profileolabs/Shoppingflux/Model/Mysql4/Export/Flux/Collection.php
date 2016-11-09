<?php

/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author vincent enjalbert @ web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Mysql4_Export_Flux_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
	protected function _construct()
	{
		$this->_init('profileolabs_shoppingflux/export_flux');
	}
}