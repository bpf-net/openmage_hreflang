<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_System_Config_Backend_GroupScopeTest extends TestCase
{
    /**
     * @param array<string, string> $codes store name => hreflang code
     * @return Bpf_Hreflang_Model_System_Config_Backend_GroupScope&MockObject
     */
    private function backend(array $codes): Bpf_Hreflang_Model_System_Config_Backend_GroupScope
    {
        $stores = [];
        $byId = [];
        $id = 1;
        foreach ($codes as $name => $code) {
            $stores[$id] = new Mage_Core_Model_Store(['store_id' => $id, 'name' => $name]);
            $byId[$id] = $code;
            $id++;
        }

        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)->onlyMethods(['__', 'getLocaleCode'])->getMock();
        $helper->method('__')->willReturnCallback(static fn (string $text, ...$args): string => vsprintf($text, $args));
        $helper->method('getLocaleCode')->willReturnCallback(static fn ($store) => $byId[$store->getId()]);

        $backend = $this->getMockBuilder(Bpf_Hreflang_Model_System_Config_Backend_GroupScope::class)
            ->onlyMethods(['_getStores', '_getHelper'])
            ->getMock();
        $backend->method('_getStores')->willReturn($stores);
        $backend->method('_getHelper')->willReturn($helper);

        return $backend;
    }

    private function assertUnique(Bpf_Hreflang_Model_System_Config_Backend_GroupScope $backend): void
    {
        $method = new ReflectionMethod($backend, '_assertUniqueCodesAcrossStores');
        $method->setAccessible(true);
        $method->invoke($backend);
    }

    public function testUniqueCodesAcrossWebsitesAreAccepted(): void
    {
        $this->assertUnique($this->backend(['English' => 'en', 'UK' => 'en-GB', 'Polski' => 'pl', 'Hidden' => '']));
        $this->addToAssertionCount(1);
    }

    public function testCodesSharedAcrossWebsitesAreRejected(): void
    {
        $this->expectException(Mage_Core_Exception::class);
        $this->expectExceptionMessage('"en" is used by store views "English EU", "English US"; "de" is used by store views "Deutsch", "Deutsch CH"');

        $this->assertUnique($this->backend([
            'English EU' => 'en',
            'Deutsch' => 'de',
            'English US' => 'en',
            'Deutsch CH' => 'de',
            'Polski' => 'pl',
        ]));
    }

    public function testStoresWithoutCodeDoNotCollide(): void
    {
        $this->assertUnique($this->backend(['A' => '', 'B' => '', 'C' => 'en']));
        $this->addToAssertionCount(1);
    }

    public function testWiredToGroupScopeField(): void
    {
        $system = simplexml_load_file(dirname(__DIR__, 6) . '/app/code/community/Bpf/Hreflang/etc/system.xml');
        $field = $system->sections->bpf_hreflang->groups->general->fields->group_scope;

        $this->assertSame('bpf_hreflang/system_config_backend_groupScope', (string) $field->backend_model);
    }
}
