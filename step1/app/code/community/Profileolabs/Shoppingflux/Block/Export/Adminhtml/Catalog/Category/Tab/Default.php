<?php

/**
 * Shopping Flux   Block for category page to assiocate products.
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux
 * @author Vincent Enjalbert @ Web-cooking.net
 */
class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Catalog_Category_Tab_Default extends Mage_Adminhtml_Block_Widget_Grid {

    public function __construct() {
        parent::__construct();
        $this->setId('shoppingflux_default_category_products');
        $this->setDefaultSort('entity_id');
        $this->setUseAjax(true);
    }
    
     public function getCategory()
    {
        return Mage::registry('category');
    }
    
    protected function _afterToHtml($html) {
        $html = parent::_afterToHtml($html);
        if($this->getRequest()->getActionName() != 'edit') return $html;
        $scriptData = $this->getLayout()->createBlock('profileolabs_shoppingflux/export_adminhtml_catalog_category_edit_form', 'category.edit.shoppingflux')->toHtml();
        //Mage::log($html . 
        //        $scriptData
        //        .
        //    '<input type="hidden" name="shoppingflux_category_products" id="shoppingflux_in_category_products" value="" />');
        return $html . 
                $scriptData
                .
            '<input type="hidden" name="shoppingflux_category_products" id="shoppingflux_in_category_products" value="" />';
    }

    protected function _addColumnFilterToCollection($column)
    {
        // Set custom filter for in category flag
        if ($column->getId() == 'in_shoppingflux_category') {
            $productIds = $this->_getSelectedProducts();
            if (empty($productIds)) {
                $productIds = 0;
            }
            if ($column->getFilter()->getValue()) {
                $this->getCollection()->addFieldToFilter('entity_id', array('in'=>$productIds));
            }
            elseif(!empty($productIds)) {
                $this->getCollection()->addFieldToFilter('entity_id', array('nin'=>$productIds));
            }
        }
        else if ($column->getId() == 'is_in_category') {
            $productIds = $this->_getInCategoryProducts();
            if (empty($productIds)) {
                $productIds = 0;
            }
            if ($column->getFilter()->getValue()) {
                $this->getCollection()->addFieldToFilter('entity_id', array('in'=>$productIds));
            }
            elseif(!empty($productIds)) {
                $this->getCollection()->addFieldToFilter('entity_id', array('nin'=>$productIds));
            }
        } else {
            parent::_addColumnFilterToCollection($column);
        }
        return $this;
    }

    protected function _getSelectedProducts() {
        $products = $this->getRequest()->getPost('sf_selected_products');
        if (is_null($products)) {
            $products = array();
           $collection = Mage::getModel('catalog/product')->getCollection()
            ->setStore($this->getRequest()->getParam('store'))
            ->setStoreId($this->getRequest()->getParam('store'))
            ->addAttributeToFilter('shoppingflux_default_category', $this->getCategory()->getId())
            ->addStoreFilter($this->getRequest()->getParam('store'));
           foreach($collection as $_product) {
               $products[] = $_product->getId();
           }
            return $products;
        }
        return $products;
    }
    
    protected function _getInCategoryProducts()
    {
        $products = $this->getRequest()->getPost('selected_products');
        if (is_null($products)) {
            $products = $this->getCategory()->getProductsPosition();
            return array_keys($products);
        }
        return $products;
    }

    protected function _prepareCollection() {
        if ($this->getCategory()->getId()) {
            $this->setDefaultFilter(array('in_shoppingflux_category' => 1));
        }
        $collection = Mage::getModel('catalog/product')->getCollection()
                ->setStore($this->getRequest()->getParam('store'))
                ->setStoreId($this->getRequest()->getParam('store'))
                ->addAttributeToSelect('name')
                ->addAttributeToSelect('sku')
                ->addAttributeToSelect('price')
                //->addAttributeToSelect('shoppingflux_default_category')
                ->addExpressionAttributeToSelect('shoppingflux_default_category','IF({{shoppingflux_default_category}}, {{shoppingflux_default_category}}, -1)', 'shoppingflux_default_category')
                ->addAttributeToSelect('shoppingflux_product')
                ->addStoreFilter($this->getRequest()->getParam('store'));
        $this->setCollection($collection);

       
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
       
       
        
        $this->addColumn('in_shoppingflux_category', array(
            'header_css_class' => 'a-center',
            'type'      => 'checkbox',
            'name'      => 'in_shoppingflux_category',
            'values'    => $this->_getSelectedProducts(),
            'align'     => 'center',
            'index'     => 'entity_id'
        ));
        $this->addColumn('is_in_category', array(
            'header'    => Mage::helper('profileolabs_shoppingflux')->__('In category ?'),
            'type'      => 'bool',
            'name'      => 'is_in_category',
            'values'    => $this->_getInCategoryProducts(),
            'align'     => 'center',
            'index'     => 'entity_id',
            'filter'    => 'adminhtml/widget_grid_column_filter_checkbox'
        ));

        $this->addColumn('entity_id', array(
            'header'    => Mage::helper('catalog')->__('ID'),
            'sortable'  => true,
            'width'     => '60',
            'index'     => 'entity_id'
        ));
        $this->addColumn('name', array(
            'header'    => Mage::helper('catalog')->__('Name'),
            'index'     => 'name'
        ));
        $this->addColumn('sku', array(
            'header'    => Mage::helper('catalog')->__('SKU'),
            'width'     => '80',
            'index'     => 'sku'
        ));
        $this->addColumn('price', array(
            'header'    => Mage::helper('catalog')->__('Price'),
            'type'  => 'currency',
            'width'     => '1',
            'currency_code' => (string) Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE),
            'index'     => 'price'
        ));
        
        $optionsCategories = Mage::helper('profileolabs_shoppingflux')->getCategoriesWithParents('name');
        $optionsCategories = array_reverse($optionsCategories, true);
        $optionsCategories[-1] = '__';
        $optionsCategories = array_reverse($optionsCategories, true);
        $this->addColumn('shoppingflux_default_category', array(
            'header' => Mage::helper('profileolabs_shoppingflux')->__('Current default category'),
            'width' => '80',
            'type' => 'options',
            'options' => $optionsCategories,
            'index' => 'shoppingflux_default_category'
        ));
        
        $optionsSf = array(0 => Mage::helper('profileolabs_shoppingflux')->__('No'), 1 => Mage::helper('profileolabs_shoppingflux')->__('Yes'));
        $this->addColumn('shoppingflux_product', array(
            'header' => Mage::helper('profileolabs_shoppingflux')->__('Send to Shoppingflux ?'),
            'width' => '80',
            'index' => 'shoppingflux_product',
            'type' => 'options',
            'options' => $optionsSf
        ));
        return  parent::_prepareColumns();
    }

    public function getGridUrl() {
        return $this->getUrl('adminhtml/shoppingfeed_export_category/grid', array('_current' => true));
    }
    
    public function getColumnRenderers() {
        return array('bool'=>'profileolabs_shoppingflux/export_adminhtml_widget_grid_column_renderer_bool');
    }

}