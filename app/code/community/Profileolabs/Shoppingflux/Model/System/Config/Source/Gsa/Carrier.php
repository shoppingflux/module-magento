<?php

class Profileolabs_Shoppingflux_Model_System_Config_Source_Gsa_Carrier
{
    /**
     * @var array|null
     */
    static protected $_optionArray = null;

    /**
     * @return array
     */
    public function toOptionHash()
    {
        return array(
            'boxtal' => 'Boxtal',
            'bpost' => 'bpost',
            'chronopost' => 'Chronopost',
            'colis prive' => 'Colis Privé',
            'colissimo' => 'Colissimo',
            'cxt' => 'CXT',
            'deliv' => 'Deliv',
            'dhl' => 'DHL',
            'dpd' => 'DPD',
            'dynamex' => 'Dynamex',
            'ecourier' => 'eCourier',
            'emsy' => 'Emsy',
            'fedex' => 'FedEx',
            'geodis' => 'Geodis',
            'gls' => 'GLS',
            'google' => 'Google',
            'gsx' => 'GSX',
            'lasership' => 'Lasership',
            'mpx' => 'MPX',
            'ont' => 'Ont',
            'ontrac' => 'OnTrac',
            'uds' => 'UDS',
            'ups' => 'UPS',
            'usps' => 'USPS',
        );
    }

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if (null === self::$_optionArray) {
            self::$_optionArray = array();

            foreach ($this->toOptionHash() as $value => $label) {
                self::$_optionArray[] = array(
                    'value' => $value,
                    'label' => $label,
                );
            }
        }

        return self::$_optionArray;
    }
}
