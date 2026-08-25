<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

final readonly class CanonicalToolCall
{
    /** @var array<string, mixed> */
    private array $arguments;

    /** @param array<string, mixed> $arguments */
    public function __construct(
        private string $id,
        private string $name,
        array $arguments = [],
    ) {
        CanonicalInput::identifier($id, 'tool call id');
        CanonicalInput::identifier($name, 'tool name');
        CanonicalInput::map($arguments, 'tool arguments');
        $normalized = CanonicalValue::normalize($arguments);
        if (! is_array($normalized)) {
            throw new CanonicalValidationException('Canonical tool arguments must be an object.');
        }
        $this->arguments = $normalized;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        CanonicalInput::assertKeys($data, ['arguments', 'id', 'name']);

        return new self(
            CanonicalInput::identifier($data['id'], 'tool call id'),
            CanonicalInput::identifier($data['name'], 'tool name'),
            CanonicalInput::map($data['arguments'], 'tool arguments'),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /** @return array{arguments: CanonicalMap, id: string, name: string} */
    public function toCanonicalArray(): array
    {
        return [
            'arguments' => new CanonicalMap($this->arguments),
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
