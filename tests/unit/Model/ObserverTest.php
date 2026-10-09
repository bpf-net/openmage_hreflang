<?php

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Bpf_Hreflang_Model_ObserverTest extends TestCase
{
    /**
     * @return Bpf_Hreflang_Model_Observer&MockObject
     */
    private function observer(Bpf_Hreflang_Model_Resource_Group $resource): Bpf_Hreflang_Model_Observer
    {
        $observer = $this->getMockBuilder(Bpf_Hreflang_Model_Observer::class)
            ->onlyMethods(['_getGroupResource', '_cleanCache'])
            ->getMock();
        $observer->method('_getGroupResource')->willReturn($resource);

        return $observer;
    }

    private function event(mixed $object): Varien_Event_Observer
    {
        return new Varien_Event_Observer(['event' => new Varien_Event(['object' => $object])]);
    }

    private function page(?int $id): Mage_Cms_Model_Page
    {
        $page = $this->createMock(Mage_Cms_Model_Page::class);
        $page->method('getId')->willReturn($id);

        return $page;
    }

    public function testDeletedPageIsRemovedFromGroupsAndCacheCleaned(): void
    {
        $resource = $this->createMock(Bpf_Hreflang_Model_Resource_Group::class);
        $resource->expects($this->once())->method('deleteEntity')->with('cms_page', 12)->willReturn(2);
        $observer = $this->observer($resource);
        $observer->expects($this->once())->method('_cleanCache');

        $observer->removeDeletedCmsPageFromGroups($this->event($this->page(12)));
    }

    public function testCacheIsKeptWhenPageWasInNoGroup(): void
    {
        $resource = $this->createMock(Bpf_Hreflang_Model_Resource_Group::class);
        $resource->method('deleteEntity')->willReturn(0);
        $observer = $this->observer($resource);
        $observer->expects($this->never())->method('_cleanCache');

        $observer->removeDeletedCmsPageFromGroups($this->event($this->page(12)));
    }

    public function testIgnoresOtherObjects(): void
    {
        $resource = $this->createMock(Bpf_Hreflang_Model_Resource_Group::class);
        $resource->expects($this->never())->method('deleteEntity');
        $observer = $this->observer($resource);

        $observer->removeDeletedCmsPageFromGroups($this->event(new Varien_Object()));
        $observer->removeDeletedCmsPageFromGroups($this->event($this->page(null)));
    }

    public function testRegisteredForCmsPageDelete(): void
    {
        $config = simplexml_load_file(dirname(__DIR__, 3) . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $registered = $config->xpath('global/events/cms_page_delete_after/observers/*');

        $this->assertCount(1, $registered);
        $this->assertSame('bpf_hreflang/observer', (string) $registered[0]->class);
        $this->assertSame('removeDeletedCmsPageFromGroups', (string) $registered[0]->method);
    }
}
