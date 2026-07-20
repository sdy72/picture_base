<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$configuredRoot = getenv('PICTURES_ROOT');
$root = is_string($configuredRoot) && $configuredRoot !== ''
    ? $configuredRoot
    : __DIR__ . '/pictures';
$application = new PictureBrowser\Application(new PictureBrowser\PictureCatalog($root));
$application->handle(PictureBrowser\Http\Request::fromGlobals())->send();
