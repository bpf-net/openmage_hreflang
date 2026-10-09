<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_Resolver_CmsTest extends TestCase
{
    /**
     * @param array<int, int> $groupItems store ID => page ID of the current page's group
     * @param array<int, array{identifier: string, is_active: bool, store_ids: list<int>}> $pages
     * @return Bpf_Hreflang_Model_Resolver_Cms&MockObject
     */
    private function resolver(
        ?int $currentPageId,
        array $groupItems,
        array $pages,
        int $currentStoreId = 1,
        string $currentIdentifier = 'some-page',
        string $noRouteIdentifier = 'no-route',
    ): Bpf_Hreflang_Model_Resolver_Cms {
        $groupResource = $this->createMock(Bpf_Hreflang_Model_Resource_Group::class);
        $groupResource->method('getEntityGroupItems')->willReturn($groupItems);

        $urlModel = $this->createMock(Bpf_Hreflang_Model_Url::class);
        $urlModel->method('getCmsPageUrl')->willReturnCallback(
            static fn (string $identifier, int $storeId) => "https://example.com/{$storeId}/{$identifier}",
        );

        $resolver = $this->getMockBuilder(Bpf_Hreflang_Model_Resolver_Cms::class)
            ->onlyMethods(['_getCurrentPageId', '_getPagesData', '_getGroupResource', '_getUrlModel', '_getCurrentStoreId', '_getCurrentPageIdentifier', '_getNoRouteIdentifier'])
            ->getMock();
        $resolver->method('_getCurrentPageId')->willReturn($currentPageId);
        $resolver->method('_getCurrentStoreId')->willReturn($currentStoreId);
        $resolver->method('_getCurrentPageIdentifier')->willReturn($currentIdentifier);
        $resolver->method('_getNoRouteIdentifier')->willReturn($noRouteIdentifier);
        $resolver->method('_getPagesData')->willReturnCallback(
            static fn (array $ids) => array_intersect_key($pages, array_flip($ids)),
        );
        $resolver->method('_getGroupResource')->willReturn($groupResource);
        $resolver->method('_getUrlModel')->willReturn($urlModel);

        return $resolver;
    }

    /**
     * @param list<int> $storeIds
     * @return array{identifier: string, is_active: bool, store_ids: list<int>}
     */
    private function page(string $identifier, array $storeIds, bool $active = true): array
    {
        return ['identifier' => $identifier, 'is_active' => $active, 'store_ids' => $storeIds];
    }

    private function request(): Mage_Core_Controller_Request_Http
    {
        return $this->createMock(Mage_Core_Controller_Request_Http::class);
    }

    public function testResolvesOnlyWithAPage(): void
    {
        $this->assertTrue($this->resolver(5, [], [])->canResolve($this->request()));
        $this->assertFalse($this->resolver(null, [], [])->canResolve($this->request()));
        $this->assertSame([], $this->resolver(null, [], [])->resolve($this->request(), [1, 2]));
    }

    public function testPageForAllStoreViewsIsItsOwnVersionEverywhere(): void
    {
        $resolver = $this->resolver(5, [], [5 => $this->page('about', [0])]);

        $this->assertSame([
            1 => 'https://example.com/1/about',
            2 => 'https://example.com/2/about',
            3 => 'https://example.com/3/about',
        ], $resolver->resolve($this->request(), [1, 2, 3]));
    }

    public function testPageForSeveralStoreViewsIsItsOwnVersionThere(): void
    {
        $resolver = $this->resolver(5, [], [5 => $this->page('about', [1, 3])]);

        $this->assertSame([1, 3], array_keys($resolver->resolve($this->request(), [1, 2, 3])));
    }

    public function testGroupDefinesTheVersions(): void
    {
        $resolver = $this->resolver(
            5,
            [1 => 5, 2 => 6, 3 => 7],
            [
                5 => $this->page('o-nas', [1]),
                6 => $this->page('about-us', [2]),
                7 => $this->page('uber-uns', [3]),
            ],
        );

        $this->assertSame([
            1 => 'https://example.com/1/o-nas',
            2 => 'https://example.com/2/about-us',
            3 => 'https://example.com/3/uber-uns',
        ], $resolver->resolve($this->request(), [1, 2, 3]));
    }

    public function testGroupOverridesAllStoreViewsAssignment(): void
    {
        // Page 5 is shown in every store view, but its group pairs only stores 1 and 2.
        $resolver = $this->resolver(
            5,
            [1 => 5, 2 => 6],
            [5 => $this->page('o-nas', [0]), 6 => $this->page('about-us', [2])],
        );

        $this->assertSame([1, 2], array_keys($resolver->resolve($this->request(), [1, 2, 3])));
    }

    public function testInactiveOrUnassignedVersionIsLeftOut(): void
    {
        $resolver = $this->resolver(
            5,
            [1 => 5, 2 => 6, 3 => 7],
            [
                5 => $this->page('o-nas', [1]),
                6 => $this->page('about-us', [2], active: false),
                7 => $this->page('uber-uns', [1]),
            ],
        );

        $this->assertSame([1], array_keys($resolver->resolve($this->request(), [1, 2, 3])));
    }

    public function testDeletedVersionIsLeftOut(): void
    {
        $resolver = $this->resolver(5, [1 => 5, 2 => 6], [5 => $this->page('o-nas', [1])]);

        $this->assertSame([1], array_keys($resolver->resolve($this->request(), [1, 2])));
    }

    public function testOnlyRequestedStoresAreResolved(): void
    {
        $resolver = $this->resolver(5, [1 => 5, 2 => 6], [5 => $this->page('o-nas', [1]), 6 => $this->page('about-us', [2])]);

        $this->assertSame([2], array_keys($resolver->resolve($this->request(), [2])));
    }

    public function testCacheKeyAndTagsCoverAllVersions(): void
    {
        $resolver = $this->resolver(5, [1 => 5, 2 => 6], []);

        $this->assertSame('cms_page:5', $resolver->getCacheKey($this->request()));
        $this->assertSame(['cms_page_5', 'cms_page_6', 'bpf_hreflang'], $resolver->getCacheTags($this->request()));
        $this->assertSame(['cms_page_9', 'bpf_hreflang'], $this->resolver(9, [], [])->getCacheTags($this->request()));
    }

    public function testRegisteredForCmsPageView(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 4) . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $cms = $config->global->bpf_hreflang->resolvers->cms;

        $this->assertSame('bpf_hreflang/resolver_cms', (string) $cms->class);
        $this->assertSame(['cms_page_view'], array_keys((array) $cms->actions));
    }

    public function testNoRoutePageAtItsOwnUrlIsNotResolved(): void
    {
        $resolver = $this->resolver(1, [], [1 => $this->page('no-route', [1, 2, 3])], currentIdentifier: 'no-route');

        $this->assertFalse($resolver->canResolve($this->request()));
    }

    public function testNoRoutePageIsCheckedPerStore(): void
    {
        $resolver = $this->resolver(1, [], [], currentIdentifier: 'not-found-de', noRouteIdentifier: 'no-route');

        $this->assertTrue($resolver->canResolve($this->request()));
    }

    public function testGroupedPageIsResolvedInStoreWhereItIsTheVersion(): void
    {
        $resolver = $this->resolver(4, [1 => 3, 2 => 4], [], currentStoreId: 2);

        $this->assertTrue($resolver->canResolve($this->request()));
    }

    public function testGroupedPageIsNotResolvedWhereGroupHasAnotherPage(): void
    {
        // Page 4 is the French version, but it is also shown in the English store, whose version is page 3.
        $resolver = $this->resolver(4, [1 => 3, 2 => 4], [], currentStoreId: 1);

        $this->assertFalse($resolver->canResolve($this->request()));
    }

    public function testGroupedPageIsNotResolvedInStoreOutsideTheGroup(): void
    {
        $resolver = $this->resolver(4, [1 => 3, 2 => 4], [], currentStoreId: 3);

        $this->assertFalse($resolver->canResolve($this->request()));
    }
}
