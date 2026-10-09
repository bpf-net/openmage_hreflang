<?php

/**
 * CMS page (cms_page_view). Versions come from the page's translation group; a page in no group
 * is its own version in every store view it is assigned to (all store views, or several).
 * A version counts only where the page is active and assigned to the store view.
 */
class Bpf_Hreflang_Model_Resolver_Cms implements Bpf_Hreflang_Model_Resolver_Interface
{
    /** @var array<int, array<int, int>> page ID => versions (store ID => page ID) */
    protected array $_groupItems = [];

    /**
     * Not for the store's 404 page opened at its own URL (it is the no-route content), and not for
     * a page shown in a store view where its translation group has another page: there the page
     * is not a version of the group, so it would point to a different page as its own language.
     */
    public function canResolve(Mage_Core_Controller_Request_Http $request): bool
    {
        $pageId = $this->_getCurrentPageId();
        if ($pageId === null) {
            return false;
        }

        $storeId = $this->_getCurrentStoreId();
        if ($this->_getCurrentPageIdentifier() === $this->_getNoRouteIdentifier($storeId)) {
            return false;
        }

        $versions = $this->_getVersions($pageId);

        return $versions === [] || ($versions[$storeId] ?? null) === $pageId;
    }

    public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds): array
    {
        $pageId = $this->_getCurrentPageId();
        if ($pageId === null) {
            return [];
        }

        $groupItems = $this->_getVersions($pageId);
        $versions = $groupItems !== []
            ? array_intersect_key($groupItems, array_flip($storeIds))
            : array_fill_keys($storeIds, $pageId);

        $pages = $this->_getPagesData(array_values(array_unique($versions)));
        $urlModel = $this->_getUrlModel();
        $urls = [];
        foreach ($versions as $storeId => $versionPageId) {
            $page = $pages[$versionPageId] ?? null;
            if ($page === null
                || !$page['is_active']
                || !array_intersect([Mage_Core_Model_App::ADMIN_STORE_ID, $storeId], $page['store_ids'])
            ) {
                continue;
            }
            $urls[$storeId] = $urlModel->getCmsPageUrl($page['identifier'], $storeId);
        }

        return $urls;
    }

    public function getCacheKey(Mage_Core_Controller_Request_Http $request): string
    {
        return 'cms_page:' . $this->_getCurrentPageId();
    }

    /**
     * Tags of every version (an identifier change elsewhere changes this page's tags) and the
     * module tag, which translation group changes clean.
     */
    public function getCacheTags(Mage_Core_Controller_Request_Http $request): array
    {
        $pageId = $this->_getCurrentPageId();
        if ($pageId === null) {
            return [];
        }

        $pageIds = array_unique(array_merge([$pageId], array_values($this->_getVersions($pageId))));
        $tags = array_map(static fn (int $id): string => Mage_Cms_Model_Page::CACHE_TAG . '_' . $id, $pageIds);
        $tags[] = Bpf_Hreflang_Block_Head::CACHE_TAG;

        return array_values($tags);
    }

    /**
     * @return array<int, int> store ID => page ID from the page's translation group, empty when in none
     */
    protected function _getVersions(int $pageId): array
    {
        if (!isset($this->_groupItems[$pageId])) {
            $this->_groupItems[$pageId] = $this->_getGroupResource()
                ->getEntityGroupItems(Bpf_Hreflang_Model_Group::ENTITY_TYPE_CMS_PAGE, $pageId);
        }

        return $this->_groupItems[$pageId];
    }

    /**
     * Page being viewed, as loaded by Mage_Cms_Helper_Page::renderPage().
     */
    protected function _getCurrentPageId(): ?int
    {
        $pageId = (int) Mage::getSingleton('cms/page')->getId();

        return $pageId > 0 ? $pageId : null;
    }

    protected function _getCurrentPageIdentifier(): string
    {
        return (string) Mage::getSingleton('cms/page')->getIdentifier();
    }

    protected function _getCurrentStoreId(): int
    {
        return (int) Mage::app()->getStore()->getId();
    }

    /**
     * Identifier of the store's 404 page; the config value may carry a "|<page ID>" suffix.
     */
    protected function _getNoRouteIdentifier(int $storeId): string
    {
        return explode('|', (string) Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $storeId))[0];
    }

    /**
     * @param list<int> $pageIds
     * @return array<int, array{identifier: string, is_active: bool, store_ids: list<int>}>
     */
    protected function _getPagesData(array $pageIds): array
    {
        if ($pageIds === []) {
            return [];
        }

        /** @var Mage_Cms_Model_Resource_Page $resource */
        $resource = Mage::getResourceSingleton('cms/page');
        $adapter = $resource->getReadConnection();

        $pages = [];
        $select = $adapter->select()
            ->from($resource->getMainTable(), ['page_id', 'identifier', 'is_active'])
            ->where('page_id IN (?)', $pageIds);
        foreach ($adapter->fetchAll($select) as $row) {
            $pages[(int) $row['page_id']] = [
                'identifier' => (string) $row['identifier'],
                'is_active' => (bool) $row['is_active'],
                'store_ids' => [],
            ];
        }

        $select = $adapter->select()
            ->from($resource->getTable('cms/page_store'), ['page_id', 'store_id'])
            ->where('page_id IN (?)', $pageIds);
        foreach ($adapter->fetchAll($select) as $row) {
            if (isset($pages[(int) $row['page_id']])) {
                $pages[(int) $row['page_id']]['store_ids'][] = (int) $row['store_id'];
            }
        }

        return $pages;
    }

    protected function _getGroupResource(): Bpf_Hreflang_Model_Resource_Group
    {
        return Mage::getResourceSingleton('bpf_hreflang/group');
    }

    protected function _getUrlModel(): Bpf_Hreflang_Model_Url
    {
        return Mage::getSingleton('bpf_hreflang/url');
    }
}
