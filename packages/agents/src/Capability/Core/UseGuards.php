<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Hooks\ExecutionTimeLimitHook;
use Cognesy\Agents\Hook\Hooks\FinishReasonHook;
use Cognesy\Agents\Hook\Hooks\StepsLimitHook;
use Cognesy\Agents\Hook\Hooks\TokenUsageLimitHook;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final readonly class UseGuards implements CanProvideAgentCapability
{
    /** @param list<InferenceFinishReason> $finishReasons */
    public function __construct(
        private ?int $maxSteps = 20,
        private ?int $maxTokens = 32768,
        private ?float $maxExecutionTime = 300.0,
        private array $finishReasons = [],
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_guards';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $hooks = $agent->hooks();

        if ($this->maxSteps !== null) {
            $hooks = $hooks->with(
                hook: new StepsLimitHook(
                    maxSteps: $this->maxSteps,
                    stepCounter: static fn($state) => $state->stepCount(),
                ),
                triggerTypes: HookTriggers::beforeStep(),
                priority: 200,
                name: 'guard:steps_limit',
            );
        }

        if ($this->maxTokens !== null) {
            $hooks = $hooks->with(
                hook: new TokenUsageLimitHook(maxTotalTokens: $this->maxTokens),
                triggerTypes: HookTriggers::beforeStep(),
                priority: 200,
                name: 'guard:token_limit',
            );
        }

        if ($this->maxExecutionTime !== null) {
            $hooks = $hooks->with(
                hook: new ExecutionTimeLimitHook(maxSeconds: $this->maxExecutionTime),
                triggerTypes: HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep),
                priority: 200,
                name: 'guard:time_limit',
            );
        }

        if ($this->finishReasons === []) {
            return $agent->withHooks($hooks);
        }

        $hooks = $hooks->with(
            hook: new FinishReasonHook(
                stopReasons: $this->finishReasons,
                finishReasonResolver: static fn($state) => $state->currentStep()?->finishReason(),
            ),
            triggerTypes: HookTriggers::afterStep(),
            priority: -200,
            name: 'guard:finish_reason',
        );
        return $agent->withHooks($hooks);
    }
}
