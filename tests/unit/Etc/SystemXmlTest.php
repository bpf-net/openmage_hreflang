<?php

use PHPUnit\Framework\TestCase;

/**
 * Keeps system.xml, adminhtml.xml and the helper's config paths in sync.
 */
class Bpf_Hreflang_Etc_SystemXmlTest extends TestCase
{
    private const ETC_DIR = __DIR__ . '/../../../app/code/community/Bpf/Hreflang/etc';

    private SimpleXMLElement $section;

    protected function setUp(): void
    {
        $system = simplexml_load_file(self::ETC_DIR . '/system.xml');
        $this->section = $system->sections->bpf_hreflang;
    }

    public function testFieldPathsMatchHelperConstants(): void
    {
        $fieldPaths = [];
        foreach ($this->section->groups->children() as $groupName => $group) {
            foreach ($group->fields->children() as $fieldName => $field) {
                $fieldPaths[] = "bpf_hreflang/{$groupName}/{$fieldName}";
            }
        }

        $helperPaths = array_values(array_filter(
            (new ReflectionClass(Bpf_Hreflang_Helper_Data::class))->getConstants(),
            static fn (string $name): bool => str_starts_with($name, 'XML_PATH_'),
            ARRAY_FILTER_USE_KEY,
        ));

        sort($fieldPaths);
        sort($helperPaths);
        $this->assertSame($helperPaths, $fieldPaths);
    }

    public function testModuleSourceModelsExist(): void
    {
        foreach ($this->section->xpath('groups/*/fields/*/source_model') as $sourceModel) {
            $alias = (string) $sourceModel;
            if (!str_starts_with($alias, 'bpf_hreflang/')) {
                continue;
            }
            $class = $this->modelClass($alias);

            $this->assertTrue(class_exists($class), "Source model {$alias} ({$class}) not found");
            $this->assertTrue(method_exists($class, 'toOptionArray'), "{$class} lacks toOptionArray()");
        }
    }

    public function testModuleBackendModelsExist(): void
    {
        foreach ($this->section->xpath('groups/*/fields/*/backend_model') as $backendModel) {
            $alias = (string) $backendModel;
            if (!str_starts_with($alias, 'bpf_hreflang/')) {
                continue;
            }
            $class = $this->modelClass($alias);

            $this->assertTrue(class_exists($class), "Backend model {$alias} ({$class}) not found");
            $this->assertTrue(is_subclass_of($class, Mage_Core_Model_Config_Data::class), "{$class} must extend Mage_Core_Model_Config_Data");
        }
    }

    public function testLocaleCodeFieldIsValidatedOnSave(): void
    {
        $backendModel = $this->section->groups->general->fields->locale_code->backend_model;

        $this->assertSame('bpf_hreflang/system_config_backend_localeCode', (string) $backendModel);
    }

    /**
     * Resolves a "bpf_hreflang/…" model alias the way Mage_Core_Model_Config does.
     */
    private function modelClass(string $alias): string
    {
        $path = substr($alias, strlen('bpf_hreflang/'));

        return 'Bpf_Hreflang_Model_' . str_replace(' ', '_', ucwords(str_replace('_', ' ', $path)));
    }

    public function testConfigSectionHasAclResource(): void
    {
        $adminhtml = simplexml_load_file(self::ETC_DIR . '/adminhtml.xml');
        $resource = $adminhtml->xpath('acl/resources/admin/children/system/children/config/children/bpf_hreflang');

        $this->assertCount(1, $resource);
        $this->assertNotSame('', (string) $resource[0]->title);
    }
}
