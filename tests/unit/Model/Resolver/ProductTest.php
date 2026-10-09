<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_Resolver_ProductTest extends TestCase
{
    private const ENABLED = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
    private const DISABLED = Mage_Catalog_Model_Product_Status::STATUS_DISABLED;

    /**
     * @param array<int, array{status?: int, visibility?: int}> $valuesByStore
     * @param list<int> $productWebsiteIds
     * @param array<int, int> $storeWebsites store ID => website ID
     * @return Bpf_Hreflang_Model_Resolver_Product&MockObject
     */
    private function resolver(
        ?int $productId,
        array $valuesByStore = [],
        array $productWebsiteIds = [1],
        array $storeWebsites = [1 => 1, 2 => 1, 3 => 1],
    ): Bpf_Hreflang_Model_Resolver_Product {
        $urlModel = $this->createMock(Bpf_Hreflang_Model_Url::class);
        $urlModel->method('getProductUrls')->willReturnCallback(
            static fn (int $id, array $storeIds) => array_combine($storeIds, array_map(static fn ($s) => "https://example.com/{$s}/p{$id}.html", $storeIds)) ?: [],
        );

        $resolver = $this->getMockBuilder(Bpf_Hreflang_Model_Resolver_Product::class)
            ->onlyMethods(['_getProductId', '_getStoreAttributeValues', '_getProductWebsiteIds', '_getStoreWebsiteId', '_getUrlModel'])
            ->getMock();
        $resolver->method('_getProductId')->willReturn($productId);
        $resolver->method('_getStoreAttributeValues')->willReturnCallback(static fn (int $id, int $storeId) => $valuesByStore[$storeId] ?? []);
        $resolver->method('_getProductWebsiteIds')->willReturn(array_map('strval', $productWebsiteIds));
        $resolver->method('_getStoreWebsiteId')->willReturnCallback(static fn (int $storeId) => $storeWebsites[$storeId]);
        $resolver->method('_getUrlModel')->willReturn($urlModel);

        return $resolver;
    }

    private function request(): Mage_Core_Controller_Request_Http
    {
        return $this->createMock(Mage_Core_Controller_Request_Http::class);
    }

    private function visible(int $status = self::ENABLED, int $visibility = Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH): array
    {
        return ['status' => $status, 'visibility' => $visibility];
    }

    public function testResolvesOnlyWithAProduct(): void
    {
        $this->assertTrue($this->resolver(42)->canResolve($this->request()));
        $this->assertFalse($this->resolver(null)->canResolve($this->request()));
        $this->assertSame([], $this->resolver(null)->resolve($this->request(), [1, 2]));
    }

    public function testReturnsUrlsOfStoresWhereProductIsAvailable(): void
    {
        $resolver = $this->resolver(42, [1 => $this->visible(), 2 => $this->visible(), 3 => $this->visible()]);

        $this->assertSame([
            1 => 'https://example.com/1/p42.html',
            2 => 'https://example.com/2/p42.html',
            3 => 'https://example.com/3/p42.html',
        ], $resolver->resolve($this->request(), [1, 2, 3]));
    }

    public function testDisabledProductIsLeftOut(): void
    {
        $resolver = $this->resolver(42, [1 => $this->visible(), 2 => $this->visible(self::DISABLED), 3 => $this->visible()]);

        $this->assertSame([1, 3], array_keys($resolver->resolve($this->request(), [1, 2, 3])));
    }

    /**
     * @dataProvider visibilityProvider
     */
    public function testVisibility(int $visibility, bool $available): void
    {
        $resolver = $this->resolver(42, [1 => $this->visible(visibility: $visibility)]);

        $this->assertSame($available ? [1] : [], array_keys($resolver->resolve($this->request(), [1])));
    }

    /**
     * @return array<string, array{int, bool}>
     */
    public function visibilityProvider(): array
    {
        return [
            'not visible individually' => [Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE, false],
            'catalog' => [Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG, true],
            'search' => [Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH, true],
            'catalog, search' => [Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH, true],
        ];
    }

    public function testProductNotAssignedToStoreWebsiteIsLeftOut(): void
    {
        $resolver = $this->resolver(
            42,
            [1 => $this->visible(), 2 => $this->visible(), 3 => $this->visible()],
            productWebsiteIds: [1],
            storeWebsites: [1 => 1, 2 => 1, 3 => 2],
        );

        $this->assertSame([1, 2], array_keys($resolver->resolve($this->request(), [1, 2, 3])));
    }

    public function testMissingAttributeValuesMeanUnavailable(): void
    {
        $resolver = $this->resolver(42, [1 => $this->visible()]);

        $this->assertSame([1], array_keys($resolver->resolve($this->request(), [1, 2])));
    }

    public function testCacheKeyAndTagsIdentifyTheProduct(): void
    {
        $resolver = $this->resolver(42);

        $this->assertSame('product:42', $resolver->getCacheKey($this->request()));
        $this->assertSame(['catalog_product_42'], $resolver->getCacheTags($this->request()));
    }

    public function testProductIdComesFromRegistry(): void
    {
        $method = new ReflectionMethod(Bpf_Hreflang_Model_Resolver_Product::class, '_getProductId');
        $method->setAccessible(true);
        $resolver = new Bpf_Hreflang_Model_Resolver_Product();

        $this->assertNull($method->invoke($resolver));

        $product = $this->createMock(Mage_Catalog_Model_Product::class);
        $product->method('getId')->willReturn('42');
        Mage::register('current_product', $product);
        try {
            $this->assertSame(42, $method->invoke($resolver));
        } finally {
            Mage::unregister('current_product');
        }
    }

    public function testRegisteredForProductPage(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 4) . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $product = $config->global->bpf_hreflang->resolvers->product;

        $this->assertSame('bpf_hreflang/resolver_product', (string) $product->class);
        $this->assertSame(['catalog_product_view'], array_keys((array) $product->actions));
    }
}
