<?php

/**
 * Shoppingflux grid products block
 *
 * @category   Profileolabs
 * @package    Profileolabs_Shoppingflux
 * @author kassim belghait kassim@profileo.com
 */
class Profileolabs_Shoppingflux_Block_Export_Adminhtml_Product_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('productGrid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('product_filter');

    }

    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        return Mage::app()->getStore($storeId);
    }

    protected function _prepareCollection()
    {
        $store = $this->_getStore();
        $collection = Mage::getModel('profileolabs_shoppingflux/catalog_product_collection')
            ->addAttributeToSelect('sku')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('attribute_set_id')
            ->addAttributeToSelect('type_id')
            ->joinField('qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left');

        if ($store->getId()) {
            $collection->setStoreId($store->getId());
            $collection->addStoreFilter($store);
            $collection->joinAttribute('shoppingflux_product', 'catalog_product/shoppingflux_product', 'entity_id', null, 'left', $store->getId());
            $collection->joinAttribute('custom_name', 'catalog_product/name', 'entity_id', null, 'inner', $store->getId());
            $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner', $store->getId());
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner', $store->getId());
            $collection->joinAttribute('price', 'catalog_product/price', 'entity_id', null, 'left', $store->getId());
        }
        else {
            $collection->addAttributeToSelect('price');
            $collection->addAttributeToSelect('status');
            $collection->addAttributeToSelect('visibility');
            $collection->addAttributeToSelect('shoppingflux_product');
        }
        
        $collection->getSelect()->joinLeft(
                array('category_product' => $collection->getTable('catalog/category_product')), 'category_product.product_id = e.entity_id', array('categories' => new Zend_Db_Expr('group_concat(category_product.category_id)'))
        );

        $collection->groupByAttribute('entity_id');
        
        $this->setCollection($collection);

        parent::_prepareCollection();
        $this->getCollection()->addWebsiteNamesToResult();
        return $this;
    }
    
    protected function _afterLoadCollection()
    {
        foreach($this->getCollection() as $item) {
            $item->setCategories(explode(',',$item->getCategories()));
        }
    }

    protected function _addColumnFilterToCollection($column)
    {
        if ($this->getCollection()) {
            if ($column->getId() == 'websites') {
                $this->getCollection()->joinField('websites',
                    'catalog/product_website',
                    'website_id',
                    'product_id=entity_id',
                    null,
                    'left');
            }
        }
        return parent::_addColumnFilterToCollection($column);
    }

    protected function _prepareColumns()
    {
        $this->addColumn('entity_id',
            array(
                'header'=> Mage::helper('catalog')->__('ID'),
                'width' => '50px',
                'type'  => 'number',
                'index' => 'entity_id',
        ));
        $this->addColumn('name',
            array(
                'header'=> Mage::helper('catalog')->__('Name'),
                'index' => 'name',
        ));

        $store = $this->_getStore();
        if ($store->getId()) {
            $this->addColumn('custom_name',
                array(
                    'header'=> Mage::helper('profileolabs_shoppingflux')->__('Name In %s', $store->getName()),
                    'index' => 'custom_name',
            ));
        }

        $categories = Mage::helper('profileolabs_shoppingflux')->getCategoriesWithParents('name', Mage::app()->getRequest()->getParam('store', null));
        $this->addColumn('categories',
            array(
                'header'=> Mage::helper('catalog')->__('Categories'),
                'index' => 'categories',
                'type'  => 'options',
                'options' => $categories,
                'filter_condition_callback' => array($this, '_filterCategoriesCondition')
        ));

        $this->addColumn('type',
            array(
                'header'=> Mage::helper('catalog')->__('Type'),
                'width' => '60px',
                'index' => 'type_id',
                'type'  => 'options',
                'options' => Mage::getSingleton('catalog/product_type')->getOptionArray(),
        ));

        $sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
            ->setEntityTypeFilter(Mage::getModel('catalog/product')->getResource()->getTypeId())
            ->load()
            ->toOptionHash();

        $this->addColumn('set_name',
            array(
                'header'=> Mage::helper('catalog')->__('Attrib. Set Name'),
                'width' => '100px',
                'index' => 'attribute_set_id',
                'type'  => 'options',
                'options' => $sets,
        ));

        $this->addColumn('sku',
            array(
                'header'=> Mage::helper('catalog')->__('SKU'),
                'width' => '80px',
                'index' => 'sku',
        ));

        $store = $this->_getStore();
        $this->addColumn('price',
            array(
                'header'=> Mage::helper('catalog')->__('Price'),
                'type'  => 'price',
                'currency_code' => $store->getBaseCurrency()->getCode(),
                'index' => 'price',
        ));

        $this->addColumn('qty',
            array(
                'header'=> Mage::helper('catalog')->__('Qty'),
                'width' => '100px',
                'type'  => 'number',
                'index' => 'qty',
        ));

        $this->addColumn('visibility',
            array(
                'header'=> Mage::helper('catalog')->__('Visibility'),
                'width' => '70px',
                'index' => 'visibility',
                'type'  => 'options',
                'options' => Mage::getModel('catalog/product_visibility')->getOptionArray(),
        ));

        $this->addColumn('status',
            array(
                'header'=> Mage::helper('catalog')->__('Status'),
                'width' => '70px',
                'index' => 'status',
                'type'  => 'options',
                'options' => Mage::getSingleton('catalog/product_status')->getOptionArray(),
        ));
        $optionsSf = array(0=>Mage::helper('profileolabs_shoppingflux')->__('No'),1=>Mage::helper('profileolabs_shoppingflux')->__('Yes'));
        $this->addColumn('shoppingflux_product',
            array(
                'header'=> Mage::helper('profileolabs_shoppingflux')->__('Send to Shoppingflux ?'),
                'width' => '70px',
                'index' => 'shoppingflux_product',
                'type'  => 'options',
                'options' => $optionsSf,
        ));

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('websites',
                array(
                    'header'=> Mage::helper('catalog')->__('Websites'),
                    'width' => '100px',
                    'sortable'  => false,
                    'index'     => 'websites',
                    'type'      => 'options',
                    'options'   => Mage::getModel('core/website')->getCollection()->toOptionHash(),
            ));
        }

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('product');

		$optionsSf = array(0=>"Non",1=>"Oui");
        $this->getMassactionBlock()->addItem('publish', array(
             'label'=> Mage::helper('profileolabs_shoppingflux')->__('Change publication'),
             'url'  => $this->getUrl('*/*/massPublish', array('_current'=>true)),
             'additional' => array(
                    'visibility' => array(
                         'name' => 'publish',
                         'type' => 'select',
                         'class' => 'required-entry',
                         'label' => Mage::helper('profileolabs_shoppingflux')->__('Publication'),
                         'values' => $optionsSf
                     )
             )
        ));

        return $this;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current'=>true));
    }

    public function getRowUrl($row)
    {
        return '';
    }
    
    protected function _filterCategoriesCondition($collection, $column) {
        if (!$value = $column->getFilter()->getValue()) {
            return;
        }

        $this->getCollection()->getSelect()->where('category_product.category_id = ?',  $value);
    }
}
