<?php

declare(strict_types=1);

namespace PictureBrowser\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        string $target,
    ) {
        $this->path = self::pathFromTarget($target);
    }

    public readonly string $path;

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $target = $_SERVER['REQUEST_URI'] ?? '';

        return new self(
            is_string($method) ? $method : '',
            is_string($target) ? $target : '',
        );
    }

    private static function pathFromTarget(string $target): string
    {
        $queryPosition = strpos($target, '?');
        if ($queryPosition === false) {
            return $target;
        }

        return substr($target, 0, $queryPosition);
    }
}
