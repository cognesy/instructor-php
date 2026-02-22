<?php declare(strict_types=1);

namespace Cognesy\Evals;

use Cognesy\Evals\ValueObject\ObservationId;
use Cognesy\Utils\Data\ImmutableDataMap;
use DateTimeImmutable;

readonly class Observation
{
    private ObservationId $id;
    private DateTimeImmutable $timestamp;
    private string $type;
    private string $key;
    private mixed $value;
    private ImmutableDataMap $metadata;

    private function __construct(
        ObservationId $id,
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
        $this->metadata = new ImmutableDataMap($metadata);
    }

    public static function make(
        string $type,
        string $key,
        mixed  $value = null,
        array  $metadata = []
    ) : self {
        return new self(
            id: ObservationId::generate(),
            timestamp: new DateTimeImmutable(),
            type: $type,
            key: $key,
            value: $value,
            metadata: $metadata
        );
    }

    public static function fromArray(array $data) : self {
        $id = (string) ($data['id'] ?? '');
        return new self(
            id: $id !== '' ? ObservationId::fromString($id) : ObservationId::generate(),
            timestamp: new DateTimeImmutable((string) ($data['timestamp'] ?? 'now')),
            type: (string) ($data['type'] ?? ''),
            key: (string) ($data['name'] ?? ''),
            value: $data['value'] ?? null,
            metadata: is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );
    }

    public function toArray() : array {
        return [
            'id' => $this->id->toString(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s.u'),
            'type' => $this->type,
            'name' => $this->key,
            'value' => $this->value,
            'metadata' => $this->metadata->toArray(),
        ];
    }

    public function withMetadata(array $metadata) : self {
        return new self(
            id: $this->id,
            timestamp: $this->timestamp,
            type: $this->type,
            key: $this->key,
            value: $this->value,
            metadata: array_merge($this->metadata->toArray(), $metadata)
        );
    }

    public function has(string $key) : bool {
        return $this->metadata->has($key);
    }

    public function get(string $key, mixed $default = null) : mixed {
        return $this->metadata->get($key, $default);
    }

    public function id() : ObservationId {
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

    public function toInt() : int {
        return (int) $this->value;
    }

    public function toFloat() : float {
        return (float) $this->value;
    }

    public function metadata() : ImmutableDataMap {
        return $this->metadata;
    }
}
