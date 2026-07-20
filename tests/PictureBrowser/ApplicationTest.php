<?php

declare(strict_types=1);

namespace PictureBrowser\Tests;

use PictureBrowser\Application;
use PictureBrowser\PictureCatalog;
use PictureBrowser\PictureMedia;
use PictureBrowser\Http\Request;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private string $root;
    private Application $application;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/picture-browser-http-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
        $this->writeEntry(
            'safe_1',
            'picture.png',
            self::validPng(),
            "<script title=\"quoted\">Zażółć 'gęślą'\nline\r\nnext</script>",
        );
        $this->application = new Application(new PictureCatalog($this->root));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testPictureRouteRendersIdMediaReferenceAndEscapedText(): void
    {
        $response = $this->application->handle(new Request('GET', '/picture/safe_1?view=full'));

        self::assertSame(200, $response->statusCode);
        self::assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        self::assertStringContainsString('Picture safe_1', $response->body);
        self::assertStringContainsString('/media/safe_1', $response->body);
        self::assertStringContainsString(
            '&lt;script title=&quot;quoted&quot;&gt;Zażółć &#039;gęślą&#039;<br>',
            $response->body,
        );
        self::assertStringContainsString("line<br>\nnext&lt;/script&gt;", $response->body);
        self::assertStringNotContainsString('<script title="quoted">', $response->body);
    }

    public function testHomeRouteRendersTheFirstPicture(): void
    {
        $response = $this->application->handle(new Request('GET', '/'));

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('Picture safe_1', $response->body);
        self::assertStringContainsString('src="/media/safe_1"', $response->body);
    }

    public function testPictureRouteRendersOrderedCatalogNavigationAndBrowserAssets(): void
    {
        $this->writeEntry('10', 'picture.jpg', self::validJpeg(), 'ten');
        $this->writeEntry('2', 'picture.png', self::validPng(), 'two');
        $this->writeEntry('alpha', 'picture.png', self::validPng(), 'alpha');

        $response = $this->application->handle(new Request('GET', '/picture/2'));

        self::assertSame(200, $response->statusCode);
        self::assertStringContainsString('href="/assets/picture-browser.css"', $response->body);
        self::assertStringContainsString('src="/assets/picture-browser.js" defer', $response->body);
        self::assertStringContainsString(
            '<img class="picture-image" data-picture-image src="/media/2"',
            $response->body,
        );
        self::assertStringContainsString('loading="lazy"', $response->body);
        self::assertStringContainsString('data-zoom-level="1.0"', $response->body);
        self::assertStringNotContainsString('<form', $response->body);
        self::assertStringNotContainsString('upload', $response->body);
        self::assertStringNotContainsString('search', $response->body);

        $ids = ['2', '10', 'alpha', 'safe_1'];
        $positions = [];
        foreach ($ids as $id) {
            self::assertSame(1, substr_count($response->body, 'data-picture-id="' . $id . '"'));
            $positions[] = strpos($response->body, 'data-picture-id="' . $id . '"');
        }

        foreach ($positions as $index => $position) {
            self::assertNotFalse($position);
            if ($index > 0) {
                self::assertTrue($positions[$index - 1] < $position);
            }
        }

        self::assertStringContainsString('aria-current="page"', $response->body);
    }

    public function testBrowserZoomAssetDeclaresBoundedSteppedZoom(): void
    {
        $asset = file_get_contents(dirname(__DIR__, 2) . '/public/assets/picture-browser.js');

        self::assertNotFalse($asset);
        self::assertStringContainsString('const MIN_ZOOM = 0.5;', $asset);
        self::assertStringContainsString('const MAX_ZOOM = 3.0;', $asset);
        self::assertStringContainsString('const ZOOM_STEP = 0.25;', $asset);
        self::assertStringContainsString('const DEFAULT_ZOOM = 1.0;', $asset);
        self::assertStringContainsString('Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, value))', $asset);
        self::assertStringContainsString('zoom + change', $asset);
    }

    public function testPngMediaRouteReturnsOriginalBytesAndMimeType(): void
    {
        $bytes = self::validPng();
        $response = $this->application->handle(new Request('GET', '/media/safe_1'));

        self::assertSame(200, $response->statusCode);
        self::assertSame('image/png', $response->headers['Content-Type']);
        self::assertSame((string) strlen($bytes), $response->headers['Content-Length']);
        self::assertSame($bytes, $response->body);
    }

    public function testJpegMediaRouteReturnsOriginalBytesAndMimeType(): void
    {
        $bytes = self::validJpeg();
        $this->writeEntry('jpeg_1', 'picture.jpg', $bytes, 'jpeg');

        $response = $this->application->handle(new Request('GET', '/media/jpeg_1'));

        self::assertSame(200, $response->statusCode);
        self::assertSame('image/jpeg', $response->headers['Content-Type']);
        self::assertSame($bytes, $response->body);
    }

    public function testInvalidAndUnknownRoutesReturnGenericNotFound(): void
    {
        $paths = [
            '/picture/missing',
            '/picture/bad.dot',
            '/picture/../safe_1',
            '/picture/a%2Fb',
            '/media/a%5Cb',
            '/picture/safe_1/extra',
            '/unknown/safe_1',
        ];

        foreach ($paths as $path) {
            $response = $this->application->handle(new Request('GET', $path));

            self::assertSame(404, $response->statusCode, $path);
            self::assertSame("Not Found\n", $response->body, $path);
            self::assertStringNotContainsString($this->root, $response->body, $path);
        }
    }

    public function testNonGetOnKnownRouteReturnsMethodNotAllowed(): void
    {
        $response = $this->application->handle(new Request('POST', '/picture/safe_1'));

        self::assertSame(405, $response->statusCode);
        self::assertSame('GET', $response->headers['Allow']);
        self::assertSame("Method Not Allowed\n", $response->body);
    }

    public function testCatalogApprovedMediaIsRequiredForDelivery(): void
    {
        $catalog = new PictureCatalog($this->root);
        $media = $catalog->findMedia('safe_1');

        self::assertInstanceOf(PictureMedia::class, $media);
        self::assertSame(self::validPng(), $catalog->readMedia($media));

        $forged = new PictureMedia(
            'safe_1',
            'picture.png',
            $this->root . '/../outside.png',
            'image/png',
        );

        self::assertNull($catalog->readMedia($forged));
    }

    public function testMediaDeliveryRechecksSymlinkSafety(): void
    {
        if (!function_exists('symlink')) {
            self::markTestSkipped('Symlinks are not supported.');
        }

        $catalog = new PictureCatalog($this->root);
        $media = $catalog->findMedia('safe_1');
        self::assertInstanceOf(PictureMedia::class, $media);

        $outside = $this->root . '-outside';
        self::assertTrue(mkdir($outside, 0700));
        self::assertNotFalse(file_put_contents($outside . '/picture.png', self::validPng()));
        self::assertTrue(unlink($this->root . '/safe_1/picture.png'));
        self::assertTrue(symlink($outside . '/picture.png', $this->root . '/safe_1/picture.png'));

        try {
            self::assertNull($catalog->readMedia($media));
        } finally {
            $this->removeTree($outside);
        }
    }

    public function testRequestFromGlobalsUsesOnlyTheRequestTargetPath(): void
    {
        $hadMethod = array_key_exists('REQUEST_METHOD', $_SERVER);
        $hadUri = array_key_exists('REQUEST_URI', $_SERVER);
        $oldMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $oldUri = $_SERVER['REQUEST_URI'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/picture/safe_1?ignored=1';
            $request = Request::fromGlobals();

            self::assertSame('GET', $request->method);
            self::assertSame('/picture/safe_1', $request->path);
        } finally {
            if ($hadMethod) {
                $_SERVER['REQUEST_METHOD'] = $oldMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }

            if ($hadUri) {
                $_SERVER['REQUEST_URI'] = $oldUri;
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }
    }

    public function testPictureMediaRejectsUnsupportedMediaValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PictureMedia('safe_1', 'picture.gif', '/tmp/picture.gif', 'image/gif');
    }

    private function writeEntry(string $id, string $filename, string $image, string $text): void
    {
        self::assertTrue(mkdir($this->root . '/' . $id, 0700, true));
        self::assertNotFalse(file_put_contents($this->root . '/' . $id . '/' . $filename, $image));
        self::assertNotFalse(file_put_contents($this->root . '/' . $id . '/text.txt', $text));
    }

    private static function validPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
    }

    private static function validJpeg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8Qf//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8Qf//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8Qf//Z',
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
