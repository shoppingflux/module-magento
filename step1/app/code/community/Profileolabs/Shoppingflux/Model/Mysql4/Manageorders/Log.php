<?php
/**
 * Error Log
 * @category ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 *
 */
class Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Log extends Mage_Core_Model_Mysql4_Abstract
{
	protected function _construct()
	{
		$this->_init('profileolabs_shoppingflux/manageorders_log','id');
	}
}