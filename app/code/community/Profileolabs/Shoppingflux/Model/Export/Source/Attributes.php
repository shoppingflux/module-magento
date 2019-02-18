<?php

class Profileolabs_Shoppingflux_Model_Export_Source_Attributes
{
    /**
     * @var array
     */
    protected $_exceptions = array(
        'store',
        'websites',
        'attribute_set',
        'type',
        'news_from_date',
        'news_to_date',
        'gallery',
        'media_gallery',
        'url_key',
        'url_path',
        'minimal_price',
        'custom_design',
        'custom_design_from',
        'custom_design_to',
        'category_ids',
        'options_container',
        'required_options',
        'has_options',
        'tax_class_id',
        'custom_layout_update',
        'page_layout',
        'recurring_profile',
        'is_recurring',
        'is_in_stock',
        'qty'
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

            /** @var Profileolabs_Shoppingflux_Model_Export_Convert_Parser_Product $productParser */
            $productParser = Mage::getSingleton('profileolabs_shoppingflux/export_convert_parser_product');

            foreach ($productParser->getExternalAttributes() as $key => $value) {
                if (!in_array($key, $this->_exceptions)) {
                    $this->_attributes[] = array('value' => $key, 'label' => $value);
                }
            }
        }

        return $this->_attributes;
    }
}
