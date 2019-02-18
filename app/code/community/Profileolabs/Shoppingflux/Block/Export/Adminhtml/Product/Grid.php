<?php

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

    /**
     * @return Mage_Core_Model_Store
     */
    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        return Mage::app()->getStore($storeId);
    }

    protected function _prepareCollection()
    {
        $store = $this->_getStore();

        /** @var Profileolabs_Shoppingflux_Model_Catalog_Product_Collection $collection */
        $collection = Mage::getModel('profileolabs_shoppingflux/catalog_product_collection');
        $collection->addAttributeToSelect(array('sku', 'name', 'attribute_set_id', 'type_id'));

        $collection->joinField(
            'qty',
            'cataloginventory/stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        if ($store->getId()) {
            $collection->setStoreId($store->getId());
            $collection->addStoreFilter($store);

            $collection->joinAttribute(
                'shoppingflux_product',
                'catalog_product/shoppingflux_product',
                'entity_id',
                null,
                'left',
                $store->getId()
            );

            $collection->joinAttribute(
                'custom_name',
                'catalog_product/name',
                'entity_id',
                null,
                'inner',
                $store->getId()
            );

            $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner', $store->getId());

            $collection->joinAttribute(
                'visibility',
                'catalog_product/visibility',
                'entity_id',
                null,
                'inner',
                $store->getId()
            );

            $collection->joinAttribute('price', 'catalog_product/price', 'entity_id', null, 'left', $store->getId());
        } else {
            $collection->addAttributeToSelect(array('price', 'status', 'visibility', 'shoppingflux_product'));
        }

        $collection->getSelect()
            ->joinLeft(
                array('category_product' => $collection->getTable('catalog/category_product')),
                'category_product.product_id = e.entity_id',
                array('categories' => new Zend_Db_Expr('group_concat(category_product.category_id)'))
            );

        $collection->groupByAttribute('entity_id');

        $this->setCollection($collection);
        parent::_prepareCollection();
        $collection->addWebsiteNamesToResult();

        return $this;
    }

    protected function _afterLoadCollection()
    {
        foreach ($this->getCollection() as $item) {
            $item->setCategories(explode(',', $item->getCategories()));
        }
    }

    protected function _addColumnFilterToCollection($column)
    {
        /**
         * @var Mage_Adminhtml_Block_Widget_Grid_Column $column
         * @var Profileolabs_Shoppingflux_Model_Catalog_Product_Collection $collection
         * */
        if ($collection = $this->getCollection()) {
            if ($column->getId() === 'websites') {
                $collection->joinField(
                    'websites',
                    'catalog/product_website',
                    'website_id',
                    'product_id=entity_id',
                    null,
                    'left'
                );
            }
        }

        return parent::_addColumnFilterToCollection($column);
    }

    protected function _prepareColumns()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->addColumn(
            'entity_id',
            array(
                'header' => $helper->__('ID'),
                'width' => '50px',
                'type' => 'number',
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

        $store = $this->_getStore();

        if ($store->getId()) {
            $this->addColumn(
                'custom_name',
                array(
                    'header' => $helper->__('Name In %s', $store->getName()),
                    'index' => 'custom_name',
                )
            );
        }

        $categories = $helper->getCategoriesWithParents(
            'name',
            Mage::app()
                ->getRequest()
                ->getParam('store', null)
        );

        $this->addColumn(
            'categories',
            array(
                'header' => $helper->__('Categories'),
                'index' => 'categories',
                'type' => 'options',
                'options' => $categories,
                'filter_condition_callback' => array($this, '_filterCategoriesCondition')
            )
        );

        /** @var Mage_Catalog_Model_Product_Type $typeModel */
        $typeModel = Mage::getSingleton('catalog/product_type');

        $this->addColumn(
            'type',
            array(
                'header' => $helper->__('Type'),
                'width' => '60px',
                'index' => 'type_id',
                'type' => 'options',
                'options' => $typeModel->getOptionArray(),
            )
        );

        /** @var Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = Mage::getResourceModel('catalog/product');
        $productTypeId = $productResource->getTypeId();

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $sets */
        $sets = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $sets->setEntityTypeFilter($productTypeId);
        $sets->load();

        $this->addColumn(
            'set_name',
            array(
                'header' => $helper->__('Attrib. Set Name'),
                'width' => '100px',
                'index' => 'attribute_set_id',
                'type' => 'options',
                'options' => $sets->toOptionHash(),
            )
        );

        $this->addColumn(
            'sku',
            array(
                'header' => $helper->__('SKU'),
                'width' => '80px',
                'index' => 'sku',
            )
        );

        $this->addColumn(
            'price',
            array(
                'header' => $helper->__('Price'),
                'type' => 'price',
                'currency_code' => $store->getBaseCurrency()->getCode(),
                'index' => 'price',
            )
        );

        $this->addColumn(
            'qty',
            array(
                'header' => $helper->__('Qty'),
                'width' => '100px',
                'type' => 'number',
                'index' => 'qty',
            )
        );

        /** @var Mage_Catalog_Model_Product_Visibility $visibilityModel */
        $visibilityModel = Mage::getSingleton('catalog/product_visibility');

        $this->addColumn(
            'visibility',
            array(
                'header' => $helper->__('Visibility'),
                'width' => '70px',
                'index' => 'visibility',
                'type' => 'options',
                'options' => $visibilityModel->getOptionArray(),
            )
        );

        /** @var Mage_Catalog_Model_Product_Status $statusModel */
        $statusModel = Mage::getSingleton('catalog/product_status');

        $this->addColumn(
            'status',
            array(
                'header' => $helper->__('Status'),
                'width' => '70px',
                'index' => 'status',
                'type' => 'options',
                'options' => $statusModel->getOptionArray(),
            )
        );

        $yesNoOptions = array(
            0 => $helper->__('No'),
            1 => $helper->__('Yes'),
        );

        $this->addColumn(
            'shoppingflux_product',
            array(
                'header' => $helper->__('Send to Shoppingflux ?'),
                'width' => '70px',
                'index' => 'shoppingflux_product',
                'type' => 'options',
                'options' => $yesNoOptions,
            )
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'websites',
                array(
                    'header' => $helper->__('Websites'),
                    'width' => '100px',
                    'sortable' => false,
                    'index' => 'websites',
                    'type' => 'options',
                    'options' => Mage::getModel('core/website')->getCollection()->toOptionHash(),
                )
            );
        }

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('product');

        $yesNoOptions = array(
            0 => $helper->__('No'),
            1 => $helper->__('Yes'),
        );

        $this->getMassactionBlock()->addItem(
            'publish',
            array(
                'label' => $helper->__('Change publication'),
                'url' => $this->getUrl('*/*/massPublish', array('_current' => true)),
                'additional' => array(
                    'visibility' => array(
                        'name' => 'publish',
                        'type' => 'select',
                        'class' => 'required-entry',
                        'label' => $helper->__('Publication'),
                        'values' => $yesNoOptions,
                    )
                )
            )
        );

        return $this;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    public function getRowUrl($row)
    {
        return '';
    }

    protected function _filterCategoriesCondition($collection, $column)
    {
        if ($value = $column->getFilter()->getValue()) {
            $collection->getSelect()->where('category_product.category_id = ?', $value);
        }
    }
}
