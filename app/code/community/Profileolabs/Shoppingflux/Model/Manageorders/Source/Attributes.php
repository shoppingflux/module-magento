<?php

/**
 * Shopping Flux
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait - Vincent Enjalbert
 */
class Profileolabs_Shoppingflux_Model_Manageorders_Source_Attributes {

    protected $_exceptions = array(
        'website_id',
        'store_id',
        'created_in',
        'prefix',
        'suffix',
        'firstname',
        'middlename',
        'lastname',
        'email',
        'group_id',
        'dob',
        'password_hash',
        'default_shipping',
        'default_billing',
        'taxvat',
        'confirmation',
        'created_at',
        'gender',
        'rp_token',
        'rp_token_created_at',
        'disable_auto_group_change',
        'from_shoppingflux',
    );
    protected $_attributes = null;

    public function toOptionArray() {
        if (is_null($this->_attributes)) {
            $this->_attributes = array();

            $model = Mage::getResourceModel('customer/customer');
            $typeId = $model->getTypeId();

            $attributesCollection = Mage::getResourceModel('eav/entity_attribute_collection')
                    ->setEntityTypeFilter($typeId)
                    ->load();
            $this->_attributes = array();
            $this->_attributes[] = array('value' => '', 'label' => '');
            foreach ($attributesCollection as $attribute) {
                $code = $attribute->getAttributeCode();
                if (!in_array($code, $this->_exceptions)) {
                    $this->_attributes[] = array('value' => $code, 'label' => $code);
                }
            }
        }
        return $this->_attributes;
    }

}
