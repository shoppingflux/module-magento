<?php
/**
 * Shopping Flux Log grid container
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	public function __construct()
	{
	  parent::__construct();
	     
	    $this->_controller = 'manageorders_adminhtml_log';
	    $this->_blockGroup = 'profileolabs_shoppingflux';
	    $this->_headerText = $this->__('Shopping flux log');
	
		$this->_removeButton('add');
		$this->_addButton("deleteAll",array(
											'label'   => Mage::helper('profileolabs_shoppingflux')->__('Delete'),
								            'onclick' => "setLocation('{$this->getUrl('*/*/delete')}')",
								            'class'   => 'delete'
											));
	}
}