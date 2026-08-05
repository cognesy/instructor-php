<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

/**
 * What the caller asked the provider to return: a type, and — for schema modes — the schema
 * and how strictly to apply it.
 *
 * Deliberately knows nothing about how any provider serialises that. It used to: three
 * injectable `Closure` handlers let a body format hand this object its own rendering, which
 * made every request allocate two closures and two copies of this object to reach a value the
 * body format already had everything to build. Provider variation now lives on the body
 * formats, as overridable methods — dispatch expressed as dispatch. See
 * `OpenAIBodyFormat::renderResponseFormatForType()`.
 */
readonly final class ResponseFormat
{
    public function __construct(
        private ?string $type = null,
        private ?array $schema = null,
        private ?string $name = null,
        private ?bool $strict = null,
    ) {}

    public static function empty(): self {
        return new self();
    }

    public static function text(): self {
        return new self(type: 'text');
    }

    public static function jsonObject(): self {
        return new self(type: 'json_object');
    }

    public static function jsonSchema(
        array $schema,
        string $name = 'schema',
        bool $strict = true,
    ): self {
        return new self(
            type: 'json_schema',
            schema: $schema,
            name: $name,
            strict: $strict,
        );
    }

    public function schemaName(): string {
        return $this->name ?? 'schema';
    }

    public function strict(): bool {
        return $this->strict ?? true;
    }

    public function type(): string {
        return $this->type ?? 'text';
    }

    public function schema(): array {
        return $this->schema ?? [];
    }

    /**
     * @param callable(array): array $filter
     */
    public function schemaFilteredWith(callable $filter) : array {
        return $filter($this->schema());
    }

    public function isEmpty() : bool {
        return $this->type === null
            && $this->schema === null
            && $this->name === null
            && $this->strict === null;
    }

    public function toArray(): array {
        if ($this->isEmpty()) {
            return [];
        }

        return $this->filterEmptyValues([
            'type' => $this->type,
            'schema' => $this->schema,
            'name' => $this->name,
            'strict' => $this->strict,
        ]);
    }

    public static function fromArray(array $data): self {
        return new self(
            type: $data['type'] ?? null,
            schema: $data['schema'] ?? $data['json_schema']['schema'] ?? null,
            name: $data['name'] ?? $data['json_schema']['name'] ?? null,
            strict: $data['strict'] ?? $data['json_schema']['strict'] ?? null,
        );
    }

    private function filterEmptyValues(array $data) : array {
        return array_filter($data, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}
