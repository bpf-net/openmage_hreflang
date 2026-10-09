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
     * @return list<int>
     */
    protected function _getRootStoreIds(): array
    {
        return Mage::helper('bpf_hreflang')->getRootStoreIds();
    }
}
