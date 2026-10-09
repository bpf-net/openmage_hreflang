<?php

/**
 * Unit test bootstrap: loads OpenMage classes and autoloading without Mage::app(),
 * so tests run without local.xml or a database.
 */

$root = dirname(__DIR__);

// Mage.php looks for vendor/ next to the OpenMage root; point it at this repo's vendor/.
putenv('COMPOSER_VENDOR_PATH=' . $root . '/vendor');

require $root . '/vendor/openmage/magento-lts/app/Mage.php';

// Resolve Bpf_Hreflang_* classes from this repository before OpenMage's own code pools.
set_include_path($root . '/app/code/community' . PATH_SEPARATOR . get_include_path());
