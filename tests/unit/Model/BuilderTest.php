<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_BuilderTest extends TestCase
{
    /**
     * Store view settings used by the fake helper, keyed by store ID.
     *
     * @var array<int, array{code: string, enabled: bool, noindex: bool}>
     */
    private array $settings = [];

    /** @var array<int, Mage_Core_Model_Store> */
    private array $groupStores = [];

    private function addStore(int $id, string $code, bool $active = true, bool $enabled = true, bool $noindex = false): Mage_Core_Model_Store
    {
        $store = new Mage_Core_Model_Store(['store_id' => $id, 'is_active' => $active ? 1 : 0, 'name' => "Store {$id}"]);
        $this->groupStores[$id] = $store;
        $this->settings[$id] = ['code' => $code, 'enabled' => $enabled, 'noindex' => $noindex];

        return $store;
    }

    /**
     * @return Bpf_Hreflang_Helper_Data&MockObject
     */
    private function helper(): Bpf_Hreflang_Helper_Data
    {
        $settings = &$this->settings;
        $helper = $this->getMockBuilder(Bpf_Hreflang_Helper_Data::class)
            ->onlyMethods(['getGroupStores', 'isEnabled', 'getLocaleCode', 'isStoreNoindex'])
            ->getMock();
        $helper->method('getGroupStores')->willReturnCallback(fn () => $this->groupStores);
        $helper->method('isEnabled')->willReturnCallback(static fn ($store) => $settings[$store->getId()]['enabled']);
        $helper->method('getLocaleCode')->willReturnCallback(static fn ($store) => $settings[$store->getId()]['code']);
        $helper->method('isStoreNoindex')->willReturnCallback(static fn ($store) => $settings[$store->getId()]['noindex']);

        return $helper;
    }

    /**
     * @param list<string> $extraMethods further protected methods to stub
     * @return Bpf_Hreflang_Model_Builder&MockObject
     */
    private function builder(array $extraMethods = []): Bpf_Hreflang_Model_Builder
    {
        $builder = $this->getMockBuilder(Bpf_Hreflang_Model_Builder::class)
            ->onlyMethods(array_merge(['_getHelper'], $extraMethods))
            ->getMock();
        $builder->method('_getHelper')->willReturn($this->helper());

        return $builder;
    }

    public function testCandidatesAreGroupStoresThatTakePart(): void
    {
        $pl = $this->addStore(1, 'pl');
        $en = $this->addStore(2, 'en');
        $this->addStore(3, 'de', active: false);
        $this->addStore(4, 'fr', enabled: false);
        $this->addStore(5, '');
        $this->addStore(6, 'cs', noindex: true);

        $this->assertSame([1 => $pl, 2 => $en], $this->builder()->getCandidateStores($pl));
    }

    public function testCandidatesDoNotDependOnCurrentStore(): void
    {
        $pl = $this->addStore(1, 'pl');
        $en = $this->addStore(2, 'en');
        $de = $this->addStore(3, '');

        $builder = $this->builder();
        $this->assertSame(
            array_keys($builder->getCandidateStores($pl)),
            array_keys($builder->getCandidateStores($en)),
        );
        $this->assertSame([1, 2], array_keys($builder->getCandidateStores($de)));
    }
}
