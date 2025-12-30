<?php declare(strict_types=1);

namespace Cognesy\Utils;

final readonly class Metadata
{
    private array $metadata;

    public function __construct(array $metadata = []) {
        $this->metadata = $metadata;
    }

    // CONSTRUCTORS /////////////////////////////////////////////

    public static function empty(): self {
        return new self([]);
    }

    public static function fromArray(array $metadata): self {
        return new self($metadata);
    }

    // MUTATORS /////////////////////////////////////////////////

    public function withKeyValue(string $key, mixed $value): Metadata {
        $newMetadata = $this->metadata;
        $newMetadata[$key] = $value;
        return new self($newMetadata);
    }

    public function withoutKey(string $key): Metadata {
        $newMetadata = $this->metadata;
        unset($newMetadata[$key]);
        return new self($newMetadata);
    }

    public function withMergedData(array $data): Metadata {
        $newMetadata = array_merge($this->metadata, $data);
        return new self($newMetadata);
    }

    // ACCESSORS ////////////////////////////////////////////////

    public function get(string $key, mixed $default = null): mixed {
        return $this->metadata[$key] ?? $default;
    }

    public function hasKey(string $key): bool {
        return array_key_exists($key, $this->metadata);
    }

    public function keys(): array {
        return array_keys($this->metadata);
    }

    public function isEmpty(): bool {
        return empty($this->metadata);
    }

    // TRANSFORMATIONS / CONVERSIONS ////////////////////////////

    public function toArray(): array {
        return $this->metadata;
    }
}