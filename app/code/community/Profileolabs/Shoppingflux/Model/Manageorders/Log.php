<?php
/**
 * Error Log
 * @category ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 *
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Log extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('profileolabs_shoppingflux/manageorders_log');
	}
	
	/**
	 * Save message event
	 * @param $message string
	 * @param $orderId int
	 */
	public function log($message,$orderId = null)
	{
		$orderMessage ="";
		if(!is_null($orderId))
		{
			$orderMessage = Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux Order ID : #%s (store %s)', $orderId, Mage::app()->getStore()->getId());
		}	

		$message = $orderMessage.' / '.$message;
		
		$this->setMessage($message);
		
		return $this->save();
	}
	
}