<?php

/**
 * Shopping Flux   Block for category page to assiocate products.
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Widget_Grid_Column_Renderer_Bool
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected $_defaultWidth = 55;
    /**
     * Renders grid column
     *
     * @param   Varien_Object $row
     * @return  string
     */
    public function render(Varien_Object $row)
    {
        $values = $this->getColumn()->getValues();
        $value  = $row->getData($this->getColumn()->getIndex());
        if (is_array($values)) {
            $checked = in_array($value, $values);
        }
        else {
            $checked = ($value === $this->getColumn()->getValue());
        }

        return $checked?Mage::helper('profileolabs_shoppingflux')->__('Yes'):Mage::helper('profileolabs_shoppingflux')->__('No');
    }

}
