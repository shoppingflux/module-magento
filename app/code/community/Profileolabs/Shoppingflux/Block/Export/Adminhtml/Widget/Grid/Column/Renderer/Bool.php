<?php

class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Widget_Grid_Column_Renderer_Bool extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @var int
     */
    protected $_defaultWidth = 55;

    public function render(Varien_Object $row)
    {
        $values = $this->getColumn()->getValues();
        $value = $row->getData($this->getColumn()->getIndex());

        if (is_array($values)) {
            $checked = in_array($value, $values);
        } else {
            $checked = ($value === $this->getColumn()->getValue());
        }

        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');
        return $checked ? $helper->__('Yes') : $helper->__('No');
    }

}
