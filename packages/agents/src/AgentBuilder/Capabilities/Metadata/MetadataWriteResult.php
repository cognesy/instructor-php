<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Metadata;

use Cognesy\Utils\Json\Json;

/**
 * Result object returned by MetadataWriteTool.
 * Processor inspects this to persist data to agent state.
 */
final readonly class MetadataWriteResult
{
    public function __construct(
        public bool $success,
        public string $key,
        public mixed $value,
        public ?string $error = null,
    ) {}

    public function toArray(): array {
        return [
            'success' => $this->success,
            'key' => $this->key,
            'value' => $this->value,
            'error' => $this->error,
        ];
    }

    public function __toString(): string {
        if (!$this->success) {
            return "Failed to store metadata: {$this->error}";
        }

        $preview = match (true) {
            is_string($this->value) => strlen($this->value) > 50
                ? substr($this->value, 0, 50) . '...'
                : $this->value,
            is_array($this->value), is_object($this->value) => Json::encode($this->value),
            default => (string) $this->value,
        };

        if (strlen($preview) > 100) {
            $preview = substr($preview, 0, 100) . '...';
        }

        return "Stored '{$this->key}': {$preview}";
    }
}
