<?php

/**
 * Symlinks this module into vendor/openmage/magento-lts according to modman,
 * so tools that read the Magento root (PHPStan, a dev shop) see Bpf_Hreflang.
 * Run by Composer after install/update; links are relative to stay valid on host and in Docker.
 */

$root = dirname(__DIR__);
$magentoRoot = $root . '/vendor/openmage/magento-lts';
if (!is_dir($magentoRoot)) {
    fwrite(STDERR, "link-module: {$magentoRoot} not found, skipping\n");
    exit(0);
}

$relativePath = static function (string $from, string $to): string {
    $from = explode('/', trim($from, '/'));
    $to = explode('/', trim($to, '/'));
    while ($from && $to && $from[0] === $to[0]) {
        array_shift($from);
        array_shift($to);
    }
    return str_repeat('../', count($from)) . implode('/', $to);
};

foreach (file($root . '/modman', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || $line[0] === '@') {
        continue;
    }
    [$source, $target] = preg_split('/\s+/', $line) + [1 => null];
    $target = rtrim($target ?? $source, '/');
    $sourcePath = $root . '/' . rtrim($source, '/');
    $targetPath = $magentoRoot . '/' . $target;

    if (!file_exists($sourcePath)) {
        fwrite(STDERR, "link-module: missing source {$source}\n");
        continue;
    }
    if (is_link($targetPath)) {
        unlink($targetPath);
    } elseif (file_exists($targetPath)) {
        fwrite(STDERR, "link-module: {$target} exists and is not a symlink, skipping\n");
        continue;
    }
    if (!is_dir(dirname($targetPath))) {
        mkdir(dirname($targetPath), 0777, true);
    }
    symlink($relativePath(dirname($targetPath), $sourcePath), $targetPath);
}
