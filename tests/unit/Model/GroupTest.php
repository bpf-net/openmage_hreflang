<?php

use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_GroupTest extends TestCase
{
    public function testSetItemsNormalizesAndSortsByStore(): void
    {
        $group = new Bpf_Hreflang_Model_Group();
        $group->setItems(['3' => '12', 1 => 10, 2 => '', 0 => 99, 4 => null, 5 => 0]);

        $this->assertSame([1 => 10, 3 => 12], $group->getItems());
    }

    public function testItemsOfNewGroupAreEmpty(): void
    {
        $this->assertSame([], (new Bpf_Hreflang_Model_Group())->getItems());
    }

    public function testItemsOfSavedGroupAreLoadedOnce(): void
    {
        $resource = $this->createMock(Bpf_Hreflang_Model_Resource_Group::class);
        $resource->expects($this->once())->method('getItems')->with(7)->willReturn([1 => 10, 2 => 11]);

        $group = $this->getMockBuilder(Bpf_Hreflang_Model_Group::class)
            ->onlyMethods(['_getResource'])
            ->getMock();
        $group->method('_getResource')->willReturn($resource);
        $group->setData('group_id', 7);

        $this->assertSame([1 => 10, 2 => 11], $group->getItems());
        $this->assertSame([1 => 10, 2 => 11], $group->getItems());
    }

    public function testSetItemsReplacesLoadedItems(): void
    {
        $group = new Bpf_Hreflang_Model_Group();
        $group->setItems([1 => 10]);
        $group->setItems([2 => 20]);

        $this->assertSame([2 => 20], $group->getItems());
    }
}
