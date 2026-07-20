<?php

declare(strict_types=1);

namespace PictureBrowser;

use InvalidArgumentException;

final readonly class PictureEntry
{
    public function __construct(
        public string $id,
        public string $imageFilename,
        public string $text,
    ) {
        if (!PictureId::isValid($id)) {
            throw new InvalidArgumentException('Invalid picture ID.');
        }

        if (!in_array($imageFilename, ['picture.jpg', 'picture.png'], true)) {
            throw new InvalidArgumentException('Invalid picture filename.');
        }

        if (preg_match('//u', $text) !== 1) {
            throw new InvalidArgumentException('Picture text must be valid UTF-8.');
        }
    }
}
