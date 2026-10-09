<?php

use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_Resolver_HomeTest extends TestCase
{
    public function testResolvesHomeUrlOfEveryStore(): void
    {
        $urlModel = $this->createMock(Bpf_Hreflang_Model_Url::class);
        $urlModel->method('getHomeUrl')->willReturnMap([
            [1, 'https://example.com/'],
            [2, 'https://example.com/en/'],
        ]);
        $resolver = $this->getMockBuilder(Bpf_Hreflang_Model_Resolver_Home::class)
            ->onlyMethods(['_getUrlModel'])
            ->getMock();
        $resolver->method('_getUrlModel')->willReturn($urlModel);
        $request = $this->createMock(Mage_Core_Controller_Request_Http::class);

        $this->assertTrue($resolver->canResolve($request));
        $this->assertSame(
            [1 => 'https://example.com/', 2 => 'https://example.com/en/'],
            $resolver->resolve($request, [1, 2]),
        );
        $this->assertSame('home', $resolver->getCacheKey($request));
        $this->assertSame([], $resolver->getCacheTags($request));
    }

    public function testRegisteredForHomePageAction(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 4) . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $home = $config->global->bpf_hreflang->resolvers->home;

        $this->assertSame('bpf_hreflang/resolver_home', (string) $home->class);
        $this->assertSame(['cms_index_index'], array_keys((array) $home->actions));
        $this->assertTrue(is_subclass_of(Bpf_Hreflang_Model_Resolver_Home::class, Bpf_Hreflang_Model_Resolver_Interface::class));
    }
}
