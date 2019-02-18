<?php

class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Cron_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('shoppingflux_cron_grid');
        $this->setUseAjax(true);
        $this->setDefaultSort('date');
        $this->setDefaultDir('desc');
        $this->setSaveParametersInSession(true);
        $this->setDefaultFilter(array('job_code' => 'shoppingflux'));
    }

    protected function _prepareCollection()
    {
        /** @var Mage_Cron_Model_Resource_Schedule_Collection $collection */
        $collection = Mage::getResourceModel('cron/schedule_collection');
        $collection->getSelect()->order('schedule_id desc');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        /** @var Mage_Cron_Helper_Data $helper */
        $helper = Mage::helper('cron');

        $this->addColumn(
            'schedule_id',
            array(
                'header' => $helper->__('Schedule Id'),
                'width' => '80px',
                'type' => 'text',
                'index' => 'schedule_id',
            )
        );

        $this->addColumn(
            'job_code',
            array(
                'header' => $helper->__('Job Code'),
                'width' => '80px',
                'type' => 'text',
                'index' => 'job_code',
            )
        );

        $this->addColumn(
            'status',
            array(
                'header' => $helper->__('Status'),
                'width' => '80px',
                'type' => 'text',
                'index' => 'status',
            )
        );

        $this->addColumn(
            'messages',
            array(
                'header' => $helper->__('Messages'),
                'width' => '80px',
                'type' => 'text',
                'index' => 'messages',
            )
        );

        $this->addColumn(
            'scheduled_at',
            array(
                'header' => $helper->__('Scheduled at'),
                'width' => '80px',
                'type' => 'datetime',
                'index' => 'scheduled_at',
            )
        );


        $this->addColumn(
            'created_at',
            array(
                'header' => $helper->__('Created at'),
                'width' => '80px',
                'type' => 'datetime',
                'index' => 'created_at',
            )
        );

        $this->addColumn(
            'executed_at',
            array(
                'header' => $helper->__('Executed at'),
                'width' => '80px',
                'type' => 'datetime',
                'index' => 'executed_at',
            )
        );

        $this->addColumn(
            'finished_at',
            array(
                'header' => $helper->__('Finished at'),
                'width' => '80px',
                'type' => 'datetime',
                'index' => 'finished_at',
            )
        );

        return parent::_prepareColumns();
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
