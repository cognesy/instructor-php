<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Transport;

use InvalidArgumentException;

final readonly class BatchFileUpload
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $fields
     */
    public function __construct(
        private string $url,
        private string $path,
        private array $headers,
        private array $fields = [],
        private string $fileField = 'file',
        private string $fileName = 'batch.jsonl',
        private string $contentType = 'application/jsonl',
    ) {
        BatchTransportUrl::assertSecure($url);
        if (!is_file($path) || !is_readable($path) || $fileField === '' || $fileName === '') {
            throw new InvalidArgumentException('Batch upload requires a readable file and a named multipart field.');
        }
        foreach ($headers as $name => $value) {
            if (!preg_match('/^[A-Za-z0-9-]+$/', $name) || preg_match('/[\r\n]/', $value) === 1) {
                throw new InvalidArgumentException('Invalid batch upload header.');
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

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return array<string, string> */
    public function fields(): array
    {
        return $this->fields;
    }

    public function fileField(): string
    {
        return $this->fileField;
    }
    public function fileName(): string
    {
        return $this->fileName;
    }
    public function contentType(): string
    {
        return $this->contentType;
    }
}
