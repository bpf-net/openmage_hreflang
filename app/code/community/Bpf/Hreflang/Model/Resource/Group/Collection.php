<?php

class Bpf_Hreflang_Model_Resource_Group_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('bpf_hreflang/group');
    }

    public function addEntityTypeFilter(string $entityType): self
    {
        $this->addFieldToFilter('main_table.entity_type', $entityType);

        return $this;
    }

    /**
     * Adds "base_title" and "base_identifier" of the CMS page the group was created from.
     */
    public function joinBaseCmsPage(): self
    {
        $this->getSelect()->joinLeft(
            ['base_page' => $this->getTable('cms/page')],
            'base_page.page_id = main_table.base_entity_id',
            ['base_title' => 'base_page.title', 'base_identifier' => 'base_page.identifier'],
        );

        return $this;
    }

    /**
     * Adds "store_count": number of store views that have a version in the group.
     */
    public function addStoreCount(): self
    {
        $count = $this->getConnection()->select()
            ->from(['item' => $this->getTable('bpf_hreflang/group_item')], ['COUNT(*)'])
            ->where('item.group_id = main_table.group_id');
        $this->getSelect()->columns(['store_count' => new Zend_Db_Expr('(' . $count . ')')]);

        return $this;
    }
}
