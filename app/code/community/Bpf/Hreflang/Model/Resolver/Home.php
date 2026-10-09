<?php

/**
 * Home page (cms_index_index): every store view has one, at its base URL.
 */
class Bpf_Hreflang_Model_Resolver_Home implements Bpf_Hreflang_Model_Resolver_Interface
{
    public function canResolve(Mage_Core_Controller_Request_Http $request): bool
    {
        return true;
    }

    public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds): array
    {
        $urlModel = $this->_getUrlModel();
        $urls = [];
        foreach ($storeIds as $storeId) {
            $urls[$storeId] = $urlModel->getHomeUrl($storeId);
        }

        return $urls;
    }

    public function getCacheKey(Mage_Core_Controller_Request_Http $request): string
    {
        return 'home';
    }

    /**
     * Home URLs depend on configuration only, which the block cache is tagged with anyway.
     */
    public function getCacheTags(Mage_Core_Controller_Request_Http $request): array
    {
        return [];
    }

    protected function _getUrlModel(): Bpf_Hreflang_Model_Url
    {
        return Mage::getSingleton('bpf_hreflang/url');
    }
}
