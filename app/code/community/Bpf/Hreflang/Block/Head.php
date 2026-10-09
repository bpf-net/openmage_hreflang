<?php

/**
 * Renders <link rel="alternate" hreflang="…"> tags as a child of the "head" block.
 */
class Bpf_Hreflang_Block_Head extends Mage_Core_Block_Template
{
    public const CACHE_TAG = 'bpf_hreflang';

    public const CACHE_LIFETIME = 86400;

    /** @var array<string, string>|null */
    protected ?array $_alternates = null;

    protected bool $_resolverLoaded = false;

    protected ?Bpf_Hreflang_Model_Resolver_Interface $_resolver = null;

    /**
     * hreflang code => absolute URL for the current page; empty when no tags should be output.
     *
     * @return array<string, string>
     */
    public function getAlternates(): array
    {
        if ($this->_alternates === null) {
            $resolver = $this->_getResolver();
            $this->_alternates = $resolver
                ? $this->_getBuilder()->getAlternates($resolver, $this->_getRequestObject(), $this->_getStore())
                : [];
        }

        return $this->_alternates;
    }

    /**
     * Cached only for pages a resolver handles; everything else is rendered (as an empty string)
     * without touching the cache.
     *
     * @return int|null
     */
    public function getCacheLifetime()
    {
        return $this->_getResolver() ? self::CACHE_LIFETIME : null;
    }

    /**
     * Store view, page (action + resolver key) and HTTPS, which changes nothing in the URLs
     * but keeps secure and insecure renders apart.
     *
     * @return array<int|string, string>
     */
    public function getCacheKeyInfo()
    {
        $resolver = $this->_getResolver();
        $request = $this->_getRequestObject();

        return [
            'BPF_HREFLANG',
            (string) $this->getNameInLayout(),
            (string) $this->_getStore()->getId(),
            (string) $this->_getFullActionName(),
            $resolver ? $resolver->getCacheKey($request) : '',
            $request->isSecure() ? 'https' : 'http',
        ];
    }

    /**
     * Tags of the resolved entities, the module tag (cleaned on config save and when translation
     * groups change) and the config tag.
     *
     * @return list<string>
     */
    public function getCacheTags()
    {
        $resolver = $this->_getResolver();
        $tags = [self::CACHE_GROUP, self::CACHE_TAG, Mage_Core_Model_Config::CACHE_TAG];
        if ($resolver) {
            $tags = array_merge($tags, $resolver->getCacheTags($this->_getRequestObject()));
        }

        return array_values(array_unique($tags));
    }

    protected function _toHtml()
    {
        if ($this->getAlternates() === []) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Resolver for the current page, or null when the page gets no tags: module disabled in this
     * store view, page marked noindex, no controller action, excluded page or no resolver for it.
     */
    protected function _getResolver(): ?Bpf_Hreflang_Model_Resolver_Interface
    {
        if (!$this->_resolverLoaded) {
            $this->_resolverLoaded = true;
            $this->_resolver = $this->_findResolver();
        }

        return $this->_resolver;
    }

    protected function _findResolver(): ?Bpf_Hreflang_Model_Resolver_Interface
    {
        if (!$this->_getHelper()->isEnabled($this->_getStore())
            || stripos($this->_getRobots(), 'NOINDEX') !== false
        ) {
            return null;
        }

        $fullActionName = $this->_getFullActionName();
        if ($fullActionName === null) {
            return null;
        }

        return $this->_getBuilder()->getResolverForRequest($fullActionName, $this->_getRequestObject());
    }

    /**
     * Robots of the current page as set on the head block (falls back to the store default).
     */
    protected function _getRobots(): string
    {
        $head = $this->getLayout()->getBlock('head');

        return $head instanceof Mage_Page_Block_Html_Head ? (string) $head->getRobots() : '';
    }

    protected function _getFullActionName(): ?string
    {
        $action = Mage::app()->getFrontController()->getAction();

        return $action ? $action->getFullActionName() : null;
    }

    protected function _getRequestObject(): Mage_Core_Controller_Request_Http
    {
        return Mage::app()->getRequest();
    }

    protected function _getStore(): Mage_Core_Model_Store
    {
        return Mage::app()->getStore();
    }

    protected function _getBuilder(): Bpf_Hreflang_Model_Builder
    {
        return Mage::getSingleton('bpf_hreflang/builder');
    }

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
