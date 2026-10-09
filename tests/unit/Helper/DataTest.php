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

    /**
     * @dataProvider configuredLocaleCodeProvider
     */
    public function testGetLocaleCodeReturnsNormalizedValidCodeOnly(?string $value, string $expected): void
    {
        $helper = $this->helper([Bpf_Hreflang_Helper_Data::XML_PATH_LOCALE_CODE => $value]);

        $this->assertSame($expected, $helper->getLocaleCode());
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public function configuredLocaleCodeProvider(): array
    {
        return [
            'valid' => ['de-AT', 'de-AT'],
            'needs normalization' => [" en_gb \n", 'en-GB'],
            'invalid is ignored' => ['english', ''],
            'empty' => ['', ''],
            'not set' => [null, ''],
        ];
    }

    /**
     * @dataProvider normalizeLocaleCodeProvider
     */
    public function testNormalizeLocaleCode(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->helper([])->normalizeLocaleCode($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function normalizeLocaleCodeProvider(): array
    {
        return [
            'already normalized' => ['en-GB', 'en-GB'],
            'language only' => ['PL', 'pl'],
            'underscore' => ['de_at', 'de-AT'],
            'mixed case' => ['En-gB', 'en-GB'],
            'whitespace' => ["  fr-ca\t", 'fr-CA'],
            'empty' => ['', ''],
            'invalid stays invalid' => ['eng_usa', 'eng-USA'],
        ];
    }

    /**
     * @dataProvider localeCodeValidityProvider
     */
    public function testIsValidLocaleCode(string $code, bool $expected): void
    {
        $this->assertSame($expected, $this->helper([])->isValidLocaleCode($code));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public function localeCodeValidityProvider(): array
    {
        return [
            'language' => ['en', true],
            'language and region' => ['en-GB', true],
            'other region' => ['de-AT', true],
            'empty' => ['', false],
            'three-letter language' => ['eng', false],
            'three-letter region' => ['en-GBR', false],
            'not normalized case' => ['en-gb', false],
            'underscore' => ['en_GB', false],
            'x-default is not a store code' => ['x-default', false],
            'script subtag' => ['zh-Hant', false],
            'digits' => ['e1', false],
            'trailing newline' => ["en\n", false],
        ];
    }

    public function testIsoListsMatchStandardSizes(): void
    {
        $helper = $this->helper([]);
        $letters = range('a', 'z');
        $languages = $regions = 0;
        foreach ($letters as $first) {
            foreach ($letters as $second) {
                $languages += (int) $helper->isKnownLanguage($first . $second);
                $regions += (int) $helper->isKnownRegion(strtoupper($first . $second));
            }
        }

        // ISO 639-1 defines 184 language codes; ISO 3166-1 assigns 249 alpha-2 codes.
        $this->assertSame(184, $languages);
        $this->assertSame(249, $regions);
    }

    public function testIsoListsAreCaseSensitive(): void
    {
        $helper = $this->helper([]);

        $this->assertTrue($helper->isKnownLanguage('en'));
        $this->assertFalse($helper->isKnownLanguage('EN'));
        $this->assertTrue($helper->isKnownRegion('GB'));
        $this->assertFalse($helper->isKnownRegion('gb'));
    }

    public function testNormalizedCodesFromSpecAreValid(): void
    {
        $helper = $this->helper([]);

        foreach (['pl', 'en', 'de-AT', 'EN_gb', 'en-gb'] as $input) {
            $this->assertTrue($helper->isValidLocaleCode($helper->normalizeLocaleCode($input)), $input);
        }
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

    /**
     * @param array<int, Mage_Core_Model_Store> $allStores
     * @param array<int, Mage_Core_Model_Store> $websiteStores stores of the current store's website
     */
    private function helperWithStores(string $groupScope, array $allStores, array $websiteStores): Bpf_Hreflang_Helper_Data
    {
        $website = $this->createMock(Mage_Core_Model_Website::class);
        $website->method('getStores')->willReturn($websiteStores);
        $currentStore = $this->createMock(Mage_Core_Model_Store::class);
        $currentStore->method('getWebsite')->willReturn($website);

        $app = $this->createMock(Mage_Core_Model_App::class);
        $app->method('getStores')->willReturn($allStores);
        $app->method('getStore')->willReturn($currentStore);

        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)
            ->onlyMethods(['_getConfig', '_getApp'])
            ->getMock();
        $helper->method('_getConfig')->willReturnCallback(
            static fn (string $path) => $path === Bpf_Hreflang_Helper_Data::XML_PATH_GROUP_SCOPE ? $groupScope : null,
        );
        $helper->method('_getApp')->willReturn($app);

        return $helper;
    }

    public function testGetGroupStoresReturnsWebsiteStoresForWebsiteScope(): void
    {
        $pl = new Mage_Core_Model_Store(['store_id' => 1]);
        $en = new Mage_Core_Model_Store(['store_id' => 2]);
        $uk = new Mage_Core_Model_Store(['store_id' => 3]);
        $helper = $this->helperWithStores('website', [1 => $pl, 2 => $en, 3 => $uk], [1 => $pl, 2 => $en]);

        $this->assertSame([1 => $pl, 2 => $en], $helper->getGroupStores(1));
    }

    public function testGetGroupStoresReturnsAllStoresForGlobalScope(): void
    {
        $pl = new Mage_Core_Model_Store(['store_id' => 1]);
        $en = new Mage_Core_Model_Store(['store_id' => 2]);
        $uk = new Mage_Core_Model_Store(['store_id' => 3]);
        $helper = $this->helperWithStores('global', [1 => $pl, 2 => $en, 3 => $uk], [1 => $pl, 2 => $en]);

        $this->assertSame([1 => $pl, 2 => $en, 3 => $uk], $helper->getGroupStores(1));
    }

    /**
     * @dataProvider robotsProvider
     */
    public function testIsStoreNoindex(?string $robots, bool $expected): void
    {
        $helper = $this->helper([Bpf_Hreflang_Helper_Data::CONFIG_PATH_DEFAULT_ROBOTS => $robots]);

        $this->assertSame($expected, $helper->isStoreNoindex());
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public function robotsProvider(): array
    {
        return [
            'index' => ['INDEX,FOLLOW', false],
            'noindex' => ['NOINDEX,NOFOLLOW', true],
            'noindex follow' => ['NOINDEX,FOLLOW', true],
            'lowercase' => ['noindex,follow', true],
            'not set' => [null, false],
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
