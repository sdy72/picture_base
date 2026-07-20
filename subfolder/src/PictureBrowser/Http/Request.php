<?php

declare(strict_types=1);

namespace PictureBrowser\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        string $target,
        string $basePath = '',
    ) {
        $this->path = self::pathFromTarget($target);
        $this->basePath = self::normalizeBasePath($basePath);
    }

    public readonly string $path;
    public readonly string $basePath;

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $target = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '');

        return new self(
            is_string($method) ? $method : '',
            is_string($target) ? $target : '',
            is_string($scriptName) ? self::basePathFromScriptName($scriptName) : '',
        );
    }

    public function applicationPath(): ?string
    {
        if ($this->basePath === '') {
            return $this->path;
        }

        if ($this->path === $this->basePath || $this->path === $this->basePath . '/') {
            return '/';
        }

        $prefix = $this->basePath . '/';
        if (!str_starts_with($this->path, $prefix)) {
            return null;
        }

        return substr($this->path, strlen($this->basePath));
    }

    private static function pathFromTarget(string $target): string
    {
        $queryPosition = strpos($target, '?');
        if ($queryPosition === false) {
            return $target;
        }

        return substr($target, 0, $queryPosition);
    }

    private static function basePathFromScriptName(string $scriptName): string
    {
        $path = self::pathFromTarget($scriptName);
        if (!str_ends_with($path, '/index.php')) {
            return '';
        }

        $directory = dirname($path);

        return $directory === '.' ? '' : $directory;
    }

    private static function normalizeBasePath(string $basePath): string
    {
        if ($basePath === '' || $basePath === '/') {
            return '';
        }

        if (!str_starts_with($basePath, '/')) {
            return '';
        }

        return rtrim($basePath, '/');
    }
}
