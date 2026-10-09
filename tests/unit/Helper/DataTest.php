<?php

use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Helper_DataTest extends TestCase
{
    /**
     * @param array<string, mixed> $config path => raw config value
     */
    private function helper(array $config): Bpf_Hreflang_Helper_Data
    {
        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)
            ->onlyMethods(['_getConfig'])
            ->getMock();
        $helper->method('_getConfig')
            ->willReturnCallback(static fn (string $path) => $config[$path] ?? null);

        return $helper;
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_ENABLED => '1'])->isEnabled());
        $this->assertFalse($this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_ENABLED => '0'])->isEnabled());
        $this->assertFalse($this->helper([])->isEnabled());
    }

    public function testGetLocaleCodeTrimsValue(): void
    {
        $helper = $this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_LOCALE_CODE => " de-AT \n"]);

        $this->assertSame('de-AT', $helper->getLocaleCode());
        $this->assertSame('', $this->helper([])->getLocaleCode());
    }

    /**
     * @dataProvider groupScopeProvider
     */
    public function testGetGroupScope(?string $value, string $expected): void
    {
        $helper = $this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_GROUP_SCOPE => $value]);

        $this->assertSame($expected, $helper->getGroupScope());
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public function groupScopeProvider(): array
    {
        return [
            'website' => ['website', Bpf_Hreflang_Helper_Data::GROUP_SCOPE_WEBSITE],
            'global' => ['global', Bpf_Hreflang_Helper_Data::GROUP_SCOPE_GLOBAL],
            'empty falls back to website' => [null, Bpf_Hreflang_Helper_Data::GROUP_SCOPE_WEBSITE],
            'unknown falls back to website' => ['all', Bpf_Hreflang_Helper_Data::GROUP_SCOPE_WEBSITE],
        ];
    }

    public function testGetXDefaultStoreId(): void
    {
        $this->assertSame(3, $this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_X_DEFAULT_STORE => '3'])->getXDefaultStoreId());
        $this->assertNull($this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_X_DEFAULT_STORE => ''])->getXDefaultStoreId());
        $this->assertNull($this->helper([])->getXDefaultStoreId());
    }

    public function testGetExcludedActionsSplitsLinesAndDropsBlanks(): void
    {
        $helper = $this->helper([
            Bpf_Hreflang_Helper_Data::XML_PATH_EXCLUDED_ACTIONS => " cms_index_noRoute\r\n\ncheckout_*\n  \ncheckout_*\n",
        ]);

        $this->assertSame(['cms_index_noRoute', 'checkout_*'], $helper->getExcludedActions());
        $this->assertSame([], $this->helper([])->getExcludedActions());
    }

    public function testGetRootStoreIdsParsesMultiselect(): void
    {
        $helper = $this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_ROOT_STORES => '1,3,,0,3']);

        $this->assertSame([1, 3], $helper->getRootStoreIds());
        $this->assertSame([], $this->helper([])->getRootStoreIds());
    }

    public function testDefaultsAreDeclaredInConfigXml(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 3) . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $general = $config->default->bpf_hreflang->general;

        $this->assertSame('0', (string) $general->enabled);
        $this->assertSame('website', (string) $general->group_scope);

        $helper = $this->helper([
            Bpf_Hreflang_Helper_Data::XML_PATH_EXCLUDED_ACTIONS => (string) $general->excluded_actions,
        ]);
        $this->assertSame([
            'cms_index_noRoute',
            'cms_index_defaultNoRoute',
            'catalogsearch_*',
            'checkout_*',
            'customer_*',
            'wishlist_*',
            'sales_*',
            'review_*',
            'contacts_*',
        ], $helper->getExcludedActions());
    }
}
