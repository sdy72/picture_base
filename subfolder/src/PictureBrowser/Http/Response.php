<?php

declare(strict_types=1);

namespace PictureBrowser\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
    }

    public static function notFound(): self
    {
        return new self(404, ['Content-Type' => 'text/plain; charset=UTF-8'], "Not Found\n");
    }

    public static function methodNotAllowed(): self
    {
        return new self(
            405,
            [
                'Allow' => 'GET',
                'Content-Type' => 'text/plain; charset=UTF-8',
            ],
            "Method Not Allowed\n",
        );
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $this->body;
    }
}
