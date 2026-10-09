<?php

/**
 * Builds the hreflang => URL map for the current page.
 */
class Bpf_Hreflang_Model_Builder
{
    public const XML_PATH_RESOLVERS = 'global/bpf_hreflang/resolvers';

    /**
     * Dispatched with a Varien_Object "resolvers"; observers add resolver instances keyed by code:
     * $observer->getEvent()->getResolvers()->setData('blog_post', $resolver). A resolver added
     * under the code of a built-in one replaces it.
     */
    public const EVENT_RESOLVERS_COLLECT = 'bpf_hreflang_resolvers_collect';

    /** @var array<string, Bpf_Hreflang_Model_Resolver_Interface>|null */
    protected ?array $_eventResolvers = null;

    /**
     * The first resolver that handles the request: resolvers added through the event first
     * (asked through canResolve() only), then built-in ones declared for the action.
     */
    public function getResolver(string $fullActionName, Mage_Core_Controller_Request_Http $request): ?Bpf_Hreflang_Model_Resolver_Interface
    {
        foreach ($this->getResolvers($fullActionName) as $resolver) {
            if ($resolver->canResolve($request)) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Resolvers to try for the action, in order.
     *
     * @return list<Bpf_Hreflang_Model_Resolver_Interface>
     */
    public function getResolvers(string $fullActionName): array
    {
        $eventResolvers = $this->_getEventResolvers();
        $resolvers = array_values($eventResolvers);
        $action = strtolower($fullActionName);

        foreach ($this->_getResolverConfig() as $code => $config) {
            if (isset($eventResolvers[$code]) || !in_array($action, $config['actions'], true)) {
                continue;
            }

            $resolver = $this->_createResolver($config['class']);
            if ($resolver instanceof Bpf_Hreflang_Model_Resolver_Interface) {
                $resolvers[] = $resolver;
            } else {
                $this->_log("Resolver \"{$code}\" ({$config['class']}) does not implement Bpf_Hreflang_Model_Resolver_Interface; skipped.");
            }
        }

        return $resolvers;
    }

    /**
     * @return array<string, Bpf_Hreflang_Model_Resolver_Interface>
     */
    protected function _getEventResolvers(): array
    {
        if ($this->_eventResolvers === null) {
            $this->_eventResolvers = [];
            foreach ($this->_collectEventResolvers() as $code => $resolver) {
                if ($resolver instanceof Bpf_Hreflang_Model_Resolver_Interface) {
                    $this->_eventResolvers[(string) $code] = $resolver;
                } else {
                    $this->_log("Resolver \"{$code}\" added through " . self::EVENT_RESOLVERS_COLLECT
                        . ' does not implement Bpf_Hreflang_Model_Resolver_Interface; skipped.');
                }
            }
        }

        return $this->_eventResolvers;
    }

    /**
     * @return array<string, mixed> code => resolver added by observers
     */
    protected function _collectEventResolvers(): array
    {
        $container = new Varien_Object();
        Mage::dispatchEvent(self::EVENT_RESOLVERS_COLLECT, ['resolvers' => $container]);

        return $container->getData();
    }

    /**
     * Built-in resolvers declared as:
     * <global><bpf_hreflang><resolvers><code>
     *     <class>model/alias</class>
     *     <actions><catalog_product_view/></actions>
     * </code></resolvers></bpf_hreflang></global>
     *
     * @return array<string, array{class: string, actions: list<string>}> code => class alias and lowercased actions
     */
    protected function _getResolverConfig(): array
    {
        $node = Mage::getConfig()->getNode(self::XML_PATH_RESOLVERS);
        if (!$node) {
            return [];
        }

        $config = [];
        foreach ($node->children() as $code => $resolver) {
            $actions = [];
            foreach ($resolver->actions ? $resolver->actions->children() : [] as $action => $unused) {
                $actions[] = strtolower((string) $action);
            }
            $config[(string) $code] = ['class' => (string) $resolver->class, 'actions' => $actions];
        }

        return $config;
    }

    /**
     * @return mixed
     */
    protected function _createResolver(string $class)
    {
        return Mage::getModel($class);
    }

    protected function _log(string $message): void
    {
        Mage::log($message, Zend_Log::WARN);
    }

    /**
     * Store views that can appear as alternates of the current one: those of its group
     * (website or all, per group_scope) that are active, have the module enabled, have
     * a hreflang code and are not NOINDEX by default.
     *
     * The selection depends only on the group, never on which member is current, so every
     * version of a page works with the same candidates.
     *
     * @return array<int, Mage_Core_Model_Store> keyed by store ID
     */
    public function getCandidateStores(Mage_Core_Model_Store $currentStore): array
    {
        $helper = $this->_getHelper();
        $candidates = [];

        foreach ($helper->getGroupStores($currentStore) as $store) {
            if (!$store->getIsActive()
                || !$helper->isEnabled($store)
                || $helper->getLocaleCode($store) === ''
                || $helper->isStoreNoindex($store)
            ) {
                continue;
            }

            $candidates[(int) $store->getId()] = $store;
        }

        return $candidates;
    }

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
