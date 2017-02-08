<?php
/**
 * Shopping Flux Service
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Order extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	public function __construct()
	{
	  parent::__construct();
	     
	    $this->_controller = 'manageorders_adminhtml_order';
	    $this->_blockGroup = 'profileolabs_shoppingflux';
	    $this->_headerText = $this->__('Shopping flux orders');
	
		$this->_removeButton('add');
	}
}