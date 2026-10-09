<?php

use PHPUnit\Framework\TestCase;

class BootstrapTest extends TestCase
{
    public function testOpenMageClassesAutoload(): void
    {
        $this->assertTrue(class_exists(Mage::class));
        $this->assertTrue(class_exists(Varien_Object::class));
    }

    public function testModuleCodePoolIsOnIncludePath(): void
    {
        $this->assertStringStartsWith(
            dirname(__DIR__, 2) . '/app/code/community',
            get_include_path(),
        );
    }
}
