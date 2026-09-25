<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Drivers\Support;

use JsonException;
use RuntimeException;

final readonly class BoundedHttpResponse
{
    public function __construct(
        public int $statusCode,
        public string $body,
    ) {}

    /** @return array<array-key, mixed> */
    public function json(string $backend): array
    {
        if ($this->body === '') {
            return [];
        }
        try {
            $decoded = json_decode($this->body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("{$backend} returned invalid JSON", 0, $error);
        }
        if (! is_array($decoded)) {
            throw new RuntimeException("{$backend} returned a non-object JSON payload");
        }

        return $decoded;
    }

    /** @return list<array<string, mixed>> */
    public function jsonLines(string $backend): array
    {
        $lines = preg_split('/\R/', trim($this->body));
        if ($lines === false || $lines === ['']) {
            return [];
        }
        $decoded = [];
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            try {
                $value = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException("{$backend} returned invalid JSONL", 0, $error);
            }
            if (! is_array($value) || array_is_list($value)) {
                throw new RuntimeException("{$backend} returned a non-object JSONL row");
            }
            /** @var array<string, mixed> $value */
            $decoded[] = $value;
        }

        return $decoded;
    }
}
