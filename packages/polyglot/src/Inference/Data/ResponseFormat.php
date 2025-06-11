<?php

namespace Cognesy\Polyglot\Inference\Data;

use Closure;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class ResponseFormat
{
    private ?string $name;
    private ?bool $strict;
    private ?string $type;
    private ?array $schema;

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

    public static function fromResponseFormat(array $responseFormat): self {
        return new self(
            $responseFormat['type'] ?? null,
            $responseFormat['name'] ?? $responseFormat['json_schema']['name'] ?? null,
            $responseFormat['strict'] ?? $responseFormat['json_schema']['strict'] ?? null,
            $responseFormat['schema'] ?? $responseFormat['json_schema']['schema'] ?? null,
        );
    }

    public function name(): ?string {
        return $this->name ?? 'schema';
    }

    public function strict(): ?bool {
        return $this->strict ?? true;
    }

    public function type(): ?string {
        return $this->type ?? 'text';
    }

    public function schema(): ?array {
        return $this->schema ?? [];
    }

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