<?php

declare(strict_types=1);

namespace PictureBrowser;

final class PictureCatalog
{
    private readonly ?string $rootPath;

    public function __construct(string $configuredRoot)
    {
        $this->rootPath = self::resolveConfiguredRoot($configuredRoot);
    }

    /**
     * @return list<PictureEntry>
     */
    public function entries(): array
    {
        if ($this->rootPath === null) {
            return [];
        }

        $handle = @opendir($this->rootPath);
        if ($handle === false) {
            return [];
        }

        $entries = [];
        try {
            while (($name = readdir($handle)) !== false) {
                if ($name === '.' || $name === '..') {
                    continue;
                }

                $entry = $this->readEntry($name);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
        } finally {
            closedir($handle);
        }

        usort($entries, self::compareEntries(...));

        return $entries;
    }

    public function find(string $id): ?PictureEntry
    {
        if (!PictureId::isValid($id)) {
            return null;
        }

        foreach ($this->entries() as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    private static function resolveConfiguredRoot(string $configuredRoot): ?string
    {
        if ($configuredRoot === '') {
            return null;
        }

        $resolved = @realpath($configuredRoot);
        if ($resolved === false || !is_dir($resolved) || !is_readable($resolved)) {
            return null;
        }

        return $resolved;
    }

    private function readEntry(string $id): ?PictureEntry
    {
        if (!PictureId::isValid($id)) {
            return null;
        }

        $directory = $this->resolveDirectory($id);
        if ($directory === null) {
            return null;
        }

        $jpgPresent = $this->hasChild($directory, 'picture.jpg');
        $pngPresent = $this->hasChild($directory, 'picture.png');
        if ($jpgPresent === $pngPresent) {
            return null;
        }

        $imageFilename = $jpgPresent ? 'picture.jpg' : 'picture.png';
        $imagePath = $this->resolveReadableFile($directory, $imageFilename);
        $textPath = $this->resolveReadableFile($directory, 'text.txt');
        if ($imagePath === null || $textPath === null) {
            return null;
        }

        if (!$this->isUsableImage($imagePath, $imageFilename)) {
            return null;
        }

        $text = @file_get_contents($textPath);
        if ($text === false || preg_match('//u', $text) !== 1) {
            return null;
        }

        return new PictureEntry($id, $imageFilename, $text);
    }

    private function resolveDirectory(string $id): ?string
    {
        $resolved = $this->resolveReadablePath($this->pathFor($id), true);
        if ($resolved === null || $resolved === $this->rootPath) {
            return null;
        }

        return $resolved;
    }

    private function resolveReadableFile(string $directory, string $filename): ?string
    {
        if (!$this->hasChild($directory, $filename)) {
            return null;
        }

        return $this->resolveReadablePath(
            $directory . DIRECTORY_SEPARATOR . $filename,
            false,
        );
    }

    private function resolveReadablePath(string $path, bool $directory): ?string
    {
        $resolved = @realpath($path);
        if ($resolved === false || !$this->isBelowRoot($resolved)) {
            return null;
        }

        if ($directory && !is_dir($resolved)) {
            return null;
        }

        if (!$directory && !is_file($resolved)) {
            return null;
        }

        return is_readable($resolved) ? $resolved : null;
    }

    private function pathFor(string $name): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . $name;
    }

    private function hasChild(string $directory, string $name): bool
    {
        $handle = @opendir($directory);
        if ($handle === false) {
            return false;
        }

        try {
            while (($child = readdir($handle)) !== false) {
                if ($child === $name) {
                    return true;
                }
            }
        } finally {
            closedir($handle);
        }

        return false;
    }

    private function isBelowRoot(string $path): bool
    {
        $root = $this->rootPath;
        if ($root === null) {
            return false;
        }

        if ($root === DIRECTORY_SEPARATOR) {
            return str_starts_with($path, DIRECTORY_SEPARATOR);
        }

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    private function isUsableImage(string $path, string $filename): bool
    {
        $imageInfo = @getimagesize($path);
        if (!is_array($imageInfo)) {
            return false;
        }

        $expectedType = $filename === 'picture.jpg' ? IMAGETYPE_JPEG : IMAGETYPE_PNG;

        return ($imageInfo[0] ?? 0) > 0
            && ($imageInfo[1] ?? 0) > 0
            && ($imageInfo[2] ?? null) === $expectedType;
    }

    private static function compareEntries(PictureEntry $left, PictureEntry $right): int
    {
        // Numeric IDs form the first sort group; nonnumeric IDs use bytewise lexical order.
        $leftNumeric = PictureId::isNumeric($left->id);
        $rightNumeric = PictureId::isNumeric($right->id);

        if ($leftNumeric !== $rightNumeric) {
            return $leftNumeric ? -1 : 1;
        }

        if (!$leftNumeric) {
            return strcmp($left->id, $right->id);
        }

        $leftNumber = ltrim($left->id, '0') ?: '0';
        $rightNumber = ltrim($right->id, '0') ?: '0';
        $lengthOrder = strlen($leftNumber) <=> strlen($rightNumber);
        if ($lengthOrder !== 0) {
            return $lengthOrder;
        }

        $numberOrder = strcmp($leftNumber, $rightNumber);

        return $numberOrder !== 0 ? $numberOrder : strcmp($left->id, $right->id);
    }
}
