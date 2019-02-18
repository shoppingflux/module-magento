<?php

class Profileolabs_Shoppingflux_Model_Manageorders_Source_Attributes
{
    /**
     * @var array
     */
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

    /**
     * @var array|null
     */
    protected $_attributes = null;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        if ($this->_attributes === null) {
            $this->_attributes = array(array('value' => '', 'label' => ''));

            /** @var Mage_Customer_Model_Resource_Customer $customerResource */
            $customerResource = Mage::getResourceModel('customer/customer');
            $typeId = $customerResource->getTypeId();

            /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $attributeCollection */
            $attributeCollection = Mage::getResourceModel('eav/entity_attribute_collection');
            $attributeCollection->setEntityTypeFilter($typeId)->load();

            foreach ($attributeCollection as $attribute) {
                $code = $attribute->getAttributeCode();

                if (!in_array($code, $this->_exceptions)) {
                    $this->_attributes[] = array('value' => $code, 'label' => $code);
                }
            }
        }
        return $this->_attributes;
    }
}
