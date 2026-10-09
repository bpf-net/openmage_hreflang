<?php

class Bpf_Hreflang_Model_System_Config_Source_GroupScope
{
    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('bpf_hreflang');

        return [
            ['value' => Bpf_Hreflang_Helper_Data::GROUP_SCOPE_WEBSITE, 'label' => $helper->__('Store views of the same website')],
            ['value' => Bpf_Hreflang_Helper_Data::GROUP_SCOPE_GLOBAL, 'label' => $helper->__('All store views')],
        ];
    }
}
