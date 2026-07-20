<?php

declare(strict_types=1);

namespace PictureBrowser;

final class HtmlRenderer
{
    /**
     * @param list<PictureEntry> $entries
     */
    public function render(PictureEntry $entry, array $entries, string $basePath = ''): string
    {
        $id = $this->escape($entry->id);
        $mediaUrl = $this->escape($this->url($basePath, '/media/' . $entry->id));
        $overviewUrl = $this->escape($this->url($basePath, '/'));
        $stylesheetUrl = $this->escape($this->url($basePath, '/assets/picture-browser.css'));
        $scriptUrl = $this->escape($this->url($basePath, '/assets/picture-browser.js'));
        $text = $this->renderText($entry->text);
        $navigation = $this->renderNavigation($entries, $entry->id, $basePath);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Picture {$id}</title>
<link rel="stylesheet" href="{$stylesheetUrl}">
<script src="{$scriptUrl}" defer></script>
</head>
<body>
<main class="picture-browser" data-current-picture-id="{$id}">
<section class="picture-viewer" aria-labelledby="picture-title">
<nav class="picture-breadcrumb" aria-label="Breadcrumb">
<a href="{$overviewUrl}">Overview</a>
<span aria-hidden="true"> &gt; </span>
<span aria-current="page">{$id}</span>
</nav>
<header class="picture-header">
<p class="picture-kicker">Picture</p>
<h1 id="picture-title">Picture {$id}</h1>
</header>
<div class="picture-stage">
<button class="picture-image-button" type="button" data-picture-open aria-controls="picture-lightbox" aria-expanded="false" aria-label="View picture {$id} fullscreen">
<img class="picture-image" data-picture-image src="{$mediaUrl}" alt="Picture {$id}" loading="lazy" decoding="async">
</button>
</div>
<div class="picture-lightbox" id="picture-lightbox" data-picture-lightbox hidden role="dialog" aria-modal="true" aria-label="Picture {$id} fullscreen view">
<button class="picture-lightbox-close" type="button" data-picture-close aria-label="Close fullscreen view">X</button>
<img class="picture-lightbox-image" src="{$mediaUrl}" alt="Picture {$id}">
</div>
<p class="picture-text">{$text}</p>
</section>
<nav class="picture-navigation" aria-label="Picture navigation">
<h2>Pictures</h2>
<ol>
{$navigation}
</ol>
</nav>
</main>
</body>
</html>
HTML;
    }

    /**
     * @param list<PictureEntry> $entries
     */
    public function renderOverview(array $entries, string $basePath = ''): string
    {
        $stylesheetUrl = $this->escape($this->url($basePath, '/assets/picture-browser.css'));
        $scriptUrl = $this->escape($this->url($basePath, '/assets/picture-browser.js'));
        $thumbnails = $this->renderOverviewItems($entries, $basePath);
        $navigation = $this->renderNavigation($entries, null, $basePath);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Overview</title>
<link rel="stylesheet" href="{$stylesheetUrl}">
<script src="{$scriptUrl}" defer></script>
</head>
<body>
<main class="picture-browser overview-mode">
<section class="picture-viewer overview-viewer" aria-labelledby="overview-title">
<nav class="picture-breadcrumb" aria-label="Breadcrumb">
<span aria-current="page">Overview</span>
</nav>
<header class="picture-header">
<p class="picture-kicker">Picture browser</p>
<h1 id="overview-title">Overview</h1>
</header>
<div class="picture-grid">
{$thumbnails}
</div>
</section>
<nav class="picture-navigation" aria-label="Picture navigation">
<h2>Pictures</h2>
<ol>
{$navigation}
</ol>
</nav>
</main>
</body>
</html>
HTML;
    }

    /**
     * @param list<PictureEntry> $entries
     */
    private function renderOverviewItems(array $entries, string $basePath): string
    {
        $items = [];
        foreach ($entries as $entry) {
            $id = $this->escape($entry->id);
            $pictureUrl = $this->escape($this->url($basePath, '/picture/' . $entry->id));
            $mediaUrl = $this->escape($this->url($basePath, '/media/' . $entry->id));

            $items[] = <<<HTML
<article class="picture-thumbnail" data-picture-id="{$id}">
<a class="picture-thumbnail-link" href="{$pictureUrl}" aria-label="Open picture {$id}">
<span class="picture-thumbnail-frame">
<img class="picture-thumbnail-image" src="{$mediaUrl}" alt="Picture {$id}" loading="lazy" decoding="async">
</span>
<span class="picture-thumbnail-label">Picture {$id}</span>
</a>
</article>
HTML;
        }

        return implode("\n", $items);
    }

    /**
     * @param list<PictureEntry> $entries
     */
    private function renderNavigation(array $entries, ?string $currentId, string $basePath): string
    {
        $items = [];
        foreach ($entries as $entry) {
            $items[] = $this->renderNavigationItem($entry, $currentId, $basePath);
        }

        return implode("\n", $items);
    }

    private function renderNavigationItem(PictureEntry $entry, ?string $currentId, string $basePath): string
    {
        $id = $this->escape($entry->id);
        $url = $this->escape($this->url($basePath, '/picture/' . $entry->id));
        $current = $entry->id === $currentId;
        $currentAttribute = $current ? ' aria-current="page"' : '';
        $currentClass = $current ? ' class="is-current"' : '';

        return <<<HTML
<li{$currentClass} data-picture-id="{$id}"><a href="{$url}"{$currentAttribute}>Picture {$id}</a></li>
HTML;
    }

    private function renderText(string $text): string
    {
        $escaped = $this->escape($text);

        return strtr($escaped, [
            "\r\n" => "<br>\n",
            "\r" => "<br>\n",
            "\n" => "<br>\n",
        ]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function url(string $basePath, string $path): string
    {
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
}
