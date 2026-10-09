<?php

/**
 * Finds the versions of the current page in other store views.
 *
 * Built-in resolvers are declared in config.xml under global/bpf_hreflang/resolvers/<code>;
 * other modules can add theirs through the bpf_hreflang_resolvers_collect event.
 */
interface Bpf_Hreflang_Model_Resolver_Interface
{
    /**
     * Whether this resolver handles the current request.
     */
    public function canResolve(Mage_Core_Controller_Request_Http $request): bool;

    /**
     * URLs of the current page in the given store views. Returns only the store views in which
     * the page exists and is available; the result must not depend on the current store view,
     * so that every version emits the same set of alternates.
     *
     * @param list<int> $storeIds
     * @return array<int, string> store ID => absolute URL
     */
    public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds): array;

    /**
     * Identifier of the page for the block cache key, e.g. "product:42".
     */
    public function getCacheKey(Mage_Core_Controller_Request_Http $request): string;

    /**
     * Cache tags of the entities the result depends on, e.g. "catalog_product_42".
     *
     * @return list<string>
     */
    public function getCacheTags(Mage_Core_Controller_Request_Http $request): array;
}
