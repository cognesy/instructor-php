<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;

final readonly class UseLLMConfig implements CanProvideAgentCapability
{
    public function __construct(
        private ?string $preset = null,
        private int $maxRetries = 1,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_llm_config';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $llm = match ($this->preset) {
            null => LLMProvider::new(),
            default => LLMProvider::using($this->preset),
        };

        $retryPolicy = match (true) {
            $this->maxRetries > 1 => new InferenceRetryPolicy(maxAttempts: $this->maxRetries),
            default => null,
        };

        return $agent->withToolUseDriver(
            new ToolCallingDriver(
                llm: $llm,
                retryPolicy: $retryPolicy,
                inference: (new Inference())->withLLMProvider($llm),
            )
        );
    }
}
