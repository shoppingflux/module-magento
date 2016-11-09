<?php
/**
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Export_Shipments extends Mage_Core_Model_Mysql4_Abstract
{
	protected function _construct()
	{
		$this->_init('profileolabs_shoppingflux/manageorders_export_shipments','update_id');
	}
}