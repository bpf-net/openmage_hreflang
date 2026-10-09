<?php

class Bpf_Hreflang_Block_Adminhtml_Group_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'bpf_hreflang';
        $this->_controller = 'adminhtml_group';

        parent::__construct();

        $this->_addButton('saveandcontinue', [
            'label' => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick' => Mage::helper('core/js')->getSaveAndContinueEditJs($this->getUrl('*/*/save', ['_current' => true, 'back' => 'edit'])),
            'class' => 'save continue',
        ], -100);
    }

    public function getHeaderText()
    {
        $helper = Mage::helper('bpf_hreflang');
        $group = Mage::registry(Bpf_Hreflang_Model_Group::REGISTRY_KEY);

        return $group && $group->getId()
            ? $helper->__('Edit Translation Group #%s', $group->getId())
            : $helper->__('New Translation Group');
    }
}
