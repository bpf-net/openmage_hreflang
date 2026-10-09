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
     * @param list<string> $extraMethods further protected methods to stub
     * @return Bpf_Hreflang_Model_Url&MockObject
     */
    private function urlModel(array $stores, array $extraMethods = []): Bpf_Hreflang_Model_Url
    {
        $byId = [];
        foreach ($stores as $store) {
            $byId[$store->getId()] = $store;
        }

        $model = $this->getMockBuilder(Bpf_Hreflang_Model_Url::class)
            ->onlyMethods(array_merge(['_getStore'], $extraMethods))
            ->getMock();
        $model->method('_getStore')->willReturnCallback(static fn (int $id) => $byId[$id]);

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
}
