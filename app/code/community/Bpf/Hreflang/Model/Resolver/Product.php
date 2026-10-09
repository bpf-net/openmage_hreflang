<?php

/**
 * Product page (catalog_product_view): the same product ID in each store view, at its canonical
 * URL, where the product is enabled, visible in catalog and/or search, and assigned to the
 * store's website.
 */
class Bpf_Hreflang_Model_Resolver_Product implements Bpf_Hreflang_Model_Resolver_Interface
{
    public const VISIBLE_VISIBILITIES = [
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
    ];

    public function canResolve(Mage_Core_Controller_Request_Http $request): bool
    {
        return $this->_getProductId() !== null;
    }

    public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds): array
    {
        $productId = $this->_getProductId();
        if ($productId === null) {
            return [];
        }

        $websiteIds = array_map('intval', $this->_getProductWebsiteIds($productId));
        $available = [];
        foreach ($storeIds as $storeId) {
            if (in_array($this->_getStoreWebsiteId($storeId), $websiteIds, true)
                && $this->_isAvailableInStore($productId, $storeId)
            ) {
                $available[] = $storeId;
            }
        }

        return $this->_getUrlModel()->getProductUrls($productId, $available);
    }

    public function getCacheKey(Mage_Core_Controller_Request_Http $request): string
    {
        return 'product:' . $this->_getProductId();
    }

    public function getCacheTags(Mage_Core_Controller_Request_Http $request): array
    {
        return [Mage_Catalog_Model_Product::CACHE_TAG . '_' . $this->_getProductId()];
    }

    protected function _isAvailableInStore(int $productId, int $storeId): bool
    {
        $values = $this->_getStoreAttributeValues($productId, $storeId);

        return (int) ($values['status'] ?? 0) === Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            && in_array((int) ($values['visibility'] ?? 0), self::VISIBLE_VISIBILITIES, true);
    }

    /**
     * Product being viewed, as registered by the product controller.
     */
    protected function _getProductId(): ?int
    {
        $product = Mage::registry('current_product');

        return $product instanceof Mage_Catalog_Model_Product && $product->getId() ? (int) $product->getId() : null;
    }

    /**
     * @return array{status?: mixed, visibility?: mixed} store-level values (falling back to default)
     */
    protected function _getStoreAttributeValues(int $productId, int $storeId): array
    {
        $values = Mage::getResourceSingleton('catalog/product')
            ->getAttributeRawValue($productId, ['status', 'visibility'], $storeId);

        return is_array($values) ? $values : [];
    }

    /**
     * @return list<int|string>
     */
    protected function _getProductWebsiteIds(int $productId): array
    {
        return Mage::getResourceSingleton('catalog/product')->getWebsiteIds($productId);
    }

    protected function _getStoreWebsiteId(int $storeId): int
    {
        return (int) Mage::app()->getStore($storeId)->getWebsiteId();
    }

    protected function _getUrlModel(): Bpf_Hreflang_Model_Url
    {
        return Mage::getSingleton('bpf_hreflang/url');
    }
}
