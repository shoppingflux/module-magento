<?php
/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author Vincent Enjalbert
 */
class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Order_CronController extends Mage_Adminhtml_Controller_Action
{
	protected function _initAction() {
		$this->loadLayout()
		->_setActiveMenu('shoppingflux/manageorders/crons')
		->_addBreadcrumb(Mage::helper('profileolabs_shoppingflux')->__('Crons'), Mage::helper('profileolabs_shoppingflux')->__('Crons'));

		return $this;
	}
	
	
	public function indexAction()
	{
		$this->_initAction()
		->renderLayout();
		
		return $this;
	}
	
	
	
	public function gridAction()
	{
		$this->getResponse()->setBody(
		$this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_cron_grid')->toHtml()
		);
		
		return $this;
	}
	
         protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('shoppingflux');
    }
}