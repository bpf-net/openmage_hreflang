<?php

/**
 * CMS → Hreflang Translation Groups.
 */
class Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/bpf_hreflang';

    public const MENU_PATH = 'cms/bpf_hreflang';

    public function indexAction(): void
    {
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * @return $this
     */
    protected function _initAction()
    {
        $helper = Mage::helper('bpf_hreflang');
        $this->_title($this->__('CMS'))->_title($helper->__('Hreflang Translation Groups'));
        $this->loadLayout()
            ->_setActiveMenu(self::MENU_PATH)
            ->_addBreadcrumb($helper->__('CMS'), $helper->__('CMS'))
            ->_addBreadcrumb($helper->__('Hreflang Translation Groups'), $helper->__('Hreflang Translation Groups'));

        return $this;
    }
}
