<?php

/**
 * Normalizes the hreflang code entered for a store view and rejects invalid ones.
 */
class Bpf_Hreflang_Model_System_Config_Backend_LocaleCode extends Mage_Core_Model_Config_Data
{
    protected function _beforeSave()
    {
        $this->setValue($this->_prepareValue((string) $this->getValue()));

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

    protected function _getHelper(): Bpf_Hreflang_Helper_Data
    {
        return Mage::helper('bpf_hreflang');
    }
}
