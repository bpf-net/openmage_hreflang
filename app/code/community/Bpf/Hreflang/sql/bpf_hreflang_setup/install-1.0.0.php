<?php

/**
 * Translation groups: manual pairing of entities (CMS pages, or other modules' content) across store views.
 *
 * @var Mage_Core_Model_Resource_Setup $this
 */
$installer = $this;
$installer->startSetup();
$connection = $installer->getConnection();

$table = $connection
    ->newTable($installer->getTable('bpf_hreflang/group'))
    ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
    ], 'Group ID')
    ->addColumn('entity_type', Varien_Db_Ddl_Table::TYPE_TEXT, 32, [
        'nullable' => false,
    ], 'Entity Type, e.g. cms_page')
    ->addColumn('base_entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => true,
    ], 'Entity the group was created from')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT,
    ], 'Created At')
    ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, [
        'nullable' => false,
        'default' => Varien_Db_Ddl_Table::TIMESTAMP_INIT_UPDATE,
    ], 'Updated At')
    ->addIndex(
        $installer->getIdxName('bpf_hreflang/group', ['entity_type']),
        ['entity_type'],
    )
    ->setComment('Bpf Hreflang Translation Group');
$connection->createTable($table);

$table = $connection
    ->newTable($installer->getTable('bpf_hreflang/group_item'))
    ->addColumn('item_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'identity' => true,
        'unsigned' => true,
        'nullable' => false,
        'primary' => true,
    ], 'Item ID')
    ->addColumn('group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Group ID')
    ->addColumn('entity_type', Varien_Db_Ddl_Table::TYPE_TEXT, 32, [
        'nullable' => false,
    ], 'Entity Type, e.g. cms_page')
    ->addColumn('entity_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Entity ID')
    ->addColumn('store_id', Varien_Db_Ddl_Table::TYPE_SMALLINT, null, [
        'unsigned' => true,
        'nullable' => false,
    ], 'Store View ID of this version')
    // An entity belongs to at most one group in a store view.
    ->addIndex(
        $installer->getIdxName(
            'bpf_hreflang/group_item',
            ['entity_type', 'entity_id', 'store_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['entity_type', 'entity_id', 'store_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    // A group has at most one version per store view.
    ->addIndex(
        $installer->getIdxName(
            'bpf_hreflang/group_item',
            ['group_id', 'store_id'],
            Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE,
        ),
        ['group_id', 'store_id'],
        ['type' => Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE],
    )
    ->addIndex(
        $installer->getIdxName('bpf_hreflang/group_item', ['store_id']),
        ['store_id'],
    )
    ->addForeignKey(
        $installer->getFkName('bpf_hreflang/group_item', 'group_id', 'bpf_hreflang/group', 'group_id'),
        'group_id',
        $installer->getTable('bpf_hreflang/group'),
        'group_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->addForeignKey(
        $installer->getFkName('bpf_hreflang/group_item', 'store_id', 'core/store', 'store_id'),
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_CASCADE,
        Varien_Db_Ddl_Table::ACTION_CASCADE,
    )
    ->setComment('Bpf Hreflang Translation Group Item');
$connection->createTable($table);

$installer->endSetup();
