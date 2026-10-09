<?php

/**
 * Builds the hreflang => URL map for the current page.
 */
class Bpf_Hreflang_Model_Builder
{
    /**
     * Store views that can appear as alternates of the current one: those of its group
     * (website or all, per group_scope) that are active, have the module enabled, have
     * a hreflang code and are not NOINDEX by default.
     *
     * The selection depends only on the group, never on which member is current, so every
     * version of a page works with the same candidates.
     *
     * @return array<int, Mage_Core_Model_Store> keyed by store ID
     */
    public function getCandidateStores(Mage_Core_Model_Store $currentStore): array
    {
        $helper = $this->_getHelper();
        $candidates = [];

        foreach ($helper->getGroupStores($currentStore) as $store) {
            if (!$store->getIsActive()
                || !$helper->isEnabled($store)
                || $helper->getLocaleCode($store) === ''
                || $helper->isStoreNoindex($store)
            ) {
                continue;
            }

            $candidates[(int) $store->getId()] = $store;
        }

        return $candidates;
    }

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
