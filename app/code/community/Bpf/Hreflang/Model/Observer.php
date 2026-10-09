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

    protected function _cleanCache(): void
    {
        Mage::app()->cleanCache([Bpf_Hreflang_Block_Head::CACHE_TAG]);
    }
}
