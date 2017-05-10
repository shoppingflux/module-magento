<?php
/**
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Block_Tracking_Buyline extends Mage_Core_Block_Text {

    protected function _toHtml() {
        $idTracking = Mage::getSingleton('profileolabs_shoppingflux/config')->getIdTracking();

        if (!$idTracking) {
            return '';
        }
        
        if(Mage::getSingleton('profileolabs_shoppingflux/config')->isBuylineEnabled()) {
                $this->addText("
                            <!-- BEGIN Shopping flux Tracking -->
                            <script type=\"text/javascript\">
                                var sf2 = sf2 || [];
                                sf2.push(['".$idTracking."'],[escape(document.referrer)]);
                                (function() {
                                var sf_script = document.createElement('script');
                                sf_script.src = ('https:' == document.location.protocol ? 'https://' : 'http://') + 'tag.shopping-feed.com/buyline.js';
                                sf_script.setAttribute('async', 'true');
                                document.documentElement.firstChild.appendChild(sf_script);
                                })();
                            </script>
                            <!-- END Shopping flux Tracking -->
                                    ");
            /*$this->addText('
                            <!-- BEGIN Shopping flux Tracking -->
                              <script type="text/javascript" src="http://tracking.shopping-flux.com/gg.js"></script>
                            <!-- END Shopping flux Tracking -->
                                    ');*/
        }
        return parent::_toHtml();
    }

}
