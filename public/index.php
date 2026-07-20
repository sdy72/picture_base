<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$configuredRoot = getenv('PICTURES_ROOT');
$root = is_string($configuredRoot) ? $configuredRoot : '';
$application = new PictureBrowser\Application(new PictureBrowser\PictureCatalog($root));
$application->handle(PictureBrowser\Http\Request::fromGlobals())->send();
