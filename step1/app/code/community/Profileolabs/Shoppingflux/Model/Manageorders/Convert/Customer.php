<?php

/**
 * @category ShoppingFlux
 * @package  Profileolabs_Shoppingflux_Model_Manageorders
 * @author kassim belghait, vincent enjalbert @ web-cooking.net
 *
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Convert_Customer extends Varien_Object {

    /**
     * Convert xml node to customer model
     *
     * @param   array $data
     * @return  Mage_Customer_Model_Customer
     */
    public function toCustomer(array $data, $storeId, $customer = null) {
        /* @var $customer Mage_Customer_Model_Customer */
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();
        if (!($customer instanceof Mage_Customer_Model_Customer)) {
            $customer = Mage::getModel('customer/customer')->setWebsiteId($websiteId)
                    ->loadByEmail($data['Email']);
            $customer->setImportMode(true);

            if (!$customer->getId()) {
                $customer->setWebsiteId($websiteId);
                $customer->setConfirmation(null);
                $customer->setForceConfirmed(true);
                $customer->setPasswordHash($customer->hashPassword($customer->generatePassword(8)));
                $customer->setFromShoppingflux(1);
            }
        }

        Mage::helper('core')->copyFieldset('shoppingflux_convert_customer', 'to_customer', $data, $customer);
        if ($customer->getFirstname() == "")
            $customer->setFirstname('__');
      
        

        return $customer;
    }

    /**
     * Convert xml node to customer address model
     *
     * @param   array $data
     * @return  Mage_Customer_Model_Address
     */
    public function addresstoCustomer(array $data, $storeId, $customer = null, $type = 'billing') {
        /* @var $customer Mage_Customer_Model_Customer */
        if (!($customer instanceof Mage_Customer_Model_Customer)) {

            $customer = $this->toCustomer($data, $storeId);
        }

        /* @var $address Mage_Customer_Model_Address */
        $address = Mage::getModel('customer/address');
        $address->setId(null);
        $address->setIsDefaultBilling(true);
        $address->setIsDefaultShipping(false);
        if ($type == "shipping") {
            $address->setIsDefaultBilling(false);
            $address->setIsDefaultShipping(true);
        }

        Mage::helper('core')->copyFieldset('shoppingflux_convert_customer', 'to_customer_address', $data, $address);

        if ($address->getFirstname() == "")
            $address->setFirstname(' __ ');

        if (strpos(strtolower($address->getCountryId()), 'france') !== false)
            $address->setCountryId('FR');
        
        if ($address->getTelephone() == "" && $data['PhoneMobile'])
            $address->setTelephone($data['PhoneMobile']);
        
        $address->setStreet(array($data['Street1'], $data['Street2']));
        
        if($data['PhoneMobile'] && strlen(trim($data['PhoneMobile'])) >= 9) {
            if(Mage::getSingleton('profileolabs_shoppingflux/config')->getMobilePhoneAttribute($storeId)) {
                $customer->setData(Mage::getSingleton('profileolabs_shoppingflux/config')->getMobilePhoneAttribute(), $data['PhoneMobile']);
            } else if(Mage::getSingleton('profileolabs_shoppingflux/config')->preferMobilePhone($storeId)) {
                $address->setTelephone($data['PhoneMobile']);
            }
        }

        $codeRegion = substr(str_pad($address->getPostcode(), 5, "0", STR_PAD_LEFT), 0, 2);

        //$regionId = Mage::getModel('directory/region')->loadByCode($codeRegion,$address->getCountry())->getId();
        $regionId = Mage::getModel('directory/region')->getCollection()
                ->addRegionCodeFilter($codeRegion)
                ->addCountryFilter($address->getCountry())
                ->getFirstItem()
                ->getId();

        if ($regionId)
            $address->setRegionId($regionId);
        else
            $address->setRegionId(182); //Ain pour le pays FR


        if(Mage::getSingleton('profileolabs_shoppingflux/config')->getAddressLengthLimit($storeId)) {
            $limit = Mage::getSingleton('profileolabs_shoppingflux/config')->getAddressLengthLimit($storeId);
            $streets = $address->getStreet();
            $nStreets = array();
            foreach($streets as $street) {
                if(strlen($street) > $limit) {
                    $nStreets = array_merge($nStreets, Mage::helper('profileolabs_shoppingflux')->truncateAddress($street, $limit));
                } else {
                    $nStreets[] = $street;
                }
            }
            $address->setStreet($nStreets);
        }
        return $address;
    }

}