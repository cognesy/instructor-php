<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Drivers\ReAct\ReActDriver;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;

final readonly class UseReActConfig implements CanProvideAgentCapability
{
    public function __construct(
        private CanCreateInference $inference,
        private CanCreateStructuredOutput $structuredOutput,
        private ?string $preset = null,
        private string $model = '',
        private array $options = [],
        private bool $finalViaInference = false,
        private ?string $finalModel = null,
        private array $finalOptions = [],
        private int $maxRetries = 2,
        private OutputMode $mode = OutputMode::Json,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_react_config';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $llm = match ($this->preset) {
            null => LLMProvider::new(),
            default => LLMProvider::using($this->preset),
        };

        return $agent->withToolUseDriver(new ReActDriver(
            llm: $llm,
            model: $this->model,
            options: $this->options,
            finalViaInference: $this->finalViaInference,
            finalModel: $this->finalModel,
            finalOptions: $this->finalOptions,
            maxRetries: $this->maxRetries,
            mode: $this->mode,
            events: $agent->events(),
            inference: $this->inference,
            structuredOutput: $this->structuredOutput,
        ));
    }
}
