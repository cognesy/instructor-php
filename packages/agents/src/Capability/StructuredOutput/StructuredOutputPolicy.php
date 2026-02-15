<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

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

    public function with(
        ?string $llmPreset = null,
        ?string $model = null,
        ?int $defaultMaxRetries = null,
        ?OutputMode $outputMode = null,
        ?string $systemPrompt = null,
        ?bool $useMaybe = null,
    ): self {
        return new self(
            llmPreset: $llmPreset ?? $this->llmPreset,
            model: $model ?? $this->model,
            defaultMaxRetries: $defaultMaxRetries ?? $this->defaultMaxRetries,
            outputMode: $outputMode ?? $this->outputMode,
            systemPrompt: $systemPrompt ?? $this->systemPrompt,
            useMaybe: $useMaybe ?? $this->useMaybe,
        );
    }

    public function withLlmPreset(string $preset): self {
        return $this->with(llmPreset: $preset);
    }

    public function withModel(string $model): self {
        return $this->with(model: $model);
    }

    public function applyTo(StructuredOutput $instructor): void {
        if ($this->llmPreset !== null) { $instructor->using($this->llmPreset); }
        if ($this->model !== null) { $instructor->withModel($this->model); }
        if ($this->outputMode !== null) { $instructor->withOutputMode($this->outputMode); }
        if ($this->systemPrompt !== null) { $instructor->withSystem($this->systemPrompt); }
    }
}
