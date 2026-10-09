<?php

use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_System_Config_Backend_LocaleCodeTest extends TestCase
{
    private function prepareValue(string $value): string
    {
        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)
            ->onlyMethods(['__'])
            ->getMock();
        $helper->method('__')->willReturnCallback(static fn (string $text, ...$args): string => vsprintf($text, $args));

        $backend = $this->getMockBuilder(Bpf_Hreflang_Model_System_Config_Backend_LocaleCode::class)
            ->onlyMethods(['_getHelper'])
            ->getMock();
        $backend->method('_getHelper')->willReturn($helper);

        $method = new ReflectionMethod($backend, '_prepareValue');
        $method->setAccessible(true);

        return $method->invoke($backend, $value);
    }

    /**
     * @dataProvider acceptedValueProvider
     */
    public function testValidValueIsNormalized(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->prepareValue($input));
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

        $this->prepareValue($input);
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
}
