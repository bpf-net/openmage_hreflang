<?php

/**
 * Renders <link rel="alternate" hreflang="…"> tags as a child of the "head" block.
 */
class Bpf_Hreflang_Block_Head extends Mage_Core_Block_Template
{
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
