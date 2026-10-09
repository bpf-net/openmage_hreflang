<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_UrlTest extends TestCase
{
    /**
     * Store whose base URLs mimic Mage_Core_Model_Store::getBaseUrl().
     *
     * @return Mage_Core_Model_Store&MockObject
     */
    private function store(int $id, string $code, bool $secureFrontend, bool $storeInUrl = false): Mage_Core_Model_Store
    {
        $store = $this->createMock(Mage_Core_Model_Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('isFrontUrlSecure')->willReturn($secureFrontend);
        $store->method('getStoreInUrl')->willReturn($storeInUrl);
        $store->method('getBaseUrl')->willReturnCallback(
            static function (string $type, $secure) use ($code, $storeInUrl): string {
                $url = ($secure ? 'https' : 'http') . '://example.com/';
                if ($type === Mage_Core_Model_Store::URL_TYPE_LINK && $storeInUrl) {
                    $url .= $code . '/';
                }
                return $url;
            },
        );

        return $store;
    }

    /**
     * @param list<Mage_Core_Model_Store> $stores
     * @param list<int> $rootStoreIds stores served without the store code
     * @param list<string> $extraMethods further protected methods to stub
     * @return Bpf_Hreflang_Model_Url&MockObject
     */
    private function urlModel(array $stores, array $rootStoreIds = [], array $extraMethods = []): Bpf_Hreflang_Model_Url
    {
        $byId = [];
        foreach ($stores as $store) {
            $byId[$store->getId()] = $store;
        }

        $model = $this->getMockBuilder(Bpf_Hreflang_Model_Url::class)
            ->onlyMethods(array_merge(['_getStore', '_getRootStoreIds'], $extraMethods))
            ->getMock();
        $model->method('_getStore')->willReturnCallback(static fn (int $id) => $byId[$id]);
        $model->method('_getRootStoreIds')->willReturn($rootStoreIds);

        return $model;
    }

    public function testHomeUrlUsesHttpsWhenFrontendIsSecure(): void
    {
        $model = $this->urlModel([$this->store(1, 'pl', true)]);

        $this->assertSame('https://example.com/', $model->getHomeUrl(1));
    }

    public function testHomeUrlUsesHttpWhenFrontendIsNotSecure(): void
    {
        $model = $this->urlModel([$this->store(1, 'pl', false)]);

        $this->assertSame('http://example.com/', $model->getHomeUrl(1));
    }

    public function testHomeUrlKeepsStoreCodeByDefault(): void
    {
        $model = $this->urlModel([$this->store(2, 'en', true, true)]);

        $this->assertSame('https://example.com/en/', $model->getHomeUrl(2));
    }

    public function testRootStoreHomeUrlHasNoStoreCode(): void
    {
        $model = $this->urlModel([$this->store(1, 'pl', true, true), $this->store(2, 'en', true, true)], [1]);

        $this->assertSame('https://example.com/', $model->getHomeUrl(1));
        $this->assertSame('https://example.com/en/', $model->getHomeUrl(2));
    }

    public function testRootStoreWithoutStoreCodeInUrlIsUnchanged(): void
    {
        $model = $this->urlModel([$this->store(1, 'pl', false, false)], [1]);

        $this->assertSame('http://example.com/', $model->getHomeUrl(1));
    }

    /**
     * @param array<string, array<int, string>> $rewrites id_path => [store ID => request path]
     * @param list<int> $rootStoreIds
     * @return Bpf_Hreflang_Model_Url&MockObject
     */
    private function urlModelWithRewrites(array $rewrites, array $rootStoreIds = []): Bpf_Hreflang_Model_Url
    {
        $model = $this->urlModel(
            [$this->store(1, 'pl', true, true), $this->store(2, 'en', true, true), $this->store(3, 'at', false, true)],
            $rootStoreIds,
            ['_fetchRequestPaths'],
        );
        $model->method('_fetchRequestPaths')->willReturnCallback(
            static fn (string $idPath, array $storeIds): array => array_intersect_key($rewrites[$idPath] ?? [], array_flip($storeIds)),
        );

        return $model;
    }

    public function testProductUrlsUseEachStoresRewrite(): void
    {
        $model = $this->urlModelWithRewrites(
            ['product/42' => [1 => 'kubek.html', 2 => 'mug.html', 3 => 'becher.html']],
            [1],
        );

        $this->assertSame([
            1 => 'https://example.com/kubek.html',
            2 => 'https://example.com/en/mug.html',
            3 => 'http://example.com/at/becher.html',
        ], $model->getProductUrls(42, [1, 2, 3]));
    }

    public function testProductUrlsSkipStoresWithoutRewrite(): void
    {
        $model = $this->urlModelWithRewrites(['product/42' => [2 => 'mug.html']]);

        $this->assertSame([2 => 'https://example.com/en/mug.html'], $model->getProductUrls(42, [1, 2]));
    }

    public function testProductUrlsOnlyForRequestedStores(): void
    {
        $model = $this->urlModelWithRewrites(['product/42' => [1 => 'kubek.html', 2 => 'mug.html']]);

        $this->assertSame([2 => 'https://example.com/en/mug.html'], $model->getProductUrls(42, [2]));
    }

    public function testFetchRequestPathsWithNoStoresSkipsTheDatabase(): void
    {
        $method = new ReflectionMethod(Bpf_Hreflang_Model_Url::class, '_fetchRequestPaths');
        $method->setAccessible(true);

        $this->assertSame([], $method->invoke(new Bpf_Hreflang_Model_Url(), 'product/42', []));
    }
}
