<?php

declare(strict_types=1);

namespace PictureBrowser;

final class HtmlRenderer
{
    public function render(PictureEntry $entry): string
    {
        $id = $this->escape($entry->id);
        $mediaUrl = $this->escape('/media/' . $entry->id);
        $text = $this->renderText($entry->text);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Picture {$id}</title>
</head>
<body>
<main>
<h1>Picture {$id}</h1>
<img src="{$mediaUrl}" alt="Picture {$id}">
<p>{$text}</p>
</main>
</body>
</html>
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
