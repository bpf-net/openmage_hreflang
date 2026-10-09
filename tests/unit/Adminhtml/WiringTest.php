<?php

use PHPUnit\Framework\TestCase;

/**
 * Admin route, menu, ACL and layout must line up, or the page 404s or vanishes from the menu.
 */
class Bpf_Hreflang_Adminhtml_WiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';
    private const CONTROLLER = self::ROOT . '/app/code/community/Bpf/Hreflang/controllers/Adminhtml/Bpf/Hreflang/GroupController.php';

    private SimpleXMLElement $config;
    private SimpleXMLElement $adminhtml;

    protected function setUp(): void
    {
        $this->config = simplexml_load_file(self::ROOT . '/app/code/community/Bpf/Hreflang/etc/config.xml');
        $this->adminhtml = simplexml_load_file(self::ROOT . '/app/code/community/Bpf/Hreflang/etc/adminhtml.xml');
        require_once self::CONTROLLER;
    }

    public function testRouterMapsToControllerClassPrefix(): void
    {
        $module = $this->config->admin->routers->adminhtml->args->modules->Bpf_Hreflang;

        $this->assertSame('Bpf_Hreflang_Adminhtml', (string) $module);
        $this->assertSame('Mage_Adminhtml', (string) $module['before']);
        // Class Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController → URL adminhtml/bpf_hreflang_group
        $this->assertTrue(class_exists('Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController', false));
    }

    public function testMenuPointsToController(): void
    {
        $menu = $this->adminhtml->menu->cms->children->bpf_hreflang;

        $this->assertSame('adminhtml/bpf_hreflang_group', (string) $menu->action);
        $this->assertSame('cms/bpf_hreflang', Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController::MENU_PATH);
    }

    public function testControllerAclResourceIsDeclared(): void
    {
        $path = Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController::ADMIN_RESOURCE;
        $node = $this->adminhtml->acl->resources->admin;
        foreach (explode('/', $path) as $part) {
            $node = $node->children->{$part};
        }

        $this->assertSame('cms/bpf_hreflang', $path);
        $this->assertNotSame('', (string) $node->title, "ACL resource {$path} is not declared");
    }

    public function testAdminLayoutIsRegisteredAndDeployed(): void
    {
        $file = (string) $this->config->adminhtml->layout->updates->bpf_hreflang->file;
        $path = "app/design/adminhtml/base/default/layout/{$file}";

        $this->assertFileExists(self::ROOT . '/' . $path);
        $this->assertStringContainsString($path, (string) file_get_contents(self::ROOT . '/modman'));
    }

    public function testIndexPageShowsGroupGrid(): void
    {
        $layout = simplexml_load_file(self::ROOT . '/app/design/adminhtml/base/default/layout/bpf_hreflang.xml');
        $block = $layout->xpath('adminhtml_bpf_hreflang_group_index/reference[@name="content"]/block')[0];

        $this->assertSame('bpf_hreflang/adminhtml_group', (string) $block['type']);
        $this->assertTrue(is_subclass_of(Bpf_Hreflang_Block_Adminhtml_Group::class, Mage_Adminhtml_Block_Widget_Grid_Container::class));
        // The container renders block "<_blockGroup>/<_controller>_grid".
        $this->assertTrue(is_subclass_of(Bpf_Hreflang_Block_Adminhtml_Group_Grid::class, Mage_Adminhtml_Block_Widget_Grid::class));
    }

    public function testEditAndNewPagesShowGroupForm(): void
    {
        $layout = simplexml_load_file(self::ROOT . '/app/design/adminhtml/base/default/layout/bpf_hreflang.xml');
        $block = $layout->xpath('adminhtml_bpf_hreflang_group_edit/reference[@name="content"]/block')[0];

        $this->assertSame('bpf_hreflang/adminhtml_group_edit', (string) $block['type']);
        $this->assertSame('adminhtml_bpf_hreflang_group_edit', (string) $layout->adminhtml_bpf_hreflang_group_new->update['handle']);
        $this->assertTrue(is_subclass_of(Bpf_Hreflang_Block_Adminhtml_Group_Edit::class, Mage_Adminhtml_Block_Widget_Form_Container::class));
        // The form container renders block "<_blockGroup>/<_controller>_edit_form".
        $this->assertTrue(is_subclass_of(Bpf_Hreflang_Block_Adminhtml_Group_Edit_Form::class, Mage_Adminhtml_Block_Widget_Form::class));
    }

    public function testControllerHasGroupActions(): void
    {
        foreach (['index', 'new', 'edit', 'save', 'delete'] as $action) {
            $this->assertTrue(method_exists('Bpf_Hreflang_Adminhtml_Bpf_Hreflang_GroupController', $action . 'Action'), $action);
        }
    }
}
