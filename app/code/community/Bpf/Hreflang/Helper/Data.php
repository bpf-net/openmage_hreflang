<?php

class Bpf_Hreflang_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'bpf_hreflang/general/enabled';
    public const XML_PATH_LOCALE_CODE = 'bpf_hreflang/general/locale_code';
    public const XML_PATH_GROUP_SCOPE = 'bpf_hreflang/general/group_scope';
    public const XML_PATH_X_DEFAULT_STORE = 'bpf_hreflang/general/x_default_store';
    public const XML_PATH_EXCLUDED_ACTIONS = 'bpf_hreflang/general/excluded_actions';
    public const XML_PATH_ROOT_STORES = 'bpf_hreflang/url/root_stores';

    public const GROUP_SCOPE_WEBSITE = 'website';
    public const GROUP_SCOPE_GLOBAL = 'global';

    /**
     * @param null|bool|int|string|Mage_Core_Model_Store $store
     */
    public function isEnabled($store = null): bool
    {
        return (bool) $this->_getConfig(self::XML_PATH_ENABLED, $store);
    }

    /**
     * Hreflang code of the store view; empty string when the store does not take part.
     *
     * @param null|bool|int|string|Mage_Core_Model_Store $store
     */
    public function getLocaleCode($store = null): string
    {
        return trim((string) $this->_getConfig(self::XML_PATH_LOCALE_CODE, $store));
    }

    /**
     * Which store views are mutual alternatives: those of the same website, or all of them.
     */
    public function getGroupScope(): string
    {
        $scope = (string) $this->_getConfig(self::XML_PATH_GROUP_SCOPE, Mage_Core_Model_App::ADMIN_STORE_ID);

        return $scope === self::GROUP_SCOPE_GLOBAL ? self::GROUP_SCOPE_GLOBAL : self::GROUP_SCOPE_WEBSITE;
    }

    /**
     * Store view used as x-default for the given store's website; null when not configured.
     *
     * @param null|bool|int|string|Mage_Core_Model_Store $store
     */
    public function getXDefaultStoreId($store = null): ?int
    {
        $storeId = (int) $this->_getConfig(self::XML_PATH_X_DEFAULT_STORE, $store);

        return $storeId > 0 ? $storeId : null;
    }

    /**
     * Full action names excluded from hreflang output; a trailing "*" marks a prefix.
     *
     * @return list<string>
     */
    public function getExcludedActions(): array
    {
        $value = (string) $this->_getConfig(self::XML_PATH_EXCLUDED_ACTIONS, Mage_Core_Model_App::ADMIN_STORE_ID);
        $lines = array_map('trim', preg_split('/\R/', $value) ?: []);

        return array_values(array_unique(array_filter($lines, static fn (string $line): bool => $line !== '')));
    }

    /**
     * Store views served without the store code in the URL path.
     *
     * @return list<int>
     */
    public function getRootStoreIds(): array
    {
        $value = (string) $this->_getConfig(self::XML_PATH_ROOT_STORES, Mage_Core_Model_App::ADMIN_STORE_ID);
        $ids = array_map('intval', explode(',', $value));

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * @param null|bool|int|string|Mage_Core_Model_Store $store
     * @return mixed
     */
    protected function _getConfig(string $path, $store = null)
    {
        return Mage::getStoreConfig($path, $store);
    }
}
