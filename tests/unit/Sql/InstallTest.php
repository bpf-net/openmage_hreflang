<?php

use PHPUnit\Framework\TestCase;

/**
 * Runs the install script against a recording installer and checks the table definitions.
 */
class Bpf_Hreflang_Sql_InstallTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../../app/code/community/Bpf/Hreflang/sql/bpf_hreflang_setup/install-1.0.0.php';

    /** @var array<string, Varien_Db_Ddl_Table> */
    private static array $tables;

    public static function setUpBeforeClass(): void
    {
        $installer = new class {
            /** @var array<string, Varien_Db_Ddl_Table> */
            public array $created = [];

            public function startSetup(): self
            {
                return $this;
            }

            public function endSetup(): self
            {
                return $this;
            }

            public function getConnection(): self
            {
                return $this;
            }

            public function newTable(string $name): Varien_Db_Ddl_Table
            {
                return (new Varien_Db_Ddl_Table())->setName($name);
            }

            public function createTable(Varien_Db_Ddl_Table $table): void
            {
                $this->created[$table->getName()] = $table;
            }

            public function getTable(string $alias): string
            {
                return ['bpf_hreflang/group' => 'bpf_hreflang_group', 'bpf_hreflang/group_item' => 'bpf_hreflang_group_item', 'core/store' => 'core_store'][$alias];
            }

            /**
             * @param list<string> $fields
             */
            public function getIdxName(string $table, array $fields, string $type = ''): string
            {
                return strtoupper(($type ?: 'idx') . '_' . str_replace('/', '_', $table) . '_' . implode('_', $fields));
            }

            public function getFkName(string $table, string $column, string $refTable, string $refColumn): string
            {
                return strtoupper('fk_' . str_replace('/', '_', "{$table}_{$column}_{$refTable}_{$refColumn}"));
            }

            public function run(string $script): void
            {
                include $script;
            }
        };
        $installer->run(self::SCRIPT);
        self::$tables = $installer->created;
    }

    public function testCreatesBothTables(): void
    {
        $this->assertSame(['bpf_hreflang_group', 'bpf_hreflang_group_item'], array_keys(self::$tables));
    }

    public function testGroupTable(): void
    {
        $columns = self::$tables['bpf_hreflang_group']->getColumns();

        $this->assertSame(['GROUP_ID', 'ENTITY_TYPE', 'BASE_ENTITY_ID', 'CREATED_AT', 'UPDATED_AT'], array_keys($columns));
        $this->assertTrue($columns['GROUP_ID']['IDENTITY']);
        $this->assertTrue($columns['GROUP_ID']['PRIMARY']);
        $this->assertTrue($columns['GROUP_ID']['UNSIGNED']);
        $this->assertSame(32, $columns['ENTITY_TYPE']['LENGTH']);
    }

    public function testGroupItemColumnsMatchSpec(): void
    {
        $columns = self::$tables['bpf_hreflang_group_item']->getColumns();

        $this->assertSame(['ITEM_ID', 'GROUP_ID', 'ENTITY_TYPE', 'ENTITY_ID', 'STORE_ID'], array_keys($columns));
        $this->assertTrue($columns['ITEM_ID']['IDENTITY']);
        $this->assertSame('smallint', $columns['STORE_ID']['DATA_TYPE']);
        $this->assertTrue($columns['STORE_ID']['UNSIGNED'], 'store_id must match core_store.store_id for the FK');
        $this->assertTrue($columns['GROUP_ID']['UNSIGNED'], 'group_id must match bpf_hreflang_group.group_id for the FK');
    }

    public function testGroupItemUniqueConstraints(): void
    {
        $unique = [];
        foreach (self::$tables['bpf_hreflang_group_item']->getIndexes() as $index) {
            if ($index['TYPE'] === Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE) {
                $unique[] = array_column($index['COLUMNS'], 'NAME');
            }
        }

        $this->assertEqualsCanonicalizing([
            ['entity_type', 'entity_id', 'store_id'],
            ['group_id', 'store_id'],
        ], $unique);
    }

    public function testGroupItemForeignKeysCascade(): void
    {
        $keys = [];
        foreach (self::$tables['bpf_hreflang_group_item']->getForeignKeys() as $fk) {
            $keys[$fk['COLUMN_NAME']] = [$fk['REF_TABLE_NAME'], $fk['REF_COLUMN_NAME'], $fk['ON_DELETE']];
        }

        $this->assertSame([
            'group_id' => ['bpf_hreflang_group', 'group_id', Varien_Db_Ddl_Table::ACTION_CASCADE],
            'store_id' => ['core_store', 'store_id', Varien_Db_Ddl_Table::ACTION_CASCADE],
        ], $keys);
    }

    public function testScriptVersionMatchesModuleVersion(): void
    {
        $config = simplexml_load_file(__DIR__ . '/../../../app/code/community/Bpf/Hreflang/etc/config.xml');

        $this->assertSame('1.0.0', (string) $config->modules->Bpf_Hreflang->version);
        $this->assertSame('Bpf_Hreflang', (string) $config->global->resources->bpf_hreflang_setup->setup->module);
        $this->assertSame('bpf_hreflang_group', (string) $config->global->models->bpf_hreflang_resource->entities->group->table);
        $this->assertSame('bpf_hreflang_group_item', (string) $config->global->models->bpf_hreflang_resource->entities->group_item->table);
    }
}
