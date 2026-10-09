<?php

/**
 * Base page plus one CMS page per store view that has a hreflang code.
 */
class Bpf_Hreflang_Block_Adminhtml_Group_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $helper = Mage::helper('bpf_hreflang');
        /** @var Bpf_Hreflang_Model_Group $group */
        $group = Mage::registry(Bpf_Hreflang_Model_Group::REGISTRY_KEY);

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $group->getId()]),
            'method' => 'post',
        ]);
        $form->setUseContainer(true);

        $base = $form->addFieldset('base_fieldset', ['legend' => $helper->__('Group')]);
        $base->addField('base_page_id', 'select', [
            'name' => 'base_page_id',
            'label' => $helper->__('Base Page'),
            'note' => $helper->__('The page the group is about; shown in the groups grid.'),
            'values' => $this->_getPageOptions(null),
        ]);

        $versions = $form->addFieldset('versions_fieldset', [
            'legend' => $helper->__('Versions'),
            'comment' => $helper->__('Choose the page that is the translation of the base page in each store view. Store views without a hreflang code are not listed.'),
        ]);

        $stores = $this->_getStoresWithLocaleCode();
        if ($stores === []) {
            $versions->addField('no_stores', 'note', [
                'text' => $helper->__('No store view has a hreflang code yet. Set one in System → Configuration → Bpf → Hreflang.'),
            ]);
        }
        foreach ($stores as $storeId => $label) {
            $versions->addField('items_' . $storeId, 'select', [
                'name' => "items[{$storeId}]",
                'label' => $label,
                'values' => $this->_getPageOptions($storeId),
            ]);
        }

        $values = ['base_page_id' => $group->getBaseEntityId()];
        foreach ($group->getItems() as $storeId => $pageId) {
            $values['items_' . $storeId] = $pageId;
        }
        $form->setValues($values);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * @return array<int, string> store ID => "Website / Store View (code)"
     */
    protected function _getStoresWithLocaleCode(): array
    {
        $helper = Mage::helper('bpf_hreflang');
        $stores = [];
        foreach (Mage::app()->getStores() as $store) {
            $code = $helper->getLocaleCode($store);
            if ($code !== '') {
                $stores[(int) $store->getId()] = sprintf('%s / %s (%s)', $store->getWebsite()->getName(), $store->getName(), $code);
            }
        }

        return $stores;
    }

    /**
     * CMS pages available in the store view (assigned to it or to all store views), or all pages.
     *
     * @return list<array{value: int|string, label: string}>
     */
    protected function _getPageOptions(?int $storeId): array
    {
        /** @var Mage_Cms_Model_Resource_Page_Collection $pages */
        $pages = Mage::getResourceModel('cms/page_collection')->setOrder('title', Varien_Data_Collection::SORT_ORDER_ASC);
        if ($storeId !== null) {
            $pages->addStoreFilter($storeId);
        }

        $options = [['value' => '', 'label' => Mage::helper('bpf_hreflang')->__('-- None --')]];
        foreach ($pages as $page) {
            $options[] = [
                'value' => (int) $page->getId(),
                'label' => sprintf('%s (%s)%s', $page->getTitle(), $page->getIdentifier(), $page->getIsActive() ? '' : ' [disabled]'),
            ];
        }

        return $options;
    }
}
