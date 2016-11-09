<?php

/**
 * ShoppingFLux shipping model
 *
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author     kassim belghait
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Carrier_Shoppingflux
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    protected $_code = 'shoppingflux';
    protected $_isFixed = true;

    /**
     * FreeShipping Rates Collector
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->isActive()) {
            return false;
        }

        $result = Mage::getModel('shipping/rate_result');

        $method = Mage::getModel('shipping/rate_result_method');

        $method->setCarrier('shoppingflux');
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod('shoppingflux');
        $method->setMethodTitle($this->getConfigData('name'));

        $orderSf = Mage::registry('current_order_sf');
        $quoteSf = Mage::registry('current_quote_sf');
        $shippingPrice = $orderSf['TotalShipping'];
        if (!Mage::helper('tax')->shippingPriceIncludesTax() && Mage::helper('tax')->getShippingTaxClass(null)) {
            $percent = null;
            $pseudoProduct = new Varien_Object();
            $pseudoProduct->setTaxClassId(Mage::helper('tax')->getShippingTaxClass(null));

            $taxClassId = $pseudoProduct->getTaxClassId();
            if (is_null($percent)) {
                if ($taxClassId) {
                    $request = Mage::getSingleton('tax/calculation')->getRateRequest($quoteSf->getShippingAddress(), $quoteSf->getBillingAddress(), null, null);
                    $request->setProductClassId($taxClassId);
                    $request->setCustomerClassId($quoteSf->getCustomerTaxClassId());
                    $percent = Mage::getSingleton('tax/calculation')->getRate($request);

                    if ($percent !== false || !is_null($percent)) {

                        $shippingPrice = $shippingPrice - ($shippingPrice / (100 + $percent) * $percent);
                    }
                }
            }

            //Mage::log("including tax = ".$includingTax." shipping price = ".$shippingPrice,null,'test_shipping_price.log');
        }
        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $result->append($method);
        

        return $result;
    }
    
    /**
    * Processing additional validation to check is carrier applicable.
    *
    * @param Mage_Shipping_Model_Rate_Request $request
    * @return Mage_Shipping_Model_Carrier_Abstract|Mage_Shipping_Model_Rate_Result_Error|boolean
    */
    public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request)
    {
    	//if(Mage::getVersion() == '1.4.1.0')
    	return $this->isActive();
    	 
    	//return parent::proccessAdditionalValidation($request);
    }
    
    public function getSession()
    {
    	return Mage::getSingleton('checkout/session');
    }
    
	public function isActive()
    {
       if(Mage::registry('is_shoppingfeed_import')/*$this->getSession()->getIsShoppingFlux()*/)
       	return true;
       	
       return false;
    }

    public function getAllowedMethods()
    {
        return array('shoppingflux'=>$this->getConfigData('name'));
    }

}
