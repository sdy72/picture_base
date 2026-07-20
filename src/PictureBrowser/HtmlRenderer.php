<?php

declare(strict_types=1);

namespace PictureBrowser;

final class HtmlRenderer
{
    /**
     * @param list<PictureEntry> $entries
     */
    public function render(PictureEntry $entry, array $entries): string
    {
        $id = $this->escape($entry->id);
        $mediaUrl = $this->escape('/media/' . $entry->id);
        $text = $this->renderText($entry->text);
        $navigation = $this->renderNavigation($entries, $entry->id);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Picture {$id}</title>
<link rel="stylesheet" href="/assets/picture-browser.css">
<script src="/assets/picture-browser.js" defer></script>
</head>
<body>
<main class="picture-browser" data-current-picture-id="{$id}">
<section class="picture-viewer" aria-labelledby="picture-title">
<header class="picture-header">
<p class="picture-kicker">Picture</p>
<h1 id="picture-title">Picture {$id}</h1>
</header>
<div class="picture-stage">
<img class="picture-image" data-picture-image src="{$mediaUrl}" alt="Picture {$id}" loading="lazy" decoding="async">
</div>
<div class="zoom-controls" aria-label="Zoom controls">
<button type="button" data-zoom-action="decrease" aria-label="Zoom out">−</button>
<output data-zoom-level="1.0" aria-live="polite">1.0×</output>
<button type="button" data-zoom-action="increase" aria-label="Zoom in">+</button>
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
    private function renderNavigation(array $entries, string $currentId): string
    {
        $items = [];
        foreach ($entries as $entry) {
            $items[] = $this->renderNavigationItem($entry, $currentId);
        }

        return implode("\n", $items);
    }

    private function renderNavigationItem(PictureEntry $entry, string $currentId): string
    {
        $id = $this->escape($entry->id);
        $url = $this->escape('/picture/' . $entry->id);
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
}
