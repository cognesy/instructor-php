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

    public readonly Tools $tools;
    private CanUseTools $driver;
    private Data\Collections\StepProcessors $processors;
    private Data\Collections\ContinuationCriteria $continuationCriteria;

    public function __construct(
        ?Tools $tools = null,
        ?StepProcessors $processors = null,
        ?ContinuationCriteria $continuationCriteria = null,
        ?CanUseTools $driver = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
        $this->tools = $tools ?? new Tools();
        $this->driver = $driver ?? new ToolCallingDriver;
        
        $this->processors = $processors ?? $this->defaultProcessors();
        $this->continuationCriteria = $continuationCriteria ?? $this->defaultContinuationCriteria();
        
        $this->tools->withEventHandler($this->events);
    }

    // HANDLE PARAMETRIZATION //////////////////////////////////////

    public function driver() : CanUseTools {
        return $this->driver;
    }

    // Stateless - no internal state accessors

    // HANDLE TOOL USE /////////////////////////////////////////////

    public function nextStep(ToolUseState $state): ToolUseState {
        if (!$this->hasNextStep($state)) {
            $this->emitToolUseFinished($state);
            return $state;
        }
        
        $this->emitToolUseStepStarted($state);
        $step = $this->driver->useTools($state, $this->tools);
        $newState = $state->withAddedStep($step)->withCurrentStep($step);
        $newState = $this->processors->apply($newState);
        $this->emitToolUseStepCompleted($newState);
        return $newState;
    }

    public function hasNextStep(ToolUseState $state): bool {
        if ($state->currentStep() === null) {
            return true;
        }
        return $this->canContinue($state);
    }

    public function finalStep(ToolUseState $state): ToolUseState {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
        }
        return $state;
    }

    /** @return Generator<ToolUseState> */
    public function iterator(ToolUseState $state): iterable {
        while ($this->hasNextStep($state)) {
            $state = $this->nextStep($state);
            yield $state;
        }
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseState $state): bool {
        return $this->continuationCriteria->canContinue($state);
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
            'tools' => count($this->tools->nameList()),
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
