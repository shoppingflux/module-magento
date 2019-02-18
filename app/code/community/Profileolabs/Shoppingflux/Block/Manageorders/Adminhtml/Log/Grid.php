<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Log_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('shoppingflux_log_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('date');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        /** @var Profileolabs_Shoppingflux_Model_Mysql4_Manageorders_Log_Collection $collection */
        $collection = Mage::getResourceModel('profileolabs_shoppingflux/manageorders_log_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        /** @var Profileolabs_Shoppingflux_Helper_Data $helper */
        $helper = Mage::helper('profileolabs_shoppingflux');

        $this->addColumn(
            'id',
            array(
                'header' => $helper->__('ID'),
                'width' => '80px',
                'type' => 'text',
                'index' => 'id',
            )
        );

        $this->addColumn(
            'date',
            array(
                'header' => $helper->__('Created at'),
                'index' => 'date',
                'type' => 'datetime',
                'width' => '100px',
            )
        );

        $this->addColumn(
            'message',
            array(
                'header' => $helper->__('Message'),
                'index' => 'message',
            )
        );

        return parent::_prepareColumns();
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
