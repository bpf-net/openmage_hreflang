<?php

/**
 * Category page (catalog_category_view): the same category ID in each store view, where the
 * category is active and belongs to the tree under the store's root category.
 */
class Bpf_Hreflang_Model_Resolver_Category implements Bpf_Hreflang_Model_Resolver_Interface
{
    public function canResolve(Mage_Core_Controller_Request_Http $request): bool
    {
        return $this->_getCategory() !== null;
    }

    public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds): array
    {
        $category = $this->_getCategory();
        if ($category === null) {
            return [];
        }

        $categoryId = (int) $category->getId();
        $pathIds = array_map('intval', explode('/', (string) $category->getPath()));
        $available = [];
        foreach ($storeIds as $storeId) {
            $rootId = $this->_getStoreRootCategoryId($storeId);
            if ($categoryId !== $rootId
                && in_array($rootId, $pathIds, true)
                && $this->_isActiveInStore($categoryId, $storeId)
            ) {
                $available[] = $storeId;
            }
        }

        return $this->_getUrlModel()->getCategoryUrls($categoryId, $available);
    }

    public function getCacheKey(Mage_Core_Controller_Request_Http $request): string
    {
        return 'category:' . $this->_getCategory()?->getId();
    }

    public function getCacheTags(Mage_Core_Controller_Request_Http $request): array
    {
        return [Mage_Catalog_Model_Category::CACHE_TAG . '_' . $this->_getCategory()?->getId()];
    }

    /**
     * Category being viewed, as registered by the category controller.
     */
    protected function _getCategory(): ?Mage_Catalog_Model_Category
    {
        $category = Mage::registry('current_category');

        return $category instanceof Mage_Catalog_Model_Category && $category->getId() ? $category : null;
    }

    protected function _isActiveInStore(int $categoryId, int $storeId): bool
    {
        return (bool) Mage::getResourceSingleton('catalog/category')
            ->getAttributeRawValue($categoryId, 'is_active', $storeId);
    }

    protected function _getStoreRootCategoryId(int $storeId): int
    {
        return (int) Mage::app()->getStore($storeId)->getRootCategoryId();
    }

    protected function _getUrlModel(): Bpf_Hreflang_Model_Url
    {
        return Mage::getSingleton('bpf_hreflang/url');
    }
}
