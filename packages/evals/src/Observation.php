<?php

namespace Cognesy\Evals;

use Cognesy\Utils\DataMap;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

class Observation
{
    use Traits\Observation\HandlesAccess;

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
}
