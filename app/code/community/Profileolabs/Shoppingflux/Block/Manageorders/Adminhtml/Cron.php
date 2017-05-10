<?php

/**
 * Shopping Flux Log grid container
 * @category   ShoppingFlux
 * @package    Profileolabs_Shoppingflux_ManageOrders
 * @author Vincent Enjalbert
 */
class Profileolabs_Shoppingflux_Block_Manageorders_Adminhtml_Cron extends Mage_Adminhtml_Block_Widget_Grid_Container {

    public function __construct() {
        parent::__construct();

        $this->_controller = 'manageorders_adminhtml_cron';
        $this->_blockGroup = 'profileolabs_shoppingflux';
        $this->_headerText = $this->__('Crons');

        $this->_removeButton('add');
    }

}
