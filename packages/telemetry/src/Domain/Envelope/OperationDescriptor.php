<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Domain\Envelope;

final readonly class OperationDescriptor
{
    public function __construct(
        private string $id,
        private string $type,
        private string $name,
        private OperationKind $kind,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function kind(): OperationKind
    {
        return $this->kind;
    }

    /** @return array{id: string, type: string, name: string, kind: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'kind' => $this->kind->value,
        ];
    }

    /** @param array{id: string, type: string, name: string, kind: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            name: $data['name'],
            kind: OperationKind::from($data['kind']),
        );
    }
}
