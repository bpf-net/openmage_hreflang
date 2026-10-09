<?php

/**
 * CMS → Hreflang Translation Groups.
 */
class Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'cms/bpf_hreflang';

    public const MENU_PATH = 'cms/bpf_hreflang';

    /** Session key for the posted form, restored after a failed save. */
    public const FORM_DATA_KEY = 'bpf_hreflang_group_form_data';

    public function indexAction(): void
    {
        $this->_initAction();
        $this->renderLayout();
    }

    public function newAction(): void
    {
        $this->_forward('edit');
    }

    public function editAction(): void
    {
        $helper = Mage::helper('bpf_hreflang');
        $group = $this->_initGroup();
        if ($group === null) {
            $this->_getSession()->addError($helper->__('This translation group no longer exists.'));
            $this->_redirect('*/*/');
            return;
        }

        $data = $this->_getSession()->getData(self::FORM_DATA_KEY, true);
        if (is_array($data)) {
            $this->_applyPostData($group, $data);
        }

        Mage::register(Bpf_Hreflang_Model_Group::REGISTRY_KEY, $group);

        $this->_initAction();
        $this->_title($group->getId() ? $helper->__('Edit Group #%s', $group->getId()) : $helper->__('New Group'));
        $this->renderLayout();
    }

    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost();
        if (!$data) {
            $this->_redirect('*/*/');
            return;
        }

        $helper = Mage::helper('bpf_hreflang');
        $group = $this->_initGroup();
        if ($group === null) {
            $this->_getSession()->addError($helper->__('This translation group no longer exists.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $this->_applyPostData($group, $data);
            $group->save();

            $this->_getSession()->addSuccess($helper->__('The translation group has been saved.'));
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', ['id' => $group->getId()]);
                return;
            }
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $helper->__('An error occurred while saving the translation group.'));
        }

        $this->_getSession()->setData(self::FORM_DATA_KEY, $data);
        $this->_redirect('*/*/edit', $group->getId() ? ['id' => $group->getId()] : []);
    }

    public function deleteAction(): void
    {
        $helper = Mage::helper('bpf_hreflang');
        $group = $this->_initGroup();
        if ($group === null || !$group->getId()) {
            $this->_getSession()->addError($helper->__('Unable to find a translation group to delete.'));
            $this->_redirect('*/*/');
            return;
        }

        try {
            $group->delete();
            $this->_getSession()->addSuccess($helper->__('The translation group has been deleted.'));
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }
        $this->_redirect('*/*/');
    }

    /**
     * Group from the "id" parameter, a new one without it, or null when the ID does not exist.
     */
    protected function _initGroup(): ?Bpf_Hreflang_Model_Group
    {
        /** @var Bpf_Hreflang_Model_Group $group */
        $group = Mage::getModel('bpf_hreflang/group');
        $id = (int) $this->getRequest()->getParam('id');
        if ($id) {
            $group->load($id);
            if (!$group->getId()) {
                return null;
            }
        } else {
            $group->setEntityType(Bpf_Hreflang_Model_Group::ENTITY_TYPE_CMS_PAGE);
        }

        return $group;
    }

    /**
     * @param array<string, mixed> $data posted form: base_page_id, items[store ID] = page ID
     */
    protected function _applyPostData(Bpf_Hreflang_Model_Group $group, array $data): void
    {
        $basePageId = (int) ($data['base_page_id'] ?? 0);
        $group->setBaseEntityId($basePageId ?: null);
        $group->setItems(is_array($data['items'] ?? null) ? $data['items'] : []);
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
