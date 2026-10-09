<?php

class Bpf_Hreflang_Block_Adminhtml_Group extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'bpf_hreflang';
        $this->_controller = 'adminhtml_group';
        $this->_headerText = Mage::helper('bpf_hreflang')->__('Hreflang Translation Groups');
        $this->_addButtonLabel = Mage::helper('bpf_hreflang')->__('Add New Group');
        parent::__construct();
    }
}
