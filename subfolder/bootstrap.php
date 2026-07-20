<?php

declare(strict_types=1);

$namespacePrefix = 'PictureBrowser\\';
$sourceDirectory = __DIR__ . '/src/PictureBrowser/';

spl_autoload_register(static function (string $class) use ($namespacePrefix, $sourceDirectory): void {
    if (!str_starts_with($class, $namespacePrefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($namespacePrefix));
    $path = $sourceDirectory . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
