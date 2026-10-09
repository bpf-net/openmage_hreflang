<?php

/**
 * Switching alternates to all store views requires their hreflang codes to be unique across
 * websites; codes were only checked within each website before.
 */
class Bpf_Hreflang_Model_System_Config_Backend_GroupScope extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        if ((string) $this->getValue() === Bpf_Hreflang_Helper_Data::GROUP_SCOPE_GLOBAL) {
            $this->_assertUniqueCodesAcrossStores();
        }

        return parent::_beforeSave();
    }

    /**
     * @throws Mage_Core_Exception listing every code used by more than one store view
     */
    protected function _assertUniqueCodesAcrossStores(): void
    {
        $helper = $this->_getHelper();
        $storesByCode = [];
        foreach ($this->_getStores() as $store) {
            $code = $helper->getLocaleCode($store);
            if ($code !== '') {
                $storesByCode[$code][] = (string) $store->getName();
            }
        }

        $collisions = [];
        foreach ($storesByCode as $code => $names) {
            if (count($names) > 1) {
                $collisions[] = $helper->__('"%s" is used by store views "%s"', $code, implode('", "', $names));
            }
        }

        if ($collisions !== []) {
            Mage::throwException($helper->__(
                'All store views cannot be alternates of each other while they share hreflang codes: %s. Give each store view a different code first.',
                implode('; ', $collisions),
            ));
        }
    }

    /**
     * @return array<int, Mage_Core_Model_Store>
     */
    protected function _getStores(): array
    {
        return Mage::app()->getStores();
    }

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
