<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_Resolver_CategoryTest extends TestCase
{
    private function category(int $id, string $path): Mage_Catalog_Model_Category
    {
        $category = $this->getMockBuilder(Mage_Catalog_Model_Category::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId'])
            ->addMethods(['getPath'])
            ->getMock();
        $category->method('getId')->willReturn((string) $id);
        $category->method('getPath')->willReturn($path);

        return $category;
    }

    /**
     * @param array<int, int> $roots store ID => root category ID
     * @param array<int, bool> $active store ID => is_active
     * @return Bpf_Hreflang_Model_Resolver_Category&MockObject
     */
    private function resolver(?Mage_Catalog_Model_Category $category, array $roots = [1 => 2, 2 => 2, 3 => 2], array $active = [1 => true, 2 => true, 3 => true]): Bpf_Hreflang_Model_Resolver_Category
    {
        $urlModel = $this->createMock(Bpf_Hreflang_Model_Url::class);
        $urlModel->method('getCategoryUrls')->willReturnCallback(
            static fn (int $id, array $storeIds) => array_combine($storeIds, array_map(static fn ($s) => "https://example.com/{$s}/c{$id}.html", $storeIds)) ?: [],
        );

        $resolver = $this->getMockBuilder(Bpf_Hreflang_Model_Resolver_Category::class)
            ->onlyMethods(['_getCategory', '_isActiveInStore', '_getStoreRootCategoryId', '_getUrlModel'])
            ->getMock();
        $resolver->method('_getCategory')->willReturn($category);
        $resolver->method('_isActiveInStore')->willReturnCallback(static fn (int $id, int $storeId) => $active[$storeId] ?? false);
        $resolver->method('_getStoreRootCategoryId')->willReturnCallback(static fn (int $storeId) => $roots[$storeId]);
        $resolver->method('_getUrlModel')->willReturn($urlModel);

        return $resolver;
    }

    private function request(): Mage_Core_Controller_Request_Http
    {
        return $this->createMock(Mage_Core_Controller_Request_Http::class);
    }

    public function testResolvesOnlyWithACategory(): void
    {
        $this->assertTrue($this->resolver($this->category(10, '1/2/10'))->canResolve($this->request()));
        $this->assertFalse($this->resolver(null)->canResolve($this->request()));
        $this->assertSame([], $this->resolver(null)->resolve($this->request(), [1, 2]));
    }

    public function testReturnsUrlsOfStoresWhereCategoryIsActive(): void
    {
        $resolver = $this->resolver($this->category(10, '1/2/10'), active: [1 => true, 2 => false, 3 => true]);

        $this->assertSame([
            1 => 'https://example.com/1/c10.html',
            3 => 'https://example.com/3/c10.html',
        ], $resolver->resolve($this->request(), [1, 2, 3]));
    }

    public function testCategoryOutsideStoreRootTreeIsLeftOut(): void
    {
        // Stores 1–2 use root 2, store 3 uses root 5; category 10 sits under root 2 only.
        $resolver = $this->resolver($this->category(10, '1/2/7/10'), roots: [1 => 2, 2 => 2, 3 => 5]);

        $this->assertSame([1, 2], array_keys($resolver->resolve($this->request(), [1, 2, 3])));
    }

    public function testRootCategoryIdIsMatchedAsWholePathSegment(): void
    {
        // Root 2 must not match category 12's segment "12".
        $resolver = $this->resolver($this->category(20, '1/12/20'), roots: [1 => 2]);

        $this->assertSame([], $resolver->resolve($this->request(), [1]));
    }

    public function testRootCategoryItselfIsNotAPage(): void
    {
        $resolver = $this->resolver($this->category(2, '1/2'));

        $this->assertSame([], $resolver->resolve($this->request(), [1, 2, 3]));
    }

    public function testCacheKeyAndTagsIdentifyTheCategory(): void
    {
        $resolver = $this->resolver($this->category(10, '1/2/10'));

        $this->assertSame('category:10', $resolver->getCacheKey($this->request()));
        $this->assertSame(['catalog_category_10'], $resolver->getCacheTags($this->request()));
    }

    public function testCategoryComesFromRegistry(): void
    {
        $method = new ReflectionMethod(Bpf_Hreflang_Model_Resolver_Category::class, '_getCategory');
        $method->setAccessible(true);
        $resolver = new Bpf_Hreflang_Model_Resolver_Category();

        $this->assertNull($method->invoke($resolver));

        $category = $this->category(10, '1/2/10');
        Mage::register('current_category', $category);
        try {
            $this->assertSame($category, $method->invoke($resolver));
        } finally {
            Mage::unregister('current_category');
        }
    }

    public function testRegisteredForCategoryPage(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 4) . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $category = $config->global->bpf_hreflang->resolvers->category;

        $this->assertSame('bpf_hreflang/resolver_category', (string) $category->class);
        $this->assertSame(['catalog_category_view'], array_keys((array) $category->actions));
    }
}
