<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\StructuredOutput;

use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * Global configuration for structured output extraction.
 *
 * These are defaults that apply to all extractions unless overridden
 * by per-schema config or tool call parameters.
 */
final readonly class StructuredOutputPolicy
{
    public function __construct(
        public ?string $llmPreset = null,
        public ?string $model = null,
        public int $defaultMaxRetries = 3,
        public ?OutputMode $outputMode = null,
        public ?string $systemPrompt = null,
        public bool $useMaybe = true,
    ) {}

    public function withLlmPreset(string $preset): self {
        return new self(
            llmPreset: $preset,
            model: $this->model,
            defaultMaxRetries: $this->defaultMaxRetries,
            outputMode: $this->outputMode,
            systemPrompt: $this->systemPrompt,
            useMaybe: $this->useMaybe,
        );
    }

    public function withModel(string $model): self {
        return new self(
            llmPreset: $this->llmPreset,
            model: $model,
            defaultMaxRetries: $this->defaultMaxRetries,
            outputMode: $this->outputMode,
            systemPrompt: $this->systemPrompt,
            useMaybe: $this->useMaybe,
        );
    }
}
