<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_System_Config_Backend_LocaleCodeTest extends TestCase
{
    /**
     * @param array<int, Mage_Core_Model_Store> $groupStores stores returned for the group
     * @param array<int, string> $codes configured hreflang code per store ID
     * @return Bpf_Hreflang_Helper_Data&MockObject
     */
    private function helper(array $groupStores = [], array $codes = []): Bpf_Hreflang_Helper_Data
    {
        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)
            ->onlyMethods(['__', 'getGroupStores', 'getLocaleCode'])
            ->getMock();
        $helper->method('__')->willReturnCallback(static fn (string $text, ...$args): string => vsprintf($text, $args));
        $helper->method('getGroupStores')->willReturn($groupStores);
        $helper->method('getLocaleCode')->willReturnCallback(
            static fn (Mage_Core_Model_Store $store): string => $codes[$store->getId()] ?? '',
        );

        return $helper;
    }

    /**
     * Calls a protected method of the backend model wired to the given helper.
     *
     * @param mixed ...$args
     * @return mixed
     */
    private function invoke(Bpf_Hreflang_Helper_Data $helper, string $method, ...$args)
    {
        $backend = $this->getMockBuilder(Bpf_Hreflang_Model_System_Config_Backend_LocaleCode::class)
            ->onlyMethods(['_getHelper'])
            ->getMock();
        $backend->method('_getHelper')->willReturn($helper);

        $reflection = new ReflectionMethod($backend, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($backend, ...$args);
    }

    private function store(int $id, string $name): Mage_Core_Model_Store
    {
        return new Mage_Core_Model_Store(['store_id' => $id, 'name' => $name]);
    }

    /**
     * @dataProvider acceptedValueProvider
     */
    public function testValidValueIsNormalized(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->invoke($this->helper(), '_prepareValue', $input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function acceptedValueProvider(): array
    {
        return [
            'normalized' => ['en-GB', 'en-GB'],
            'underscore and case' => [' DE_at ', 'de-AT'],
            'empty disables the store' => ['', ''],
            'whitespace only disables the store' => ['  ', ''],
        ];
    }

    /**
     * @dataProvider rejectedValueProvider
     */
    public function testInvalidValueIsRejected(string $input): void
    {
        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage(sprintf('Invalid hreflang code "%s"', $input));

        $this->invoke($this->helper(), '_prepareValue', $input);
    }

    /**
     * @return array<string, array{string}>
     */
    public function rejectedValueProvider(): array
    {
        return [
            'three-letter language' => ['eng'],
            'three-letter region' => ['en-GBR'],
            'x-default' => ['x-default'],
            'script subtag' => ['zh-Hant'],
        ];
    }

    /**
     * @dataProvider unknownIsoCodeProvider
     */
    public function testCodeOutsideIsoListsIsRejected(string $input, string $expectedMessage): void
    {
        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->invoke($this->helper(), '_prepareValue', $input);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function unknownIsoCodeProvider(): array
    {
        return [
            'unknown language' => ['zz', '"zz" is not an ISO 639-1 language code'],
            'unknown language with valid region' => ['qq-GB', '"qq" is not an ISO 639-1 language code'],
            'unknown region' => ['en-XX', '"XX" is not an ISO 3166-1 region code'],
            'unknown region code ZZ' => ['en-ZZ', '"ZZ" is not an ISO 3166-1 region code'],
            'user-assigned region XK' => ['sq-XK', '"XK" is not an ISO 3166-1 region code'],
            'withdrawn region AN' => ['nl-AN', '"AN" is not an ISO 3166-1 region code'],
            'european union is not a country' => ['en-EU', '"EU" is not an ISO 3166-1 region code'],
            'common mistake UK for GB' => ['en-UK', '"UK" is not an ISO 3166-1 region code'],
        ];
    }

    /**
     * @dataProvider knownIsoCodeProvider
     */
    public function testRealIsoCodesAreAccepted(string $input): void
    {
        $this->assertSame($input, $this->invoke($this->helper(), '_prepareValue', $input));
    }

    /**
     * @return array<string, array{string}>
     */
    public function knownIsoCodeProvider(): array
    {
        return [
            'nepali, an anagram of en' => ['ne'],
            'brazilian portuguese' => ['pt-BR'],
            'south sudan' => ['en-SS'],
            'curacao' => ['nl-CW'],
        ];
    }

    public function testCodeUsedByAnotherStoreOfTheGroupIsRejected(): void
    {
        $helper = $this->helper(
            [1 => $this->store(1, 'Polski'), 2 => $this->store(2, 'English')],
            [1 => 'pl', 2 => 'en'],
        );

        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('Hreflang code "pl" is already used by store view "Polski"');

        $this->invoke($helper, '_assertUniqueInGroup', 'pl', 2);
    }

    public function testStoreMayKeepItsOwnCode(): void
    {
        $helper = $this->helper(
            [1 => $this->store(1, 'Polski'), 2 => $this->store(2, 'English')],
            [1 => 'pl', 2 => 'en'],
        );

        $this->invoke($helper, '_assertUniqueInGroup', 'en', 2);
        $this->addToAssertionCount(1);
    }

    public function testSameLanguageWithDifferentRegionIsAllowed(): void
    {
        $helper = $this->helper(
            [1 => $this->store(1, 'English'), 2 => $this->store(2, 'UK')],
            [1 => 'en', 2 => ''],
        );

        $this->invoke($helper, '_assertUniqueInGroup', 'en-GB', 2);
        $this->addToAssertionCount(1);
    }

    public function testStoresOutsideTheGroupAreNotChecked(): void
    {
        // The group (e.g. website scope) holds only store 2; store 3 with the same code belongs elsewhere.
        $helper = $this->helper([2 => $this->store(2, 'English')], [3 => 'en']);

        $this->invoke($helper, '_assertUniqueInGroup', 'en', 2);
        $this->addToAssertionCount(1);
    }
}
