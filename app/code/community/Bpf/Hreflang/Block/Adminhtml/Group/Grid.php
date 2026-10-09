<?php

class Bpf_Hreflang_Block_Adminhtml_Group_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('bpfHreflangGroupGrid');
        $this->setDefaultSort('group_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        /** @var Bpf_Hreflang_Model_Resource_Group_Collection $collection */
        $collection = Mage::getResourceModel('bpf_hreflang/group_collection');
        $collection->addEntityTypeFilter(Bpf_Hreflang_Model_Group::ENTITY_TYPE_CMS_PAGE)
            ->joinBaseCmsPage()
            ->addStoreCount();
        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $helper = Mage::helper('bpf_hreflang');

        $this->addColumn('group_id', [
            'header' => $helper->__('ID'),
            'index' => 'group_id',
            'filter_index' => 'main_table.group_id',
            'type' => 'number',
            'width' => '60px',
        ]);
        $this->addColumn('base_title', [
            'header' => $helper->__('Base Page'),
            'index' => 'base_title',
            'filter_index' => 'base_page.title',
        ]);
        $this->addColumn('base_identifier', [
            'header' => $helper->__('Base Page URL Key'),
            'index' => 'base_identifier',
            'filter_index' => 'base_page.identifier',
        ]);
        $this->addColumn('store_count', [
            'header' => $helper->__('Store Views'),
            'index' => 'store_count',
            'type' => 'number',
            'filter' => false,
            'width' => '100px',
        ]);
        $this->addColumn('updated_at', [
            'header' => $helper->__('Last Modified'),
            'index' => 'updated_at',
            'filter_index' => 'main_table.updated_at',
            'type' => 'datetime',
            'width' => '170px',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * @param Bpf_Hreflang_Model_Group $row
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getData('group_id')]);
    }
}
