<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\ContinuationCriteria\ErrorPresenceCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\FinishReasonCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\RetryLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ToolCallPresenceCheck;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\ToolUse\Data\Collections\StepProcessors;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\Events\ToolUseFinished;
use Cognesy\Addons\ToolUse\Events\ToolUseStepCompleted;
use Cognesy\Addons\ToolUse\Events\ToolUseStepStarted;
use Cognesy\Addons\ToolUse\Processors\AccumulateTokenUsage;
use Cognesy\Addons\ToolUse\Processors\AppendContextVariables;
use Cognesy\Addons\ToolUse\Processors\AppendToolStateMessages;
use Cognesy\Addons\ToolUse\Traits\ToolUse\HandlesMutation;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Events\Traits\HandlesEvents;
use Cognesy\Messages\Messages;
use Generator;

class ToolUse {
    use HandlesEvents;
    use HandlesMutation;

    private CanUseTools $driver;
    private Data\Collections\StepProcessors $processors;
    private Data\Collections\ContinuationCriteria $continuationCriteria;

    private ToolUseState $state;

    public function __construct(
        ?ToolUseState $state = null,
        ?StepProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->state = $state ?? new ToolUseState;
        $this->driver = $driver ?? new ToolCallingDriver;

        $this->processors = $processors ?? $this->defaultProcessors();
        $this->continuationCriteria = $continuationCriteria ?? $this->defaultContinuationCriteria();

        $this->state->tools()->withEventHandler($this->events);
    }

    // HANDLE PARAMETRIZATION //////////////////////////////////////

    public function driver() : CanUseTools {
        return $this->driver;
    }

    public function state() : ToolUseState {
        return $this->state;
    }

    public function tools() : Tools {
        return $this->state->tools();
    }

    public function messages() : Messages {
        return $this->state->messages();
    }

    // HANDLE TOOL USE /////////////////////////////////////////////

    public function nextStep() : ToolUseStep {
        $this->emitToolUseStepStarted($this->state);
        $step = $this->driver->useTools($this->state);
        $this->state = $this->state->withAddedStep($step)->withCurrentStep($step);
        $this->state = $this->processors->apply($this->state);
        $this->emitToolUseStepCompleted($this->state);
        return $step;
    }

    public function hasNextStep() : bool {
        if ($this->state->currentStep() === null) {
            return true;
        }
        return $this->canContinue($this->state);
    }

    public function finalStep() : ToolUseStep {
        while ($this->hasNextStep()) {
            $this->nextStep();
        }
        return $this->state->currentStep();
    }

    /** @return Generator<ToolUseStep> */
    public function iterator() : iterable {
        while ($this->hasNextStep()) {
            yield $this->nextStep();
        }
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseState $state) : bool {
        $can = $this->continuationCriteria->canContinue($state);
        if (!$can) {
            $state->withStatus(match(true) {
                $state->currentStep()?->hasErrors() => ToolUseStatus::Failed,
                default => ToolUseStatus::Completed,
            });
            $this->emitToolUseFinished($state);
        }
        return $can;
    }

    protected function defaultProcessors(): StepProcessors {
        return new StepProcessors(
            new AccumulateTokenUsage(),
            new AppendContextVariables(),
            new AppendToolStateMessages(),
        );
    }

    protected function defaultContinuationCriteria(
        int $maxSteps = 3,
        int $maxTokens = 8192,
        int $maxExecutionTime = 30,
        int $maxRetries = 3,
        array $finishReasons = [],
    ) : ContinuationCriteria {
        return new ContinuationCriteria(
            new StepsLimit($maxSteps),
            new TokenUsageLimit($maxTokens),
            new ExecutionTimeLimit($maxExecutionTime),
            new RetryLimit($maxRetries),
            new ErrorPresenceCheck(),
            new ToolCallPresenceCheck(),
            new FinishReasonCheck($finishReasons),
        );
    }

    private function emitToolUseFinished(ToolUseState $state) : void {
        $this->dispatch(new ToolUseFinished([
            'status' => $state->status()->value,
            'steps' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'errors' => $state->currentStep()?->errorsAsString(),
        ]));
    }

    private function emitToolUseStepStarted(ToolUseState $state) : void {
        $this->dispatch(new ToolUseStepStarted([
            'step' => $state->stepCount() + 1,
            'messages' => $state->messages()->count(),
            'tools' => count($state->tools()->nameList()),
        ]));
    }

    private function emitToolUseStepCompleted(ToolUseState $state) : void {
        $this->dispatch(new ToolUseStepCompleted([
            'step' => $state->stepCount(),
            'hasToolCalls' => $state->currentStep()?->hasToolCalls() ?? false,
            'errors' => count($state->currentStep()?->errors() ?? []),
            'usage' => $state->currentStep()?->usage()->toArray() ?? [],
            'finishReason' => $state->currentStep()?->finishReason()?->value ?? null,
        ]));
    }
}
