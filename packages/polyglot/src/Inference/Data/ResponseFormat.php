<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Closure;

readonly final class ResponseFormat
{
    /**
     * @param (Closure(): array)|null $toTextHandler
     * @param (Closure(): array)|null $toJsonObjectHandler
     * @param (Closure(): array)|null $toJsonSchemaHandler
     */
    public function __construct(
        private ?string $type = null,
        private ?array $schema = null,
        private ?string $name = null,
        private ?bool $strict = null,
        private ?Closure $toTextHandler = null,
        private ?Closure $toJsonObjectHandler = null,
        private ?Closure $toJsonSchemaHandler = null,
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

    public function asText(): array {
        return match (true) {
            $this->toTextHandler === null => $this->defaultAsText(),
            default => ($this->toTextHandler)(),
        };
    }

    public function asJsonObject(): array {
        return match (true) {
            $this->toJsonObjectHandler === null => $this->defaultAsJsonObject(),
            default => ($this->toJsonObjectHandler)(),
        };
    }

    public function asJsonSchema(): array {
        return match (true) {
            $this->toJsonSchemaHandler === null => $this->defaultAsJsonSchema(),
            default => ($this->toJsonSchemaHandler)(),
        };
    }

    /**
     * @param Closure(): array $callback
     */
    public function withToTextHandler(Closure $callback): self {
        return new self(
            type: $this->type,
            schema: $this->schema,
            name: $this->name,
            strict: $this->strict,
            toTextHandler: $callback,
            toJsonObjectHandler: $this->toJsonObjectHandler,
            toJsonSchemaHandler: $this->toJsonSchemaHandler,
        );
    }

    /**
     * @param Closure(): array $callback
     */
    public function withToJsonObjectHandler(Closure $callback): self {
        return new self(
            type: $this->type,
            schema: $this->schema,
            name: $this->name,
            strict: $this->strict,
            toTextHandler: $this->toTextHandler,
            toJsonObjectHandler: $callback,
            toJsonSchemaHandler: $this->toJsonSchemaHandler,
        );
    }

    /**
     * @param Closure(): array $callback
     */
    public function withToJsonSchemaHandler(Closure $callback): self {
        return new self(
            type: $this->type,
            schema: $this->schema,
            name: $this->name,
            strict: $this->strict,
            toTextHandler: $this->toTextHandler,
            toJsonObjectHandler: $this->toJsonObjectHandler,
            toJsonSchemaHandler: $callback,
        );
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

    private function defaultAsText(): array {
        return ['type' => 'text'];
    }

    private function defaultAsJsonObject(): array {
        return ['type' => 'json_object'];
    }

    private function defaultAsJsonSchema(): array {
        return [
            'type' => 'json_schema',
            'json_schema' => $this->filterEmptyValues([
                'name' => $this->schemaName(),
                'schema' => $this->schema(),
                'strict' => $this->strict(),
            ]),
        ];
    }

    private function filterEmptyValues(array $data) : array {
        return array_filter($data, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}
