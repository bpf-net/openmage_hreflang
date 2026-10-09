<?php

/**
 * Normalizes the hreflang code entered for a store view, rejects invalid ones
 * and codes already used by another store view of the same alternates group.
 */
class Bpf_Hreflang_Model_System_Config_Backend_LocaleCode extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        $code = $this->_prepareValue((string) $this->getValue());

        if ($code !== '' && $this->getScope() === 'stores') {
            $this->_assertUniqueInGroup($code, (int) $this->getScopeId());
        }

        $this->setValue($code);

        return parent::_beforeSave();
    }

    /**
     * @throws Mage_Core_Exception when the code is not a valid hreflang code
     */
    protected function _prepareValue(string $value): string
    {
        $helper = $this->_getHelper();
        $code = $helper->normalizeLocaleCode($value);

        if ($code !== '' && !$helper->isValidLocaleCode($code)) {
            Mage::throwException($helper->__(
                'Invalid hreflang code "%s". Use a two-letter language code, optionally with a two-letter region, e.g. "en" or "en-GB".',
                $value,
            ));
        }

        return $code;
    }

    /**
     * @throws Mage_Core_Exception when another store view of the group already uses the code
     */
    protected function _assertUniqueInGroup(string $code, int $storeId): void
    {
        $helper = $this->_getHelper();

        foreach ($helper->getGroupStores($storeId) as $store) {
            if ((int) $store->getId() === $storeId) {
                continue;
            }

            if ($helper->getLocaleCode($store) === $code) {
                Mage::throwException($helper->__(
                    'Hreflang code "%s" is already used by store view "%s". Store views listed as alternates of each other need different codes.',
                    $code,
                    $store->getName(),
                ));
            }
        }
    }

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
