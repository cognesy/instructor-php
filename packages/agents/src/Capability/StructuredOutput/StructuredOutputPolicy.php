<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\StructuredOutput;

use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

final readonly class StructuredOutputPolicy
{
    public function __construct(
        public ?LLMProvider $llm = null,
        public ?string $model = null,
        public int $defaultMaxRetries = 3,
        public ?OutputMode $outputMode = null,
        public ?string $systemPrompt = null,
        public bool $useMaybe = true,
    ) {}

    public function with(
        ?LLMProvider $llm = null,
        ?string $model = null,
        ?int $defaultMaxRetries = null,
        ?OutputMode $outputMode = null,
        ?string $systemPrompt = null,
        ?bool $useMaybe = null,
    ): self {
        return new self(
            llm: $llm ?? $this->llm,
            model: $model ?? $this->model,
            defaultMaxRetries: $defaultMaxRetries ?? $this->defaultMaxRetries,
            outputMode: $outputMode ?? $this->outputMode,
            systemPrompt: $systemPrompt ?? $this->systemPrompt,
            useMaybe: $useMaybe ?? $this->useMaybe,
        );
    }

    public function withLLMProvider(LLMProvider $llm): self {
        return $this->with(llm: $llm);
    }

    public function withModel(string $model): self {
        return $this->with(model: $model);
    }

    public function provider(): LLMProvider {
        return $this->llm ?? LLMProvider::new();
    }

    public function withRequest(StructuredOutputRequest $request): StructuredOutputRequest {
        return $request->with(
            system: $this->systemPrompt,
            model: $this->model,
        );
    }

    public function withConfigBuilder(StructuredOutputConfigBuilder $configBuilder): StructuredOutputConfigBuilder {
        if ($this->outputMode === null) {
            return $configBuilder;
        }
        return $configBuilder->withOutputMode($this->outputMode);
    }
}
