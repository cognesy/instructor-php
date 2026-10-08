<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use InvalidArgumentException;

final readonly class BatchFileBodyRequest
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $url,
        private string $path,
        private array $headers,
        private string $method = 'POST',
    ) {
        BatchTransportUrl::assertSecure($url);
        if (!is_file($path) || !is_readable($path) || !in_array($method, ['POST', 'PUT'], true)) {
            throw new InvalidArgumentException('Batch file body requires a readable file and POST or PUT.');
        }
        foreach ($headers as $name => $value) {
            if (!preg_match('/^[A-Za-z0-9-]+$/', $name) || preg_match('/[\r\n]/', $value) === 1) {
                throw new InvalidArgumentException('Invalid batch request header.');
            }
        }
    }

    public function url(): string
    {
        return $this->url;
    }
    public function path(): string
    {
        return $this->path;
    }
    public function method(): string
    {
        return $this->method;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
