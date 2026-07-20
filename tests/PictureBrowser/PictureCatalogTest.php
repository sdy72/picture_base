<?php

declare(strict_types=1);

namespace PictureBrowser\Tests;

use PictureBrowser\PictureCatalog;
use PictureBrowser\PictureId;
use PHPUnit\Framework\TestCase;

final class PictureCatalogTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/picture-browser-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testValidPngEntryPreservesUtf8Text(): void
    {
        $this->writeEntry('summer_1', "Zażółć gęślą jaźń\n");

        $entries = (new PictureCatalog($this->root))->entries();

        self::assertCount(1, $entries);
        self::assertSame('summer_1', $entries[0]->id);
        self::assertSame('picture.png', $entries[0]->imageFilename);
        self::assertSame("Zażółć gęślą jaźń\n", $entries[0]->text);
    }

    public function testMissingRequiredFilesAndWrongCaseNamesAreSkipped(): void
    {
        $this->makeDirectory('no-image');
        $this->writeText('no-image', 'text');
        $this->makeDirectory('no-text');
        $this->writeImage('no-text', 'picture.png');
        $this->makeDirectory('wrong-case');
        $this->writeImage('wrong-case', 'Picture.png');
        $this->writeText('wrong-case', 'text');

        self::assertSame([], (new PictureCatalog($this->root))->entries());
    }

    public function testBothImageFilenamesAreAmbiguousAndSkipped(): void
    {
        $this->makeDirectory('ambiguous');
        $this->writeImage('ambiguous', 'picture.jpg');
        $this->writeImage('ambiguous', 'picture.png');
        $this->writeText('ambiguous', 'text');

        self::assertSame([], (new PictureCatalog($this->root))->entries());
    }

    public function testInvalidImageContentAndInvalidUtf8AreSkipped(): void
    {
        $this->makeDirectory('bad-image');
        $this->writeFile('bad-image/picture.png', 'not an image');
        $this->writeText('bad-image', 'text');
        $this->makeDirectory('bad-text');
        $this->writeImage('bad-text', 'picture.png');
        $this->writeFile('bad-text/text.txt', "invalid \xB1\x31");

        self::assertSame([], (new PictureCatalog($this->root))->entries());
    }

    public function testMalformedIdsAndTraversalAttemptsAreRejected(): void
    {
        foreach (['bad.dot', 'bad space', str_repeat('a', PictureId::MAX_LENGTH + 1)] as $id) {
            $this->writeEntry($id, 'text');
        }

        $catalog = new PictureCatalog($this->root);

        self::assertSame([], $catalog->entries());
        foreach (['../outside', '..', '.', 'nested/id', 'bad.dot'] as $attempt) {
            self::assertNull($catalog->find($attempt));
        }
    }

    public function testDirectorySymlinkOutsideRootIsSkipped(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks are not supported.');
        }

        $outside = $this->root . '-outside';
        self::assertTrue(mkdir($outside, 0700));
        $this->writeEntryAt($outside, 'escaped', 'outside');

        try {
            if (@symlink($outside . '/escaped', $this->root . '/escaped') === false) {
                self::markTestSkipped('The test environment cannot create symlinks.');
            }

            self::assertSame([], (new PictureCatalog($this->root))->entries());
        } finally {
            $this->removeTree($outside);
        }
    }

    public function testFileSymlinkOutsideRootIsSkipped(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks are not supported.');
        }

        $outside = $this->root . '-outside';
        self::assertTrue(mkdir($outside, 0700));
        $this->writeFileAt($outside, 'picture.png', self::validPng());

        try {
            $this->makeDirectory('escaped-file');
            $this->writeText('escaped-file', 'inside');
            if (@symlink($outside . '/picture.png', $this->root . '/escaped-file/picture.png') === false) {
                self::markTestSkipped('The test environment cannot create symlinks.');
            }

            self::assertSame([], (new PictureCatalog($this->root))->entries());
        } finally {
            $this->removeTree($outside);
        }
    }

    public function testUnreadableImageIsSkippedWhenPermissionsAreEnforced(): void
    {
        $this->writeEntry('unreadable', 'text');
        $image = $this->root . '/unreadable/picture.png';
        self::assertTrue(chmod($image, 0000));

        if (is_readable($image)) {
            chmod($image, 0600);
            self::markTestSkipped('The test process can read chmod 0000 files.');
        }

        try {
            self::assertSame([], (new PictureCatalog($this->root))->entries());
        } finally {
            chmod($image, 0600);
        }
    }

    public function testMissingAndEmptyRootsProduceEmptyCatalogs(): void
    {
        self::assertSame([], (new PictureCatalog(''))->entries());
        self::assertSame([], (new PictureCatalog($this->root . '-missing'))->entries());
        self::assertSame([], (new PictureCatalog($this->root))->entries());
    }

    public function testNumericIdsSortNumericallyAndOtherIdsSortLexically(): void
    {
        foreach (['10', '2', '01', 'alpha', 'Beta', 'alpha-2'] as $id) {
            $this->writeEntry($id, $id);
        }

        $ids = array_map(
            static fn ($entry): string => $entry->id,
            (new PictureCatalog($this->root))->entries(),
        );

        self::assertSame(['01', '2', '10', 'Beta', 'alpha', 'alpha-2'], $ids);
    }

    private function writeEntry(string $id, string $text): void
    {
        $this->makeDirectory($id);
        $this->writeImage($id, 'picture.png');
        $this->writeText($id, $text);
    }

    private function writeEntryAt(string $base, string $id, string $text): void
    {
        $directory = $base . '/' . $id;
        self::assertTrue(mkdir($directory, 0700, true));
        $this->writeFileAt($directory, 'picture.png', self::validPng());
        $this->writeFileAt($directory, 'text.txt', $text);
    }

    private function makeDirectory(string $id): void
    {
        self::assertTrue(mkdir($this->root . '/' . $id, 0700, true));
    }

    private function writeImage(string $id, string $filename): void
    {
        $this->writeFile($id . '/' . $filename, self::validPng());
    }

    private function writeText(string $id, string $text): void
    {
        $this->writeFile($id . '/text.txt', $text);
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $this->writeFileAt($this->root, $relativePath, $contents);
    }

    private function writeFileAt(string $base, string $relativePath, string $contents): void
    {
        self::assertNotFalse(file_put_contents($base . '/' . $relativePath, $contents));
    }

    private static function validPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }

    private function removeTree(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }

        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->removeTree($path . '/' . $name);
            }
        }

        rmdir($path);
    }
}
