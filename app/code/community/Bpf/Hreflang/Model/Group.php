<?php

/**
 * Translation group: versions of one piece of content across store views (store ID => entity ID).
 *
 * @method string getEntityType()
 * @method $this setEntityType(string $value)
 * @method int|null getBaseEntityId()
 * @method $this setBaseEntityId(int|null $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 * @method Bpf_Hreflang_Model_Resource_Group getResource()
 */
class Bpf_Hreflang_Model_Group extends Mage_Core_Model_Abstract
{
    public const ENTITY_TYPE_CMS_PAGE = 'cms_page';

    /** Registry key of the group edited in the admin. */
    public const REGISTRY_KEY = 'current_bpf_hreflang_group';

    protected $_eventPrefix = 'bpf_hreflang_group';

    protected $_eventObject = 'group';

    protected function _construct()
    {
        $this->_init('bpf_hreflang/group');
    }

    /**
     * Versions in the group, loaded on first use.
     *
     * @return array<int, int> store ID => entity ID
     */
    public function getItems(): array
    {
        if (!$this->hasData('items')) {
            $groupId = (int) $this->getData('group_id');
            $this->setData('items', $groupId ? $this->_getResource()->getItems($groupId) : []);
        }

        return $this->getData('items');
    }

    /**
     * Replaces the versions; saved together with the group. Empty entity IDs remove the store view.
     *
     * @param array<int|string, int|string|null> $items store ID => entity ID
     */
    public function setItems(array $items): self
    {
        $normalized = [];
        foreach ($items as $storeId => $entityId) {
            if ((int) $storeId > 0 && (int) $entityId > 0) {
                $normalized[(int) $storeId] = (int) $entityId;
            }
        }
        ksort($normalized);

        return $this->setData('items', $normalized);
    }

    public function getValidator(): Bpf_Hreflang_Model_Group_Validator
    {
        return Mage::getSingleton('bpf_hreflang/group_validator');
    }

    /**
     * @throws Mage_Core_Exception when the group breaks the translation group rules
     */
    protected function _beforeSave()
    {
        $errors = $this->getValidator()->validate($this);
        if ($errors !== []) {
            Mage::throwException(implode(' ', $errors));
        }

        $now = Varien_Date::now();
        if ($this->isObjectNew() || !$this->getId()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return parent::_beforeSave();
    }

    protected function _afterSave()
    {
        $this->_cleanHreflangCache();

        return parent::_afterSave();
    }

    protected function _afterDelete()
    {
        $this->_cleanHreflangCache();

        return parent::_afterDelete();
    }

    protected function _cleanHreflangCache(): void
    {
        Mage::app()->cleanCache([Bpf_Hreflang_Block_Head::CACHE_TAG]);
    }
}
