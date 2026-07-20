<?php

declare(strict_types=1);

namespace PictureBrowser\Http;

use PictureBrowser\PictureId;

final class Router
{
    /** @return array{type: 'picture'|'media', id: string}|null */
    public function match(Request $request): ?array
    {
        $matches = [];
        if (preg_match('/\A\/(picture|media)\/([^\/]+)\z/', $request->path, $matches) !== 1) {
            return null;
        }

        $id = $matches[2];
        if (!PictureId::isValid($id)) {
            return null;
        }

        return ['type' => $matches[1], 'id' => $id];
    }
}
