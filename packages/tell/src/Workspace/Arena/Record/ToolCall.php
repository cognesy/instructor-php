<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena\Record;

use Cognesy\Tell\Workspace\Arena\RecordException;
use stdClass;

final readonly class ToolCall
{
    /** @var array<string, mixed> */
    private array $arguments;

    /** @param array<string, mixed> $arguments */
    public function __construct(
        private string $id,
        private string $name,
        array $arguments = [],
    ) {
        Input::identifier($id, 'tool call id');
        Input::identifier($name, 'tool name');
        Input::map($arguments, 'tool arguments');
        $normalized = Value::normalize($arguments);
        if (!is_array($normalized)) {
            throw new RecordException('Arena record tool arguments must be an object.');
        }
        $this->arguments = $normalized;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        Input::assertKeys($data, ['arguments', 'id', 'name']);

        return new self(
            Input::identifier($data['id'], 'tool call id'),
            Input::identifier($data['name'], 'tool name'),
            Input::map($data['arguments'], 'tool arguments'),
        );
    }

    public function id(): string {
        return $this->id;
    }

    public function name(): string {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function arguments(): array {
        return $this->arguments;
    }

    /** @return array{arguments: stdClass, id: string, name: string} */
    public function toArray(): array {
        return [
            'arguments' => (object) $this->arguments,
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
