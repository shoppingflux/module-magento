<?php
/**
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Method extends Mage_Core_Model_Abstract
{
	protected function _construct()
	{
		$this->_init('profileolabs_shoppingflux/manageorders_shipping_method');
	}
        
        
	
        public function getFullShippingMethodCodeFor($marketplace, $shippingMethod) {
            $code = $marketplace . '_' . $shippingMethod;
            $code = strtolower($code);
            $code = preg_replace('%\s+%i', '_', $code);
            return $code;
        }
	
        public function getFullShippingMethodCode() {
            return $this->getFullShippingMethodCodeFor($this->getMarketplace(), $this->getShippingMethod());
        }
        
        public function loadByMethod($marketplace, $shippingMethod) {
            $collection = $this->getCollection();
            $collection->addFieldToFilter('marketplace', $marketplace);
            $collection->addFieldToFilter('shipping_method', $shippingMethod);
            if($collection->count() <= 0) {
                $this->setId(null);
                $this->setMarketplace($marketplace);
                $this->setShippingMethod($shippingMethod);
                return $this;
            }
            return $collection->getFirstItem();
        }
        
        public function saveShippingMethod($marketplace, $shippingMethod) {
            $model = $this->loadByMethod($marketplace, $shippingMethod);
            $model->setLastSeenAt(date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time())));
            $model->save();
        }
	
}