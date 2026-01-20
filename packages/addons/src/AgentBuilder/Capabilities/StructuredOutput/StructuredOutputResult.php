<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Utils\Json\Json;

/**
 * Result of a structured output extraction.
 *
 * Contains the extracted data (or error), plus metadata for
 * the processor to persist to agent state.
 */
final readonly class StructuredOutputResult
{
    public function __construct(
        public bool $success,
        public string $schema,
        public mixed $data = null,
        public ?string $storeAs = null,
        public ?string $error = null,
    ) {}

    public static function success(string $schema, mixed $data, ?string $storeAs = null): self {
        return new self(
            success: true,
            schema: $schema,
            data: $data,
            storeAs: $storeAs,
        );
    }

    public static function failure(string $schema, string $error): self {
        return new self(
            success: false,
            schema: $schema,
            error: $error,
        );
    }

    public function toArray(): array {
        return [
            'success' => $this->success,
            'schema' => $this->schema,
            'data' => $this->data,
            'store_as' => $this->storeAs,
            'error' => $this->error,
        ];
    }

    public function __toString(): string {
        if (!$this->success) {
            return "Extraction failed ({$this->schema}): {$this->error}";
        }

        $preview = match (true) {
            is_object($this->data) => Json::encode($this->data),
            is_array($this->data) => Json::encode($this->data),
            default => (string) $this->data,
        };

        if (strlen($preview) > 200) {
            $preview = substr($preview, 0, 200) . '...';
        }

        $stored = $this->storeAs !== null
            ? " (stored as '{$this->storeAs}')"
            : '';

        return "Extracted {$this->schema}{$stored}: {$preview}";
    }
}
