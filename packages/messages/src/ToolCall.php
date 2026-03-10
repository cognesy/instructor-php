<?php declare(strict_types=1);

namespace Cognesy\Messages;

use Cognesy\Utils\Json\Json;
use InvalidArgumentException;

final readonly class ToolCall
{
    private ?ToolCallId $id;

    public function __construct(
        private string $name,
        private array $arguments = [],
        ToolCallId|string|null $id = null,
    ) {
        $this->id = self::normalizeId($id);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: self::nameFrom($data),
            arguments: self::argumentsFrom($data),
            id: self::idFrom($data),
        );
    }

    public static function none(): self
    {
        return new self(name: '(no-tool)');
    }

    // ACCESSORS ///////////////////////////////////////////////////

    public function id(): ?ToolCallId
    {
        return $this->id;
    }

    public function idString(): string
    {
        return $this->id?->toString() ?? '';
    }

    public function name(): string
    {
        return $this->name;
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    /** Alias for arguments() — polyglot compatibility */
    public function args(): array
    {
        return $this->arguments;
    }

    public function argumentsAsJson(): string
    {
        return Json::encode($this->arguments);
    }

    /** Alias for argumentsAsJson() — polyglot compatibility */
    public function argsAsJson(): string
    {
        return Json::encode($this->arguments);
    }

    public function hasArgs(): bool
    {
        return !empty($this->arguments);
    }

    public function hasValue(string $key): bool
    {
        return isset($this->arguments[$key]);
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }

    // MUTATORS ////////////////////////////////////////////////////

    public function with(
        ToolCallId|string|null $id = null,
        ?string $name = null,
        ?array $args = null,
    ): self {
        return new self(
            name: $name ?? $this->name,
            arguments: $args ?? $this->arguments,
            id: self::normalizeId($id ?? $this->id),
        );
    }

    public function withId(string|ToolCallId $id): self
    {
        return new self(
            name: $this->name,
            arguments: $this->arguments,
            id: is_string($id) ? new ToolCallId($id) : $id,
        );
    }

    public function withName(string $name): self
    {
        return new self(
            name: $name,
            arguments: $this->arguments,
            id: $this->id,
        );
    }

    public function withArguments(string|array $args): self
    {
        return new self(
            name: $this->name,
            arguments: is_array($args) ? $args : Json::fromString($args)->toArray(),
            id: $this->id,
        );
    }

    /** Alias for withArguments() — polyglot compatibility */
    public function withArgs(string|array $args): self
    {
        return $this->withArguments($args);
    }

    // SERIALIZATION ////////////////////////////////////////////////

    public function toArray(): array
    {
        return [
            'id' => $this->id?->toNullableString(),
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }

    public function toString(): string
    {
        if ($this->arguments === []) {
            return $this->name . '()';
        }

        $parts = [];
        foreach ($this->arguments as $key => $value) {
            $parts[] = $key . '=' . self::stringify($value);
        }

        return $this->name . '(' . implode(', ', $parts) . ')';
    }

    // INTERNAL ////////////////////////////////////////////////////

    private static function nameFrom(array $data): string
    {
        // Canonical format: 'name', OpenAI format: 'function.name'
        $name = $data['name'] ?? $data['function']['name'] ?? '(unnamed-tool)';

        return match (true) {
            is_string($name) => $name,
            default => throw new InvalidArgumentException('ToolCall name must be a string.'),
        };
    }

    private static function argumentsFrom(array $data): array
    {
        // Canonical format: 'arguments'/'args', OpenAI format: 'function.arguments'
        $arguments = $data['arguments'] ?? $data['args'] ?? $data['function']['arguments'] ?? [];

        return match (true) {
            is_array($arguments) => $arguments,
            is_string($arguments) => Json::fromString($arguments)->toArray(),
            $arguments === null => [],
            default => throw new InvalidArgumentException('ToolCall arguments must be an array, JSON string, or null.'),
        };
    }

    private static function idFrom(array $data): ?ToolCallId
    {
        $id = $data['id'] ?? null;

        return match (true) {
            $id === null => null,
            is_string($id) && $id !== '' => new ToolCallId($id),
            $id instanceof ToolCallId => $id,
            default => null,
        };
    }

    private static function normalizeId(ToolCallId|string|null $id): ?ToolCallId
    {
        return match (true) {
            $id === null => null,
            $id instanceof ToolCallId => $id,
            is_string($id) && $id !== '' => new ToolCallId($id),
            default => null,
        };
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            default => Json::encode($value),
        };
    }
}
