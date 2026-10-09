<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_BuilderTest extends TestCase
{
    /**
     * Store view settings used by the fake helper, keyed by store ID.
     *
     * @var array<int, array{code: string, enabled: bool, noindex: bool}>
     */
    private array $settings = [];

    /** @var array<int, Mage_Core_Model_Store> */
    private array $groupStores = [];

    /** @var array{website?: int, default?: int} x_default_store as read at website or default scope */
    private array $xDefaultByScope = [];

    private string $groupScope = Bpf_Hreflang_Helper_Data::GROUP_SCOPE_WEBSITE;

    /** @var list<string> excluded_actions as returned by the helper (defaults from SPEC 6.4) */
    private array $excludedActions = [
        'cms_index_noRoute', 'cms_index_defaultNoRoute', 'catalogsearch_*', 'checkout_*',
        'customer_*', 'wishlist_*', 'sales_*', 'review_*', 'contacts_*',
    ];

    private function addStore(int $id, string $code, bool $active = true, bool $enabled = true, bool $noindex = false): Mage_Core_Model_Store
    {
        $store = new Mage_Core_Model_Store(['store_id' => $id, 'is_active' => $active ? 1 : 0, 'name' => "Store {$id}"]);
        $this->groupStores[$id] = $store;
        $this->settings[$id] = ['code' => $code, 'enabled' => $enabled, 'noindex' => $noindex];

        return $store;
    }

    /**
     * @return Bpf_Hreflang_Helper_Data&MockObject
     */
    private function helper(): Bpf_Hreflang_Helper_Data
    {
        $settings = &$this->settings;
        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)
            ->onlyMethods(['getGroupStores', 'isEnabled', 'getLocaleCode', 'isStoreNoindex', 'getXDefaultStoreId', 'getGroupScope', 'getExcludedActions'])
            ->getMock();
        $helper->method('getExcludedActions')->willReturnCallback(fn () => $this->excludedActions);
        $helper->method('getXDefaultStoreId')->willReturnCallback(
            fn ($scope) => $this->xDefaultByScope[$scope instanceof Mage_Core_Model_Store ? 'website' : 'default'] ?? null,
        );
        $helper->method('getGroupScope')->willReturnCallback(fn () => $this->groupScope);
        $helper->method('getGroupStores')->willReturnCallback(fn () => $this->groupStores);
        $helper->method('isEnabled')->willReturnCallback(static fn ($store) => $settings[$store->getId()]['enabled']);
        $helper->method('getLocaleCode')->willReturnCallback(static fn ($store) => $settings[$store->getId()]['code']);
        $helper->method('isStoreNoindex')->willReturnCallback(static fn ($store) => $settings[$store->getId()]['noindex']);

        return $helper;
    }

    /**
     * @param list<string> $extraMethods further protected methods to stub
     * @return Bpf_Hreflang_Model_Builder&MockObject
     */
    private function builder(array $extraMethods = []): Bpf_Hreflang_Model_Builder
    {
        $builder = $this->getMockBuilder(Bpf_Hreflang_Model_Builder::class)
            ->onlyMethods(array_merge(['_getHelper'], $extraMethods))
            ->getMock();
        $builder->method('_getHelper')->willReturn($this->helper());

        return $builder;
    }

    public function testCandidatesAreGroupStoresThatTakePart(): void
    {
        $pl = $this->addStore(1, 'pl');
        $en = $this->addStore(2, 'en');
        $this->addStore(3, 'de', active: false);
        $this->addStore(4, 'fr', enabled: false);
        $this->addStore(5, '');
        $this->addStore(6, 'cs', noindex: true);

        $this->assertSame([1 => $pl, 2 => $en], $this->builder()->getCandidateStores($pl));
    }

    public function testCandidatesDoNotDependOnCurrentStore(): void
    {
        $pl = $this->addStore(1, 'pl');
        $en = $this->addStore(2, 'en');
        $de = $this->addStore(3, '');

        $builder = $this->builder();
        $this->assertSame(
            array_keys($builder->getCandidateStores($pl)),
            array_keys($builder->getCandidateStores($en)),
        );
        $this->assertSame([1, 2], array_keys($builder->getCandidateStores($de)));
    }

    private function resolver(bool $canResolve = true): Bpf_Hreflang_Model_Resolver_Interface
    {
        return new class ($canResolve) implements Bpf_Hreflang_Model_Resolver_Interface {
            public function __construct(private bool $canResolve)
            {
            }

            public function canResolve(Mage_Core_Controller_Request_Http $request): bool
            {
                return $this->canResolve;
            }

            public function resolve(Mage_Core_Controller_Request_Http $request, array $storeIds): array
            {
                return [];
            }

            public function getCacheKey(Mage_Core_Controller_Request_Http $request): string
            {
                return 'fake';
            }

            public function getCacheTags(Mage_Core_Controller_Request_Http $request): array
            {
                return [];
            }
        };
    }

    /**
     * @param array<string, array{class: string, actions: list<string>}> $config
     * @param array<string, mixed> $instances class alias => instance created for it
     * @param array<string, mixed> $eventResolvers code => object added by observers
     * @return Bpf_Hreflang_Model_Builder&MockObject
     */
    private function builderWithResolvers(array $config, array $instances, array $eventResolvers = []): Bpf_Hreflang_Model_Builder
    {
        $builder = $this->builder(['_getResolverConfig', '_createResolver', '_collectEventResolvers', '_log']);
        $builder->method('_getResolverConfig')->willReturn($config);
        $builder->method('_createResolver')->willReturnCallback(static fn (string $class) => $instances[$class] ?? false);
        $builder->method('_collectEventResolvers')->willReturn($eventResolvers);

        return $builder;
    }

    public function testConfigResolversAreFilteredByAction(): void
    {
        $product = $this->resolver();
        $category = $this->resolver();
        $builder = $this->builderWithResolvers(
            [
                'product' => ['class' => 'x/product', 'actions' => ['catalog_product_view']],
                'category' => ['class' => 'x/category', 'actions' => ['catalog_category_view']],
            ],
            ['x/product' => $product, 'x/category' => $category],
        );

        $this->assertSame([$product], $builder->getResolvers('catalog_product_view'));
        $this->assertSame([], $builder->getResolvers('cms_page_view'));
    }

    public function testActionMatchingIsCaseInsensitive(): void
    {
        $home = $this->resolver();
        $builder = $this->builderWithResolvers(
            ['home' => ['class' => 'x/home', 'actions' => ['cms_index_noroute']]],
            ['x/home' => $home],
        );

        $this->assertSame([$home], $builder->getResolvers('cms_index_noRoute'));
    }

    public function testEventResolversComeFirstAndApplyToAnyAction(): void
    {
        $product = $this->resolver();
        $blog = $this->resolver();
        $builder = $this->builderWithResolvers(
            ['product' => ['class' => 'x/product', 'actions' => ['catalog_product_view']]],
            ['x/product' => $product],
            ['blog_post' => $blog],
        );

        $this->assertSame([$blog, $product], $builder->getResolvers('catalog_product_view'));
        $this->assertSame([$blog], $builder->getResolvers('blog_post_view'));
    }

    public function testEventResolverReplacesBuiltInWithSameCode(): void
    {
        $custom = $this->resolver();
        $builder = $this->builderWithResolvers(
            ['product' => ['class' => 'x/product', 'actions' => ['catalog_product_view']]],
            ['x/product' => $this->resolver()],
            ['product' => $custom],
        );

        $this->assertSame([$custom], $builder->getResolvers('catalog_product_view'));
    }

    public function testInvalidResolversAreSkippedAndLogged(): void
    {
        $builder = $this->builderWithResolvers(
            ['broken' => ['class' => 'x/broken', 'actions' => ['catalog_product_view']]],
            ['x/broken' => new Varien_Object()],
            ['not_a_resolver' => new Varien_Object()],
        );
        $builder->expects($this->exactly(2))->method('_log');

        $this->assertSame([], $builder->getResolvers('catalog_product_view'));
    }

    public function testEventIsDispatchedOnce(): void
    {
        $builder = $this->builder(['_getResolverConfig', '_collectEventResolvers']);
        $builder->method('_getResolverConfig')->willReturn([]);
        $builder->expects($this->once())->method('_collectEventResolvers')->willReturn([]);

        $builder->getResolvers('catalog_product_view');
        $builder->getResolvers('cms_page_view');
    }

    public function testFirstResolverThatCanResolveWins(): void
    {
        $declines = $this->resolver(false);
        $accepts = $this->resolver(true);
        $later = $this->resolver(true);
        $builder = $this->builderWithResolvers(
            ['later' => ['class' => 'x/later', 'actions' => ['catalog_product_view']]],
            ['x/later' => $later],
            ['declines' => $declines, 'accepts' => $accepts],
        );
        $request = $this->createMock(Mage_Core_Controller_Request_Http::class);

        $this->assertSame($accepts, $builder->getResolver('catalog_product_view', $request));
    }

    public function testNoResolverForUnhandledPage(): void
    {
        $builder = $this->builderWithResolvers([], []);
        $request = $this->createMock(Mage_Core_Controller_Request_Http::class);

        $this->assertNull($builder->getResolver('cms_page_view', $request));
    }

    public function testResolverConfigIsReadFromConfigXmlFormat(): void
    {
        $config = new Mage_Core_Model_Config_Base(<<<'XML'
            <config><global><bpf_hreflang><resolvers>
                <product>
                    <class>bpf_hreflang/resolver_product</class>
                    <actions><catalog_product_view/></actions>
                </product>
                <home>
                    <class>bpf_hreflang/resolver_home</class>
                    <actions><cms_index_index/><Cms_Index_Other/></actions>
                </home>
            </resolvers></bpf_hreflang></global></config>
            XML);
        $configProperty = new ReflectionProperty(Mage::class, '_config');
        $configProperty->setAccessible(true);
        $previous = $configProperty->getValue();
        $configProperty->setValue(null, $config);

        try {
            $method = new ReflectionMethod(Bpf_Hreflang_Model_Builder::class, '_getResolverConfig');
            $method->setAccessible(true);
            $result = $method->invoke(new Bpf_Hreflang_Model_Builder());
        } finally {
            $configProperty->setValue(null, $previous);
        }

        $this->assertSame([
            'product' => ['class' => 'bpf_hreflang/resolver_product', 'actions' => ['catalog_product_view']],
            'home' => ['class' => 'bpf_hreflang/resolver_home', 'actions' => ['cms_index_index', 'cms_index_other']],
        ], $result);
    }

    /**
     * @param array<int, string> $urls store ID => URL the resolver returns
     */
    private function resolverReturning(array $urls): Bpf_Hreflang_Model_Resolver_Interface
    {
        $resolver = $this->createMock(Bpf_Hreflang_Model_Resolver_Interface::class);
        $resolver->method('resolve')->willReturnCallback(
            static fn ($request, array $storeIds) => array_intersect_key($urls, array_flip($storeIds)),
        );

        return $resolver;
    }

    /**
     * @param array<int, string> $urls
     * @return array<string, string>
     */
    private function alternates(array $urls, Mage_Core_Model_Store $current, ?Bpf_Hreflang_Model_Builder $builder = null): array
    {
        $builder ??= $this->builder(['_log']);

        return $builder->getAlternates(
            $this->resolverReturning($urls),
            $this->createMock(Mage_Core_Controller_Request_Http::class),
            $current,
        );
    }

    public function testAlternatesIncludeSelfReferenceAndXDefault(): void
    {
        $pl = $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $this->addStore(3, 'de-AT');
        $this->xDefaultByScope = ['website' => 1];

        $this->assertSame([
            'pl' => 'https://example.com/kubek.html',
            'en' => 'https://example.com/en/mug.html',
            'de-AT' => 'https://example.com/at/becher.html',
            'x-default' => 'https://example.com/kubek.html',
        ], $this->alternates([
            1 => 'https://example.com/kubek.html',
            2 => 'https://example.com/en/mug.html',
            3 => 'https://example.com/at/becher.html',
        ], $pl));
    }

    public function testEveryVersionGetsTheSameAlternates(): void
    {
        $stores = [$this->addStore(1, 'pl'), $this->addStore(2, 'en'), $this->addStore(3, 'de-AT')];
        $this->xDefaultByScope = ['website' => 1];
        $urls = [1 => 'https://example.com/a', 2 => 'https://example.com/en/b', 3 => 'https://example.com/at/c'];

        $maps = array_map(fn ($store) => $this->alternates($urls, $store), $stores);

        $this->assertSame($maps[0], $maps[1]);
        $this->assertSame($maps[0], $maps[2]);
    }

    public function testUnavailableVersionDisappearsFromAllVersions(): void
    {
        $pl = $this->addStore(1, 'pl');
        $en = $this->addStore(2, 'en');
        $this->addStore(3, 'de-AT');
        // The product is disabled in store 3, so the resolver does not return it.
        $urls = [1 => 'https://example.com/a', 2 => 'https://example.com/en/b'];

        $expected = ['pl' => 'https://example.com/a', 'en' => 'https://example.com/en/b'];
        $this->assertSame($expected, $this->alternates($urls, $pl));
        $this->assertSame($expected, $this->alternates($urls, $en));
    }

    public function testNoAlternatesWithoutSelfReference(): void
    {
        $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $de = $this->addStore(3, 'de-AT');

        $this->assertSame([], $this->alternates([1 => 'https://example.com/a', 2 => 'https://example.com/en/b'], $de));
    }

    public function testNoAlternatesWhenCurrentStoreIsNotACandidate(): void
    {
        $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $excluded = $this->addStore(3, '');

        $this->assertSame([], $this->alternates([1 => 'a', 2 => 'b', 3 => 'c'], $excluded));
    }

    public function testNoAlternatesBelowTwoVersionsEvenWithXDefault(): void
    {
        $pl = $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $this->xDefaultByScope = ['website' => 1];

        $this->assertSame([], $this->alternates([1 => 'https://example.com/a'], $pl));
    }

    public function testXDefaultOmittedWhenItsVersionIsUnavailable(): void
    {
        $this->addStore(1, 'pl');
        $en = $this->addStore(2, 'en');
        $this->addStore(3, 'de-AT');
        $this->xDefaultByScope = ['website' => 1];

        $this->assertSame(
            ['en' => 'https://example.com/en/b', 'de-AT' => 'https://example.com/at/c'],
            $this->alternates([2 => 'https://example.com/en/b', 3 => 'https://example.com/at/c'], $en),
        );
    }

    public function testXDefaultOmittedWhenNotConfigured(): void
    {
        $pl = $this->addStore(1, 'pl');
        $this->addStore(2, 'en');

        $this->assertArrayNotHasKey('x-default', $this->alternates([1 => 'a', 2 => 'b'], $pl));
    }

    public function testGlobalScopeReadsXDefaultFromDefaultScope(): void
    {
        $pl = $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $this->groupScope = Bpf_Hreflang_Helper_Data::GROUP_SCOPE_GLOBAL;
        $this->xDefaultByScope = ['website' => 1, 'default' => 2];

        $this->assertSame('b', $this->alternates([1 => 'a', 2 => 'b'], $pl)['x-default']);
    }

    public function testUrlsForNonCandidateStoresAreIgnored(): void
    {
        $pl = $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $this->addStore(3, 'fr', enabled: false);

        $resolver = $this->createMock(Bpf_Hreflang_Model_Resolver_Interface::class);
        $resolver->method('resolve')->willReturn([1 => 'a', 2 => 'b', 3 => 'c', 99 => 'd', 4 => '']);

        $result = $this->builder(['_log'])->getAlternates(
            $resolver,
            $this->createMock(Mage_Core_Controller_Request_Http::class),
            $pl,
        );

        $this->assertSame(['pl' => 'a', 'en' => 'b'], $result);
    }

    public function testDuplicateCodeKeepsFirstStoreAndLogs(): void
    {
        $pl = $this->addStore(1, 'pl');
        $this->addStore(2, 'en');
        $this->addStore(3, 'en');
        $builder = $this->builder(['_log']);
        $builder->expects($this->once())->method('_log');

        $this->assertSame(['pl' => 'a', 'en' => 'b'], $this->alternates([1 => 'a', 2 => 'b', 3 => 'c'], $pl, $builder));
    }

    /**
     * @param array<string, mixed> $query
     * @return Mage_Core_Controller_Request_Http&MockObject
     */
    private function request(array $query = []): Mage_Core_Controller_Request_Http
    {
        $request = $this->createMock(Mage_Core_Controller_Request_Http::class);
        $request->method('getQuery')->willReturn($query);

        return $request;
    }

    /**
     * @dataProvider actionProvider
     */
    public function testIsActionExcluded(string $action, bool $expected): void
    {
        $this->assertSame($expected, $this->builder()->isActionExcluded($action));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function actionProvider(): array
    {
        return [
            '404 page' => ['cms_index_noRoute', true],
            'default 404 page' => ['cms_index_defaultNoRoute', true],
            'search results' => ['catalogsearch_result_index', true],
            'advanced search' => ['catalogsearch_advanced_result', true],
            'cart' => ['checkout_cart_index', true],
            'customer account' => ['customer_account_index', true],
            'case-insensitive' => ['CMS_INDEX_NOROUTE', true],
            'product page' => ['catalog_product_view', false],
            'category page' => ['catalog_category_view', false],
            'cms page' => ['cms_page_view', false],
            'home page' => ['cms_index_index', false],
            'exact entry is not a prefix' => ['cms_index_noRouteSomething', false],
            'prefix needs its underscore' => ['checkoutx_cart_index', false],
        ];
    }

    public function testEmptyExclusionListExcludesNothing(): void
    {
        $this->excludedActions = [];

        $this->assertFalse($this->builder()->isActionExcluded('cms_index_noRoute'));
    }

    /**
     * @dataProvider queryProvider
     * @param array<string, mixed> $query
     */
    public function testHasSignificantQueryParams(array $query, bool $expected): void
    {
        $this->assertSame($expected, $this->builder()->hasSignificantQueryParams($this->request($query)));
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public function queryProvider(): array
    {
        return [
            'no query' => [[], false],
            'tracking only' => [['utm_source' => 'x', 'utm_campaign' => 'y', 'gclid' => 'z', 'fbclid' => 'f'], false],
            'store switch' => [['___store' => 'en', '___from_store' => 'pl'], false],
            'session id' => [['SID' => 'abc'], false],
            'layered filter' => [['color' => '12'], true],
            'paging' => [['p' => '2'], true],
            'sorting with tracking' => [['dir' => 'asc', 'utm_source' => 'x'], true],
        ];
    }

    public function testExcludedPageGetsNoResolver(): void
    {
        $builder = $this->builderWithResolvers(
            [
                'home' => ['class' => 'x/home', 'actions' => ['cms_index_index', 'cms_index_noroute']],
                'product' => ['class' => 'x/product', 'actions' => ['catalog_product_view']],
            ],
            ['x/home' => $this->resolver(), 'x/product' => $this->resolver()],
        );

        $this->assertNull($builder->getResolverForRequest('cms_index_noRoute', $this->request()));
        $this->assertNull($builder->getResolverForRequest('catalog_product_view', $this->request(['color' => '1'])));
        $this->assertNotNull($builder->getResolverForRequest('catalog_product_view', $this->request(['utm_source' => 'x'])));
        $this->assertNotNull($builder->getResolverForRequest('cms_index_index', $this->request()));
    }
}
