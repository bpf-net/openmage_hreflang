<?php

class Bpf_Hreflang_Model_Resource_Group extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('bpf_hreflang/group', 'group_id');
    }

    /**
     * @return array<int, int> store ID => entity ID
     */
    public function getItems(int $groupId): array
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('bpf_hreflang/group_item'), ['store_id', 'entity_id'])
            ->where('group_id = ?', $groupId)
            ->order('store_id');

        return array_map('intval', $this->_getReadAdapter()->fetchPairs($select));
    }

    /**
     * Versions of the group the entity belongs to; empty when it is in no group.
     *
     * @return array<int, int> store ID => entity ID
     */
    public function getEntityGroupItems(string $entityType, int $entityId): array
    {
        $groupId = $this->getEntityGroupId($entityType, $entityId);

        return $groupId === null ? [] : $this->getItems($groupId);
    }

    /**
     * Group the entity belongs to (the lowest ID if, against the admin validation, it is in several).
     */
    public function getEntityGroupId(string $entityType, int $entityId): ?int
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('bpf_hreflang/group_item'), ['group_id'])
            ->where('entity_type = ?', $entityType)
            ->where('entity_id = ?', $entityId)
            ->order('group_id')
            ->limit(1);
        $groupId = $this->_getReadAdapter()->fetchOne($select);

        return $groupId === false ? null : (int) $groupId;
    }

    /**
     * Removes the entity from every group, e.g. after the entity was deleted.
     *
     * @return int number of removed versions
     */
    public function deleteEntity(string $entityType, int $entityId): int
    {
        return $this->_getWriteAdapter()->delete($this->getTable('bpf_hreflang/group_item'), [
            'entity_type = ?' => $entityType,
            'entity_id = ?' => $entityId,
        ]);
    }

    /**
     * Saves the versions set on the group (if any) in the same transaction as the group.
     */
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        if ($object->hasData('items')) {
            $this->_saveItems($object);
        }

        return parent::_afterSave($object);
    }

    protected function _saveItems(Mage_Core_Model_Abstract $group): void
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getTable('bpf_hreflang/group_item');
        $groupId = (int) $group->getData('group_id');

        $adapter->delete($table, ['group_id = ?' => $groupId]);

        $rows = [];
        foreach ($group->getData('items') as $storeId => $entityId) {
            $rows[] = [
                'group_id' => $groupId,
                'entity_type' => (string) $group->getData('entity_type'),
                'entity_id' => (int) $entityId,
                'store_id' => (int) $storeId,
            ];
        }
        if ($rows !== []) {
            $adapter->insertMultiple($table, $rows);
        }
    }
}
