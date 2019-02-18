<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * @var bool
     */
    protected $_hasGridSubsetCollection = true;

    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $currentVersion = Mage::getVersion();
        $this->_hasGridSubsetCollection = (version_compare($currentVersion, '1.4.1') >= 0);
    }

    /**
     * Retrieve collection class
     *
     * @return string
     */
    protected function _getCollectionClass()
    {
        return $this->_hasGridSubsetCollection
            ? 'sales/order_grid_collection'
            : 'sales/order_collection';
    }

    protected function _prepareCollection()
    {
        /** @var Mage_Sales_Model_Resource_Order_Grid_Collection $collection */
        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->addAttributeToFilter('from_shoppingflux', 1);

        if (!$this->_hasGridSubsetCollection) {
            /** @var Mage_Sales_Model_Resource_Order_Collection $collection */
            $collection->addAttributeToSelect('*')
                ->joinAttribute('billing_firstname', 'order_address/firstname', 'billing_address_id', null, 'left')
                ->joinAttribute('billing_lastname', 'order_address/lastname', 'billing_address_id', null, 'left')
                ->joinAttribute('shipping_firstname', 'order_address/firstname', 'shipping_address_id', null, 'left')
                ->joinAttribute('shipping_lastname', 'order_address/lastname', 'shipping_address_id', null, 'left');

            $collection->addExpressionAttributeToSelect(
                'billing_name',
                'CONCAT({{billing_firstname}}, " ", {{billing_lastname}})',
                array('billing_firstname', 'billing_lastname')
            );

            $collection->addExpressionAttributeToSelect(
                'shipping_name',
                'CONCAT({{shipping_firstname}},  IFNULL(CONCAT(\' \', {{shipping_lastname}}), \'\'))',
                array('shipping_firstname', 'shipping_lastname')
            );
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->addColumn(
            'real_order_id',
            array(
                'header' => $helper->__('Order #'),
                'width' => '80px',
                'type' => 'text',
                'index' => 'increment_id',
            )
        );

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn(
                'store_id',
                array(
                    'header' => $helper->__('Purchased From (Store)'),
                    'index' => 'store_id',
                    'type' => 'store',
                    'store_view' => true,
                    'display_deleted' => true,
                )
            );
        }

        $this->addColumn(
            'created_at',
            array(
                'header' => $helper->__('Purchased On'),
                'index' => 'created_at',
                'type' => 'datetime',
                'width' => '100px',
            )
        );

        $this->addColumn(
            'billing_name',
            array(
                'header' => $helper->__('Bill to Name'),
                'index' => 'billing_name',
            )
        );

        $this->addColumn(
            'shipping_name',
            array(
                'header' => $helper->__('Ship to Name'),
                'index' => 'shipping_name',
            )
        );

        $this->addColumn(
            'base_grand_total',
            array(
                'header' => $helper->__('G.T. (Base)'),
                'index' => 'base_grand_total',
                'type' => 'currency',
                'currency' => 'base_currency_code',
            )
        );

        $this->addColumn(
            'grand_total',
            array(
                'header' => $helper->__('G.T. (Purchased)'),
                'index' => 'grand_total',
                'type' => 'currency',
                'currency' => 'order_currency_code',
            )
        );

        $this->addColumn(
            'order_id_shoppingflux',
            array(
                'header' => $helper->__('ShoppingFlux ID'),
                'index' => 'order_id_shoppingflux',
            )
        );

        $this->addColumn(
            'marketplace_shoppingflux',
            array(
                'header' => $helper->__('Marketplace'),
                'index' => 'marketplace_shoppingflux',
            )
        );

        $this->addColumn(
            'fees_shoppingflux',
            array(
                'header' => $helper->__('Fees'),
                'type' => 'currency',
                'index' => 'fees_shoppingflux',
                'currency' => 'base_currency_code',
            )
        );

        /** @var Mage_Sales_Model_Order_Config $orderConfig */
        $orderConfig = Mage::getSingleton('sales/order_config');

        $this->addColumn(
            'status',
            array(
                'header' => $helper->__('Status'),
                'index' => 'status',
                'type' => 'options',
                'width' => '70px',
                'options' => $orderConfig->getStatuses(),
            )
        );

        /** @var Mage_Admin_Model_Session $sessionModel */
        $sessionModel = Mage::getSingleton('admin/session');

        if ($sessionModel->isAllowed('sales/order/actions/view')) {
            $this->addColumn(
                'action',
                array(
                    'header' => $helper->__('Action'),
                    'width' => '50px',
                    'type' => 'action',
                    'getter' => 'getId',
                    'actions' => array(
                        array(
                            'caption' => $helper->__('View'),
                            'url' => array('base' => 'adminhtml/sales_order/view'),
                            'field' => 'order_id'
                        )
                    ),
                    'filter' => false,
                    'sortable' => false,
                    'index' => 'stores',
                    'is_system' => true,
                )
            );
        }

        $this->addExportType('*/*/exportCsv', $helper->__('CSV'));
        $this->addExportType('*/*/exportExcel', $helper->__('Excel XML'));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->setMassactionIdField('entity_id');
        $this->getMassactionBlock()->setFormFieldName('order_ids');
        $this->getMassactionBlock()->setUseSelectAll(false);

        /** @var Mage_Admin_Model_Session $sessionModel */
        $sessionModel = Mage::getSingleton('admin/session');

        if ($sessionModel->isAllowed('sales/order/actions/cancel')) {
            $this->getMassactionBlock()->addItem(
                'cancel_order',
                array(
                    'label' => $helper->__('Cancel'),
                    'url' => $this->getUrl('adminhtml/sales_order/massCancel'),
                )
            );
        }

        if ($sessionModel->isAllowed('sales/order/actions/hold')) {
            $this->getMassactionBlock()->addItem(
                'hold_order',
                array(
                    'label' => $helper->__('Hold'),
                    'url' => $this->getUrl('adminhtml/sales_order/massHold'),
                )
            );
        }

        if ($sessionModel->isAllowed('sales/order/actions/unhold')) {
            $this->getMassactionBlock()->addItem(
                'unhold_order',
                array(
                    'label' => $helper->__('Unhold'),
                    'url' => $this->getUrl('adminhtml/sales_order/massUnhold'),
                )
            );
        }

        $this->getMassactionBlock()->addItem(
            'pdfinvoices_order',
            array(
                'label' => $helper->__('Print Invoices'),
                'url' => $this->getUrl('adminhtml/sales_order/pdfinvoices'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'pdfshipments_order',
            array(
                'label' => $helper->__('Print Packingslips'),
                'url' => $this->getUrl('adminhtml/sales_order/pdfshipments'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'pdfcreditmemos_order',
            array(
                'label' => $helper->__('Print Credit Memos'),
                'url' => $this->getUrl('adminhtml/sales_order/pdfcreditmemos'),
            )
        );

        $this->getMassactionBlock()->addItem(
            'pdfdocs_order',
            array(
                'label' => $helper->__('Print All'),
                'url' => $this->getUrl('adminhtml/sales_order/pdfdocs'),
            )
        );

        return $this;
    }

    public function getRowUrl($row)
    {
        /** @var Mage_Admin_Model_Session $sessionModel */
        $sessionModel = Mage::getSingleton('admin/session');

        return $sessionModel->isAllowed('sales/order/actions/view')
            ? $this->getUrl('adminhtml/sales_order/view', array('order_id' => $row->getId()))
            : false;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
