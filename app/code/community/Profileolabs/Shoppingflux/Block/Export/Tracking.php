<?php
/**
 * Profileolabs_Shoppingflux Tracking Block
 *
 * @category   Profileolabs
 * @package    Profileolabs_Shoppingflux
 * @author     kassim belghait <kassim@profileo.com>
 * @deprecated since version 0.5.6
 */
class Profileolabs_Shoppingflux_Block_Export_Tracking extends Mage_Core_Block_Text
{

    /**
     * Retrieve Shopping flux Account Identifier
     *
     * @return string
     */
    public function getLogin()
    {
        if (!$this->hasData('login')) {
            $this->setLogin(Mage::getStoreConfig('shoppingflux_export/general/login'));
        }
        return $this->getData('login');
    }

    /**
     * Prepare and return block's html output
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!Mage::getStoreConfigFlag('shoppingflux_export/general/active')) {
            return '';
        }
        
        if($this->getLogin() == "")
			return '';
        
        $quoteId = Mage::getSingleton('checkout/session')->getLastQuoteId();
        if(!$quoteId)
        	return '';

        $quote = Mage::getModel('sales/quote')->load($quoteId);
        
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addAttributeToFilter('quote_id', $quoteId)
            ->load();
            
         foreach($orders as $order)
         { 
         	$grandTotal = $order->getBaseGrandTotal();
         	$incrementId = $order->getIncrementId();
         	
			        $this->addText('
			<!-- BEGIN Shopping flux Tracking -->
			<script type="text/javascript">
			//<![CDATA[
			document.write(\'<img src="http://www.shopping-flux.com/tracking/?cl='.$this->getLogin().'&mt='.$grandTotal.'&cmd='.$incrementId.'" />\');//]]>
			</script>
			<!-- END Shopping flux Tracking -->
			        ');

         }

        return parent::_toHtml();
    }
}
