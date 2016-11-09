<?php
/**
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Export_Shipments extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('profileolabs_shoppingflux/manageorders_export_shipments');
	}
        
        public function scheduleShipmentExport($shipmentId) {
            $this->setUpdateId(null)->setShipmentId($shipmentId)->setUpdatedAt(date('Y-m-d H:i:s'))->save();
            return $this;
        }
	
	
}