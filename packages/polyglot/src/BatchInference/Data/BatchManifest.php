<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use InvalidArgumentException;

/** Ordered item keys needed when a provider publishes results without keys. */
final readonly class BatchManifest
{
    /** @param list<string> $keys */
    public function __construct(private array $keys)
    {
        if ($keys === [] || !array_is_list($keys) || count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException('Batch manifest requires an ordered list of unique keys.');
        }
        foreach ($keys as $key) {
            if ($key === '' || preg_match('/[\x00-\x1F\x7F]/', $key) === 1) {
                throw new InvalidArgumentException('Batch manifest contains an invalid key.');
            }
        }
    }

    public function count(): int
    {
        return count($this->keys);
    }

    public function keyAt(int $ordinal): string
    {
        return $this->keys[$ordinal] ?? throw new InvalidArgumentException('Batch result ordinal exceeds the saved manifest.');
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return $this->keys;
    }

    /** @param mixed $data */
    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !array_is_list($data)) {
            throw new InvalidArgumentException('Invalid serialized batch manifest.');
        }
        foreach ($data as $key) {
            if (!is_string($key)) {
                throw new InvalidArgumentException('Invalid serialized batch manifest key.');
            }
        }
        return new self($data);
    }
}
