<?php

class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Tab_Default extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('shoppingflux_default_category_products');
        $this->setDefaultSort('entity_id');
        $this->setUseAjax(true);
    }

    /**
     * @return Mage_Catalog_Model_Category
     */
    public function getCategory()
    {
        return Mage::registry('category');
    }

    protected function _afterToHtml($html)
    {
        $html = parent::_afterToHtml($html);

        if ($this->getRequest()->getActionName() !== 'edit') {
            return $html;
        }

        /** @var Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Edit_Form $formBlock */
        $formBlock = $this->getLayout()
            ->createBlock(
                'profileolabs_shoppingflux/export_adminhtml_catalog_category_edit_form',
                'category.edit.shoppingflux'
            );

        return $html
            . $formBlock->toHtml()
            . '<input type="hidden" value="" '
            . 'name="shoppingflux_category_products" id="shoppingflux_in_category_products" />';
    }

    /**
     * @return int[]
     */
    protected function _getSelectedProducts()
    {
        $products = $this->getRequest()->getPost('sf_selected_products');

        if ($products === null) {
            /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
            $collection = Mage::getResourceModel('catalog/product_collection');
            $collection->setStore($this->getRequest()->getParam('store'));
            $collection->setStoreId($this->getRequest()->getParam('store'));
            $collection->addAttributeToFilter('shoppingflux_default_category', $this->getCategory()->getId());
            $collection->addStoreFilter($this->getRequest()->getParam('store'));
            $products = $collection->getAllIds();
        }

        return $products;
    }

    /**
     * @return int[]
     */
    protected function _getInCategoryProducts()
    {
        $products = $this->getRequest()->getPost('selected_products');

        if ($products === null) {
            $products = array_keys($this->getCategory()->getProductsPosition());
        }

        return $products;
    }

    protected function _addColumnFilterToCollection($column)
    {
        /** @var Mage_Adminhtml_Block_Widget_Grid_Column $column */

        if ($column->getId() === 'in_shoppingflux_category') {
            $productIds = $this->_getSelectedProducts();

            if (empty($productIds)) {
                $productIds = array(0);
            }
        } else if ($column->getId() === 'is_in_category') {
            $productIds = $this->_getInCategoryProducts();

            if (empty($productIds)) {
                $productIds = array(0);
            }
        }

        if (!empty($productIds)) {
            if ($column->getFilter()->getValue()) {
                $this->getCollection()->addFieldToFilter('entity_id', array('in' => $productIds));
            } elseif (!empty($productIds)) {
                $this->getCollection()->addFieldToFilter('entity_id', array('nin' => $productIds));
            }
        } else {
            parent::_addColumnFilterToCollection($column);
        }

        return $this;
    }

    protected function _prepareCollection()
    {
        if ($this->getCategory()->getId()) {
            $this->setDefaultFilter(array('in_shoppingflux_category' => 1));
        }

        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setStore($this->getRequest()->getParam('store'));
        $collection->addAttributeToSelect(array('name', 'sku', 'price', 'shoppingflux_product'));

        $collection->addExpressionAttributeToSelect(
            'shoppingflux_default_category',
            'IF({{shoppingflux_default_category}}, {{shoppingflux_default_category}}, -1)',
            'shoppingflux_default_category'
        );

        $collection->addStoreFilter($this->getRequest()->getParam('store'));
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->addColumn(
            'in_shoppingflux_category',
            array(
                'header_css_class' => 'a-center',
                'type' => 'checkbox',
                'name' => 'in_shoppingflux_category',
                'values' => $this->_getSelectedProducts(),
                'align' => 'center',
                'index' => 'entity_id'
            )
        );

        $this->addColumn(
            'is_in_category',
            array(
                'header' => $helper->__('In category ?'),
                'type' => 'bool',
                'name' => 'is_in_category',
                'values' => $this->_getInCategoryProducts(),
                'align' => 'center',
                'index' => 'entity_id',
                'filter' => 'adminhtml/widget_grid_column_filter_checkbox',
            )
        );

        $this->addColumn(
            'entity_id',
            array(
                'header' => $helper->__('ID'),
                'sortable' => true,
                'width' => '60',
                'index' => 'entity_id',
            )
        );
        $this->addColumn(
            'name',
            array(
                'header' => $helper->__('Name'),
                'index' => 'name',
            )
        );
        $this->addColumn(
            'sku',
            array(
                'header' => $helper->__('SKU'),
                'width' => '80',
                'index' => 'sku',
            )
        );
        $this->addColumn(
            'price',
            array(
                'header' => $helper->__('Price'),
                'type' => 'currency',
                'width' => '1',
                'currency_code' => (string) Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE),
                'index' => 'price',
            )
        );

        $optionsCategories = $helper->getCategoriesWithParents('name');
        $optionsCategories = array_reverse($optionsCategories, true);
        $optionsCategories[-1] = '__';
        $optionsCategories = array_reverse($optionsCategories, true);

        $this->addColumn(
            'shoppingflux_default_category',
            array(
                'header' => $helper->__('Current default category'),
                'width' => '80',
                'type' => 'options',
                'options' => $optionsCategories,
                'index' => 'shoppingflux_default_category',
            )
        );

        $yesNoOptions = array(
            0 => $helper->__('No'),
            1 => $helper->__('Yes')
        );

        $this->addColumn(
            'shoppingflux_product',
            array(
                'header' => $helper->__('Send to Shoppingflux ?'),
                'width' => '80',
                'index' => 'shoppingflux_product',
                'type' => 'options',
                'options' => $yesNoOptions,
            )
        );
        return parent::_prepareColumns();
    }

    public function getGridUrl()
    {
        return $this->getUrl('adminhtml/shoppingfeed_export_category/grid', array('_current' => true));
    }

    public function getColumnRenderers()
    {
        return array('bool' => 'profileolabs_shoppingflux/export_adminhtml_widget_grid_column_renderer_bool');
    }
}
