<?php
/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_Order_LogController extends Mage_Adminhtml_Controller_Action
{
	protected function _initAction() {
		$this->loadLayout()
		->_setActiveMenu('shoppingflux/manageorders/log')
		->_addBreadcrumb(Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux orders log'), Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux orders log'));

		return $this;
	}
	
	
	public function indexAction()
	{
		$this->_initAction()
		->renderLayout();
		
		return $this;
	}
	
	public function deleteAction()
	{
		$collection = Mage::getModel('profileolabs_shoppingflux/manageorders_log')->getCollection();
		foreach($collection as $log)
			$log->delete();
			
		$this->_getSession()->addSuccess(Mage::helper('profileolabs_shoppingflux')->__("Log is empty."));
		
		$this->_redirect('*/*/index');
			
	}
	
	public function gridAction()
	{
		$this->getResponse()->setBody(
		$this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_log_grid')->toHtml()
		);
		
		return $this;
	}
         protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('shoppingflux/manageorders');
    }
	
}