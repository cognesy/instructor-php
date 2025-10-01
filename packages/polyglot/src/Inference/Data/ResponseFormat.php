<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Closure;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class ResponseFormat
{
    private ?string $name;
    private ?array $schema;
    private ?string $type;
    private ?bool $strict;

    private ?Closure $toTextHandler = null;
    private ?Closure $toJsonObjectHandler = null;
    private ?Closure $toJsonSchemaHandler = null;

    public function __construct(
        ?string $type = null,
        ?array $schema = null,
        ?string $name = null,
        ?bool $strict = null,
    ) {
        $this->type = $type;
        $this->schema = $schema;
        $this->name = $name;
        $this->strict = $strict;
    }

    // CONSTRUCTORS ///////////////////////////////////////////////////

    public static function fromData(array $data): self {
        return new self(
            type: $data['type'] ?? null,
            schema: $data['schema'] ?? $data['json_schema']['schema'] ?? null,
            name: $data['name'] ?? $data['json_schema']['name'] ?? null,
            strict: $data['strict'] ?? $data['json_schema']['strict'] ?? null,
        );
    }

    public static function empty(): self {
        return new self();
    }

    // ACCESSORS //////////////////////////////////////////////////////

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

    public function schemaFilteredWith(callable $filter) : array {
        return $filter($this->schema());
    }

    public function isEmpty() : bool {
        return is_null($this->type)
            && is_null($this->schema)
            && is_null($this->name)
            && is_null($this->strict);
    }

    // TRANSFORMATION AND CONVERSION //////////////////////////////////

    public function as(OutputMode $mode): array {
        return match ($mode) {
            OutputMode::Json => $this->asJsonObject(),
            OutputMode::JsonSchema => $this->asJsonSchema(),
            OutputMode::Text,
            OutputMode::MdJson => $this->asText(),
            OutputMode::Tools,
            OutputMode::Unrestricted => $this->asText(),
        };
    }

    public function asText(): array {
        return match(true) {
            is_null($this->toTextHandler) => $this->defaultAsText(),
            default => ($this->toTextHandler)(),
        };
    }

    public function asJsonObject(): array {
        return match(true) {
            is_null($this->toJsonObjectHandler) => $this->defaultAsJsonObject(),
            default => ($this->toJsonObjectHandler)(),
        };
    }

    public function asJsonSchema(): array {
        return match(true) {
            is_null($this->toJsonSchemaHandler) => $this->defaultAsJsonSchema(),
            default => ($this->toJsonSchemaHandler)(),
        };
    }

    // MUTATORS ///////////////////////////////////////////////////////

    public function withToTextHandler(Closure $callback): self {
        $this->toTextHandler = $callback;
        return $this;
    }

    public function withToJsonObjectHandler(Closure $callback): self {
        $this->toJsonObjectHandler = $callback;
        return $this;
    }

    public function withToJsonSchemaHandler(Closure $callback): self {
        $this->toJsonSchemaHandler = $callback;
        return $this;
    }

    // SERIALIZATION //////////////////////////////////////////////////

    // INTERNAL //////////////////////////////////////////////////////

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
                'name' => $this->name ?? 'schema',
                'schema' => $this->schema ?? [],
                'strict' => $this->strict ?? true,
            ]),
        ];
    }

    private function defaultAsUnrestricted(): array {
        return match($this->type) {
            'json_object' => $this->asJsonObject(),
            'json_schema' => $this->asJsonSchema(),
            default => $this->asText(),
        };
    }

    protected function filterEmptyValues(array $data) : array {
        return array_filter($data, fn($value) => $value !== null && $value !== [] && $value !== '');
    }
}