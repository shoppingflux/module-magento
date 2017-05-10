<?php
/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author kassim belghait
 */
class Profileolabs_Shoppingflux_Adminhtml_Shoppingfeed_OrderController extends Mage_Adminhtml_Controller_Action
{
	protected function _initAction() {
		$this->loadLayout()
		->_setActiveMenu('shoppingflux/manageorders/order')
		->_addBreadcrumb(Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux orders'), Mage::helper('profileolabs_shoppingflux')->__('ShoppingFlux orders'));

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
		$this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_order_grid')->toHtml()
		);
		
		return $this;
	}
	
 	/**
     * Export order grid to CSV format
     */
    public function exportCsvAction()
    {
        $fileName   = 'orders_shoppingflux.csv';
        $grid       = $this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_order_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getCsvFile());
    }

    /**
     *  Export order grid to Excel XML format
     */
    public function exportExcelAction()
    {
        $fileName   = 'orders_shoppingflux.xml';
        $grid       = $this->getLayout()->createBlock('profileolabs_shoppingflux/manageorders_adminhtml_order_grid');
        $this->_prepareDownloadResponse($fileName, $grid->getExcelFile($fileName));
    }
	
     protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('shoppingflux/manageorders');
    }
	
}