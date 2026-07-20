<?php

declare(strict_types=1);

namespace PictureBrowser\Http;

use PictureBrowser\PictureId;

final class Router
{
    /** @return array{type: 'home'}|array{type: 'picture'|'media', id: string}|null */
    public function match(Request $request): ?array
    {
        $path = $request->applicationPath();
        if ($path === null) {
            return null;
        }

        if ($path === '/') {
            return ['type' => 'home'];
        }

        $matches = [];
        if (preg_match('/\A\/(picture|media)\/([^\/]+)\z/', $path, $matches) !== 1) {
            return null;
        }

        $id = $matches[2];
        if (!PictureId::isValid($id)) {
            return null;
        }

        return ['type' => $matches[1], 'id' => $id];
    }
}
