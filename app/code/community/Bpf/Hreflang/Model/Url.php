<?php

/**
 * Builds the absolute URLs used in hreflang tags. The only place that knows how a page
 * URL looks in another store view.
 *
 * URLs are built from the store's base link URL rather than $store->getUrl(), which adds
 * "?___store=<code>" when the target store differs from the current one.
 */
class Bpf_Hreflang_Model_Url
{
    /**
     * Base URL of the store view's home page; secure when the store uses HTTPS on the frontend.
     */
    public function getHomeUrl(int $storeId): string
    {
        return $this->_getBaseUrl($storeId);
    }

    /**
     * Canonical product URLs (without category path) from each store's URL rewrite.
     * Stores without a rewrite for the product are left out.
     *
     * @param list<int> $storeIds
     * @return array<int, string> store ID => absolute URL
     */
    public function getProductUrls(int $productId, array $storeIds): array
    {
        return $this->_getUrlsByIdPath('product/' . $productId, $storeIds);
    }

    /**
     * Category URLs from each store's URL rewrite. Stores without a rewrite for the category are left out.
     *
     * @param list<int> $storeIds
     * @return array<int, string> store ID => absolute URL
     */
    public function getCategoryUrls(int $categoryId, array $storeIds): array
    {
        return $this->_getUrlsByIdPath('category/' . $categoryId, $storeIds);
    }

    /**
     * CMS page URL in the store: base URL + identifier, or the home URL for the store's home page.
     */
    public function getCmsPageUrl(string $identifier, int $storeId): string
    {
        if ($identifier === $this->_getHomePageIdentifier($storeId)) {
            return $this->getHomeUrl($storeId);
        }

        return $this->_getBaseUrl($storeId) . ltrim($identifier, '/');
    }

    /**
     * @param list<int> $storeIds
     * @return array<int, string> store ID => absolute URL
     */
    protected function _getUrlsByIdPath(string $idPath, array $storeIds): array
    {
        $urls = [];
        foreach ($this->_fetchRequestPaths($idPath, $storeIds) as $storeId => $requestPath) {
            $urls[$storeId] = $this->_getBaseUrl($storeId) . ltrim($requestPath, '/');
        }

        return $urls;
    }

    /**
     * Request paths of an id_path in the given stores. Like Mage_Core_Model_Url_Rewrite::loadByIdPath(),
     * a system rewrite wins over a custom one for the same store.
     *
     * @param list<int> $storeIds
     * @return array<int, string> store ID => request path
     */
    protected function _fetchRequestPaths(string $idPath, array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_read');
        $select = $connection->select()
            ->from($resource->getTableName('core/url_rewrite'), ['store_id', 'request_path'])
            ->where('id_path = ?', $idPath)
            ->where('store_id IN (?)', $storeIds)
            ->order('is_system ' . Varien_Db_Select::SQL_ASC);

        $paths = [];
        foreach ($connection->fetchAll($select) as $row) {
            // Rows are ordered so that the system rewrite comes last and overwrites a custom one.
            $paths[(int) $row['store_id']] = (string) $row['request_path'];
        }

        return $paths;
    }

    /**
     * Base link URL of the store view. Root stores (bpf_hreflang/url/root_stores) are served
     * without the store code segment that web/url/use_store adds; DIRECT_LINK is the same
     * base link URL (including index.php when rewrites are off) without that segment.
     */
    protected function _getBaseUrl(int $storeId): string
    {
        $store = $this->_getStore($storeId);
        $type = in_array($storeId, $this->_getRootStoreIds(), true)
            ? Mage_Core_Model_Store::URL_TYPE_DIRECT_LINK
            : Mage_Core_Model_Store::URL_TYPE_LINK;

        return $store->getBaseUrl($type, $store->isFrontUrlSecure());
    }

    protected function _getStore(int $storeId): Mage_Core_Model_Store
    {
        return Mage::app()->getStore($storeId);
    }

    /**
     * Identifier of the store's CMS home page; the config value may carry a "|<page ID>" suffix.
     */
    protected function _getHomePageIdentifier(int $storeId): string
    {
        $value = (string) Mage::getStoreConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $storeId);

        return explode('|', $value)[0];
    }

    /**
     * @return list<int>
     */
    protected function _getRootStoreIds(): array
    {
        return Mage::helper('bpf_hreflang')->getRootStoreIds();
    }
}
