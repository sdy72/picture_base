<?php

declare(strict_types=1);

namespace PictureBrowser;

use InvalidArgumentException;

final readonly class PictureMedia
{
    public function __construct(
        public string $id,
        public string $filename,
        public string $path,
        public string $mimeType,
    ) {
        if (!PictureId::isValid($id)) {
            throw new InvalidArgumentException('Invalid picture ID.');
        }

        $expectedMimeType = match ($filename) {
            'picture.jpg' => 'image/jpeg',
            'picture.png' => 'image/png',
            default => throw new InvalidArgumentException('Invalid picture filename.'),
        };

        if ($mimeType !== $expectedMimeType || $path === '') {
            throw new InvalidArgumentException('Invalid picture media.');
        }
    }
}
