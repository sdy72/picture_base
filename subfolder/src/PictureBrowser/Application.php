<?php

declare(strict_types=1);

namespace PictureBrowser;

use PictureBrowser\Http\Request;
use PictureBrowser\Http\Response;
use PictureBrowser\Http\Router;

final class Application
{
    public function __construct(
        private readonly PictureCatalog $catalog,
        private readonly Router $router = new Router(),
        private readonly HtmlRenderer $renderer = new HtmlRenderer(),
    ) {
    }

    public function handle(Request $request): Response
    {
        $route = $this->router->match($request);
        if ($route === null) {
            return Response::notFound();
        }

        if ($request->method !== 'GET') {
            return Response::methodNotAllowed();
        }

        return match ($route['type']) {
            'home' => $this->homeResponse($request->basePath),
            'picture' => $this->pictureResponse($route['id'], $request->basePath),
            'media' => $this->mediaResponse($route['id']),
        };
    }

    private function homeResponse(string $basePath): Response
    {
        $entries = $this->catalog->entries();
        if ($entries === []) {
            return Response::notFound();
        }

        return $this->renderOverview($entries, $basePath);
    }

    private function pictureResponse(string $id, string $basePath): Response
    {
        $entry = $this->catalog->find($id);
        if ($entry === null) {
            return Response::notFound();
        }

        return $this->renderPicture($entry, $this->catalog->entries(), $basePath);
    }

    /** @param list<PictureEntry> $entries */
    private function renderPicture(PictureEntry $entry, array $entries, string $basePath): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
            $this->renderer->render($entry, $entries, $basePath),
        );
    }

    /** @param list<PictureEntry> $entries */
    private function renderOverview(array $entries, string $basePath): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
            $this->renderer->renderOverview($entries, $basePath),
        );
    }

    private function mediaResponse(string $id): Response
    {
        $media = $this->catalog->findMedia($id);
        if ($media === null) {
            return Response::notFound();
        }

        $contents = $this->catalog->readMedia($media);
        if ($contents === null) {
            return Response::notFound();
        }

        return new Response(
            200,
            [
                'Content-Type' => $media->mimeType,
                'Content-Length' => (string) strlen($contents),
            ],
            $contents,
        );
    }
}
