<?php

class Bpf_Hreflang_Model_Observer
{
    /**
     * Any saved configuration section may affect hreflang URLs (module settings, base URLs,
     * "Add Store Code to Urls", default robots, CMS home page), so cached tags are dropped.
     *
     * Event: admin_system_config_section_save_after
     */
    public function cleanCacheOnConfigSave(Varien_Event_Observer $observer): void
    {
        $this->_cleanCache();
    }

    /**
     * A deleted CMS page leaves every translation group it was in.
     *
     * Event: cms_page_delete_after (the page is passed as "object")
     */
    public function removeDeletedCmsPageFromGroups(Varien_Event_Observer $observer): void
    {
        $page = $observer->getEvent()->getData('object');
        if (!$page instanceof Mage_Cms_Model_Page || !$page->getId()) {
            return;
        }

        $removed = $this->_getGroupResource()->deleteEntity(Bpf_Hreflang_Model_Group::ENTITY_TYPE_CMS_PAGE, (int) $page->getId());
        if ($removed > 0) {
            $this->_cleanCache();
        }
    }

    protected function _getGroupResource(): Bpf_Hreflang_Model_Resource_Group
    {
        return Mage::getResourceSingleton('bpf_hreflang/group');
    }

    protected function _cleanCache(): void
    {
        Mage::app()->cleanCache([Bpf_Hreflang_Block_Head::CACHE_TAG]);
    }
}
