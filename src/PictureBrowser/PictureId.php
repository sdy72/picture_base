<?php

declare(strict_types=1);

namespace PictureBrowser;

final class PictureId
{
    /** Maximum number of ASCII characters in a picture folder ID. */
    public const int MAX_LENGTH = 128;

    private function __construct()
    {
    }

    public static function isValid(string $id): bool
    {
        return strlen($id) <= self::MAX_LENGTH
            && preg_match('/\A[A-Za-z0-9_-]+\z/', $id) === 1;
    }

    public static function isNumeric(string $id): bool
    {
        return preg_match('/\A[0-9]+\z/', $id) === 1;
    }
}
