<?php
/**
 * @category    ShoppingFlux
 *  @package    Profileolabs_Shoppingflux
 * @author kassim belghait
 */



class Profileolabs_Shoppingflux_Model_Manageorders_Payment_Method_Purchaseorder extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'shoppingflux_purchaseorder';
    //protected $_formBlockType = 'payment/form_purchaseorder';
    protected $_infoBlockType = 'profileolabs_shoppingflux/manageorders_payment_info_purchaseorder';

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return  Profileolabs_Shoppingflux_Model_Manageorders_Payment_Method_Purchaseorder
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $this->getInfoInstance()->setAdditionalData($data->getMarketplace());
        return $this;
    }
    
	/**
     * Check whether payment method can be used
     * TODO: payment method instance is not supposed to know about quote
     * @param Mage_Sales_Model_Quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if(Mage::registry('is_shoppingfeed_import')/*Mage::getSingleton('checkout/session')->getIsShoppingFlux()*/)
        	return true;
        return parent::isAvailable($quote);
    }
}
