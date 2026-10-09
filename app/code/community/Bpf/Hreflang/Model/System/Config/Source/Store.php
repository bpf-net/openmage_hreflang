<?php

/**
 * Store views grouped by website and store; a select also gets an empty "None" option.
 */
class Bpf_Hreflang_Model_System_Config_Source_Store
{
    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    public function toOptionArray(bool $isMultiselect = false): array
    {
        $options = Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm();

        if (!$isMultiselect) {
            array_unshift($options, ['value' => '', 'label' => Mage::helper('bpf_hreflang')->__('-- None --')]);
        }

        return $options;
    }
}
