<?php
class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Import extends Mage_Adminhtml_Block_Template
{

    
	public function getImportOrdersUrl()
    {
    	return $this->getUrl('*/*/importOrders');
    }

    
}
