<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTimeImmutable;

class Observation
{
    private readonly string $id;
    private readonly DateTimeImmutable $timestamp;
    private string $type;
    private string $key;
    private mixed $value;
    private DataMap $metadata;

    private function __construct(
        string $id,
        DateTimeImmutable $timestamp,
        string $type,
        string $key,
        mixed  $value = null,
        array  $metadata = []
    ) {
        $this->id = $id;
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->key = $key;
        $this->value = $value;
        $this->metadata = new DataMap($metadata);
    }

    public static function make(
        string $type,
        string $key,
        mixed  $value = null,
        array  $metadata = []
    ) : self {
        return new self(
            id: Uuid::uuid4(),
            timestamp: new DateTimeImmutable(),
            type: $type,
            key: $key,
            value: $value,
            metadata: $metadata
        );
    }

    public static function fromArray(array $data) : self {
        return new self(
            id: $data['id'],
            timestamp: new DateTimeImmutable($data['timestamp']),
            type: $data['type'],
            key: $data['name'],
            value: $data['value'],
            metadata: $data['metadata']
        );
    }

    public function withValue(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    public function withMetadata(array $metadata) : self {
        $this->metadata->merge($metadata);
        return $this;
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'type' => $this->type,
            'name' => $this->key,
            'value' => $this->value,
            'metadata' => $this->metadata->toArray(),
        ];
    }

    public function has(string $key) : bool {
        return $this->metadata->has($key);
    }

    public function get(string $key, mixed $default = null) : mixed {
        return $this->metadata->get($key, $default);
    }

    public function id() : string {
        return $this->id;
    }

    public function timestamp() : DateTimeImmutable {
        return $this->timestamp;
    }

    public function type() : string {
        return $this->type;
    }

    public function key() : string {
        return $this->key;
    }

    public function value() : mixed {
        return $this->value;
    }

    public function metadata() : DataMap {
        return $this->metadata;
    }

    public function toFloat() : float {
        return (float) $this->value;
    }
}
