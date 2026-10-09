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

    protected function _getBaseUrl(int $storeId): string
    {
        $store = $this->_getStore($storeId);

        return $store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, $store->isFrontUrlSecure());
    }

    protected function _getStore(int $storeId): Mage_Core_Model_Store
    {
        return Mage::app()->getStore($storeId);
    }
}
