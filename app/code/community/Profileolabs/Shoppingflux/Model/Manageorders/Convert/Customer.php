<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Convert_Customer extends Varien_Object
{
    // See Mage_Directory setup.
    const SPAIN_REGION_PREFIX_TO_CODE_MAPPING = [
        '01' => 'Alava',
        '02' => 'Albacete',
        '03' => 'Alicante',
        '04' => 'Almeria',
        '05' => 'Avila',
        '06' => 'Badajoz',
        '07' => 'Baleares',
        '08' => 'Barcelona',
        '09' => 'Burgos',
        '10' => 'Caceres',
        '11' => 'Cadiz',
        '12' => 'Castellon',
        '13' => 'Ciudad Real',
        '14' => 'Cordoba',
        '15' => 'A Coruсa',
        '16' => 'Cuenca',
        '17' => 'Girona',
        '18' => 'Granada',
        '19' => 'Guadalajara',
        '20' => 'Guipuzcoa',
        '21' => 'Huelva',
        '22' => 'Huesca',
        '23' => 'Jaen',
        '24' => 'Leon',
        '25' => 'Lleida',
        '26' => 'La Rioja',
        '27' => 'Lugo',
        '28' => 'Madrid',
        '29' => 'Malaga',
        '30' => 'Murcia',
        '31' => 'Navarra',
        '32' => 'Ourense',
        '33' => 'Asturias',
        '34' => 'Palencia',
        '35' => 'Las Palmas',
        '36' => 'Pontevedra',
        '37' => 'Salamanca',
        '38' => 'Santa Cruz de Tenerife',
        '39' => 'Cantabria',
        '40' => 'Segovia',
        '41' => 'Sevilla',
        '42' => 'Soria',
        '43' => 'Tarragona',
        '44' => 'Teruel',
        '45' => 'Toledo',
        '46' => 'Valencia',
        '47' => 'Valladolid',
        '48' => 'Vizcaya',
        '49' => 'Zamora',
        '50' => 'Zaragoza',
        '51' => 'Ceuta',
        '52' => 'Melilla',
    ];

    /**
     * @param array $data
     * @param int $storeId
     * @param Mage_Customer_Model_Customer|null $customer
     * @return Mage_Customer_Model_Customer
     */
    public function toCustomer(array $data, $storeId, $customer = null)
    {
        $websiteId = Mage::app()->getStore($storeId)->getWebsiteId();

        if (!$customer instanceof Mage_Customer_Model_Customer) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = Mage::getModel('customer/customer');
            $customer->setWebsiteId($websiteId);
            $customer->loadByEmail($data['Email']);
            $customer->setImportMode(true);

            if (!$customer->getId()) {
                $customer->addData(
                    array(
                        'website_id' => $websiteId,
                        'confirmation' => null,
                        'force_confirmed' => true,
                        'password_hash' => $customer->hashPassword($customer->generatePassword(8)),
                        'from_shoppingflux' => 1,
                    )
                );
            }
        }

        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');
        $coreHelper->copyFieldset('shoppingflux_convert_customer', 'to_customer', $data, $customer);

        if (trim((string) $customer->getFirstname()) === '') {
            $customer->setFirstname('__');
        }

        return $customer;
    }

    /**
     * @param array $data
     * @param int $storeId
     * @param Mage_Customer_Model_Customer|null $customer
     * @param string $type
     * @return Mage_Customer_Model_Address
     */
    public function addresstoCustomer(array $data, $storeId, $customer = null, $type = 'billing')
    {
        if (!$customer instanceof Mage_Customer_Model_Customer) {
            /** @var Mage_Customer_Model_Customer $customer */
            $customer = $this->toCustomer($data, $storeId);
        }

        /** @var Mage_Customer_Model_Address $address */
        $address = Mage::getModel('customer/address');
        $address->setId(null);
        $address->setIsDefaultBilling(true);
        $address->setIsDefaultShipping(false);

        if ($type === 'shipping') {
            $address->setIsDefaultBilling(false);
            $address->setIsDefaultShipping(true);
        }

        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');
        /** @var Mage_Core_Helper_Data $coreHelper */
        $coreHelper = Mage::helper('core');
        /** @var Mage_Core_Helper_String $stringHelper */
        $stringHelper = Mage::helper('core/string');

        $coreHelper->copyFieldset('shoppingflux_convert_customer', 'to_customer_address', $data, $address);

        if (trim((string) $address->getFirstname()) === '') {
            $address->setFirstname(' __ ');
        }

        if (strpos(strtolower((string) $address->getCountryId()), 'france') !== false) {
            $address->setCountryId('FR');
        }

        if ((trim((string) $address->getTelephone()) === '') && $data['PhoneMobile']) {
            $address->setTelephone($data['PhoneMobile']);
        }

        /** @var Profileolabs_Shoppingflux_Model_Config $config */
        $config = Mage::getSingleton('profileolabs_shoppingflux/config');

        if ($data['PhoneMobile'] && $stringHelper->strlen(trim((string) $data['PhoneMobile'])) >= 9) {
            if ($mobilePhoneAttribute = $config->getMobilePhoneAttribute($storeId)) {
                $customer->setData($mobilePhoneAttribute, $data['PhoneMobile']);
            } elseif ($config->preferMobilePhone($storeId)) {
                $address->setTelephone($data['PhoneMobile']);
            }
        }

        $regionId = false;
        $regionCode = false;
        $isAddressRegionCode = false;
        $countryId = strtoupper((string) $address->getCountryId());

        if ($countryId === 'FR') {
            $postcode = str_pad($address->getPostcode(), 5, '0', STR_PAD_LEFT);
            $regionCode = $stringHelper->substr($postcode, 0, 2);

            if ($regionCode === '20') {
                // Specific treatment for Corsica postcodes / regions.
                if ('201' === $stringHelper->substr($postcode, 0, 3)) {
                    $regionCode = '2A';
                } else {
                    $regionCode = '2B';
                }
            }
        } elseif (in_array($countryId, array('CA', 'US'), true)) {
            $regionCode = trim((string) $data['Street2']);

            if (!preg_match('/^[a-z]{2}$/i', $regionCode)) {
                $regionCode = null;
            } else {
                $isAddressRegionCode = true;
            }
        } elseif ($countryId === 'ES') {
            $regionCode = $stringHelper->substr($address->getPostcode(), 0, 2);

            if (array_key_exists($regionCode, self::SPAIN_REGION_PREFIX_TO_CODE_MAPPING)) {
                $regionCode = self::SPAIN_REGION_PREFIX_TO_CODE_MAPPING[$regionCode];
            } else {
                $regionCode = null;
            }
        }

        if ($regionCode) {
            /** @var Mage_Directory_Model_Resource_Region_Collection $regionCollection */
            $regionCollection = Mage::getResourceModel('directory/region_collection');
            $regionCollection->addRegionCodeFilter($regionCode);
            $regionCollection->addCountryFilter($address->getCountry());

            if ($regionCollection->getSize() > 0) {
                $regionCollection->setCurPage(1);
                $regionCollection->setPageSize(1);
                $regionId = $regionCollection->getFirstItem()->getId();
            } else {
                $regionId = false;
            }
        }

        if ($isAddressRegionCode && !empty($regionId)) {
            $data['Street2'] = '';
        }

        $address->setStreet(array($data['Street1'], $data['Street2']));

        if ($regionId) {
            $address->setRegionId($regionId);
        } else {
            $address->setRegionId(182);
        }

        if ($limit = $config->getAddressLengthLimit($storeId)) {
            $truncatedStreet = array();

            foreach ($address->getStreet() as $streetRow) {
                if ($stringHelper->strlen($streetRow) > $limit) {
                    $truncatedStreet = array_merge(
                        $truncatedStreet,
                        $helper->truncateAddress($streetRow, $limit)
                    );
                } else {
                    $truncatedStreet[] = $streetRow;
                }
            }

            $address->setStreet($truncatedStreet);
        }

        return $address;
    }
}
