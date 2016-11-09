<?php
/**
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Block_Tracking_Roi extends Mage_Core_Block_Text
{

    
    protected function _toHtml()
    {
        $idTracking = Mage::getSingleton('profileolabs_shoppingflux/config')->getIdTracking();

        if (!$idTracking) {
            return '';
        }

        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId) {
            $order = Mage::getModel('sales/order')->load($orderId);
            if ($order->getId()) {
                $grandTotal = $order->getBaseGrandTotal();
         	$incrementId = $order->getIncrementId();
                $this->addText("
			<!-- BEGIN Shopping flux Tracking -->
			  <script>
                            var sf = sf || [];
                            sf.push(['" . $idTracking . "'], ['" . $incrementId . "'], ['" . $grandTotal . "']);

                            (function() {
                              var sf_script = document.createElement('script');
                              sf_script.src = 'https://tag.shopping-flux.com/async.js';
                              sf_script.setAttribute('async', 'true');
                              document.documentElement.firstChild.appendChild(sf_script);
                            })();
                          </script>
			<!-- END Shopping flux Tracking -->
			        ");
            }
        }
        return parent::_toHtml();
    }
}
