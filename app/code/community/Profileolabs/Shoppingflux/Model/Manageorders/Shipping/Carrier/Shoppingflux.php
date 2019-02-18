<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Shipping_Carrier_Shoppingflux extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'shoppingflux';
    protected $_isFixed = true;

    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->isActive()) {
            return false;
        }

        /** @var Mage_Shipping_Model_Rate_Result $result */
        $result = Mage::getModel('shipping/rate_result');
        /** @var Mage_Shipping_Model_Rate_Result_Method $method */
        $method = Mage::getModel('shipping/rate_result_method');

        $method->setCarrier('shoppingflux');
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('shoppingflux');
        $method->setMethodTitle($this->getConfigData('name'));

        /** @var array $sfOrder */
        $sfOrder = Mage::registry('current_order_sf');
        /** @var Mage_Sales_Model_Quote $sfQuote */
        $sfQuote = Mage::registry('current_quote_sf');

        $shippingPrice = $sfOrder['TotalShipping'];

        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        /** @var Mage_Tax_Model_Calculation $taxCalculationModel */
        $taxCalculationModel = Mage::getSingleton('tax/calculation');

        if (!$taxHelper->shippingPriceIncludesTax() && $taxHelper->getShippingTaxClass(null)) {
            $percent = null;
            $dummyProduct = new Varien_Object();
            $taxClassId = $taxHelper->getShippingTaxClass(null);
            $dummyProduct->setTaxClassId($taxClassId);

            if (($percent === null) && $taxClassId) {
                $request = $taxCalculationModel->getRateRequest(
                    $sfQuote->getShippingAddress(),
                    $sfQuote->getBillingAddress(),
                    null,
                    null
                );

                $request->setProductClassId($taxClassId);
                $request->setCustomerClassId($sfQuote->getCustomerTaxClassId());
                $percent = $taxCalculationModel->getRate($request);

                if (($percent !== false) || ($percent !== null)) {
                    $shippingPrice = $shippingPrice - ($shippingPrice / (100 + $percent) * $percent);
                }
            }
        }

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);
        $result->append($method);

        return $result;
    }

    public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        return $this->isActive();
    }

    public function isActive()
    {
        return Mage::registry('is_shoppingfeed_import');
    }

    public function getAllowedMethods()
    {
        return array('shoppingflux' => $this->getConfigData('name'));
    }
}
