<?php

/**
 * Checks a CMS page translation group before it is saved.
 */
class Bpf_Hreflang_Model_Group_Validator
{
    /**
     * @return list<string> translated error messages; empty when the group is valid
     */
    public function validate(Bpf_Hreflang_Model_Group $group): array
    {
        $helper = $this->_getHelper();
        $errors = [];
        $items = $group->getItems();
        $groupId = (int) $group->getData('group_id');

        if (count($items) < 2) {
            $errors[] = $helper->__('Choose pages for at least two store views; a single version has no alternates.');
        }

        $basePageId = (int) $group->getBaseEntityId();
        if ($basePageId && $this->_getPageInfo($basePageId) === null) {
            $errors[] = $helper->__('The base page (ID %s) no longer exists.', $basePageId);
        }

        foreach ($items as $storeId => $pageId) {
            $storeName = $this->_getStoreName($storeId);
            if ($storeName === null) {
                $errors[] = $helper->__('Store view ID %s does not exist.', $storeId);
                continue;
            }

            $page = $this->_getPageInfo($pageId);
            if ($page === null) {
                $errors[] = $helper->__('The page chosen for store view "%s" (ID %s) no longer exists.', $storeName, $pageId);
                continue;
            }

            if (!array_intersect([Mage_Core_Model_App::ADMIN_STORE_ID, $storeId], $page['store_ids'])) {
                $errors[] = $helper->__('Page "%s" is not shown in store view "%s".', $page['title'], $storeName);
            }

            $otherGroupId = $this->_getEntityGroupId($pageId);
            if ($otherGroupId !== null && $otherGroupId !== $groupId) {
                $errors[] = $helper->__('Page "%s" already belongs to translation group #%s; a page can be in one group only.', $page['title'], $otherGroupId);
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * @return array{title: string, store_ids: list<int>}|null
     */
    protected function _getPageInfo(int $pageId): ?array
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($pageId);
        if (!$page->getId()) {
            return null;
        }

        return [
            'title' => (string) $page->getTitle(),
            'store_ids' => array_map('intval', (array) $page->getResource()->lookupStoreIds((string) $pageId)),
        ];
    }

    protected function _getEntityGroupId(int $pageId): ?int
    {
        /** @var Bpf_Hreflang_Model_Resource_Group $resource */
        $resource = Mage::getResourceSingleton('bpf_hreflang/group');

        return $resource->getEntityGroupId(Bpf_Hreflang_Model_Group::ENTITY_TYPE_CMS_PAGE, $pageId);
    }

    protected function _getStoreName(int $storeId): ?string
    {
        $stores = Mage::app()->getStores();

        return isset($stores[$storeId]) ? (string) $stores[$storeId]->getName() : null;
    }

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
