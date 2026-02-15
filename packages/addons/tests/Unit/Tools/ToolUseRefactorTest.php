<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Tools;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanExecuteToolCalls;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Result\Result;
use Tests\Addons\Support\FakeInferenceRequestDriver;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ErrorPresenceCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Addons\ToolUse\Continuation\ToolCallPresenceCheck;


function _sum(int $a, int $b): int { return $a + $b; }

it('continues loop on tool failure and formats error message', function () {
    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls(new ToolCall('_sum', ['a' => 2])) // missing required 'b'
        ),
    ]);

    $tools = new Tools(FunctionTool::fromCallable(_sum(...)));

    $state = (new ToolUseState())
        ->withMessages(Messages::fromString('Test failure handling'));

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(llm: LLMProvider::new()->withDriver($driver))
    );

    $state = $toolUse->nextStep($state);
    $step = $state->currentStep();

    expect($step?->toolExecutions()->hasErrors())->toBeTrue();
    $msgs = $state->messages()->toArray();

    $invocationNames = [];
    foreach ($msgs as $m) {
        $invocationNames[] = $m['_metadata']['tool_calls'][0]['function']['name'] ?? null;
    }

    expect($invocationNames)->toContain('_sum');

    $resultNames = [];
    foreach ($msgs as $m) {
        $resultNames[] = $m['_metadata']['tool_name'] ?? null;
    }
    expect($resultNames)->toContain('_sum');
});

it('converts driver exceptions into failure steps', function () {
    $toolUse = ToolUseFactory::default(tools: new Tools());

    $failingDriver = new class implements CanUseTools {
        public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep
        {
            throw new \RuntimeException('driver exception');
        }
    };

    $toolUse = $toolUse->withDriver($failingDriver);

    $state = new ToolUseState();
    $result = $toolUse->nextStep($state);

    expect($result->status())->toBe(ToolUseStatus::Failed);
    expect($result->currentStep()?->hasErrors())->toBeTrue();
    expect($result->currentStep()?->errorsAsString())->toContain('driver exception');
});

it('stops on configured finish reasons (FinishReasonCheck)', function () {
    $state = new ToolUseState();
    $resp = new InferenceResponse(content: '', finishReason: 'stop');
    $step = new ToolUseStep(inferenceResponse: $resp);
    $state = $state->withAddedStep($step);
    $state = $state->withCurrentStep($step);

    $check = new FinishReasonCheck([InferenceFinishReason::Stop], static fn(ToolUseState $s) => $s->currentStep()?->finishReason());
    // Matching finish reason triggers ForbidContinuation (hard stop)
    expect($check->evaluate($state)->decision)->toBe(ContinuationDecision::ForbidContinuation);
});

it('limits retries based on consecutive failed steps (RetryLimit)', function () {
    $state = new ToolUseState();
    // success step (no errors): empty tool executions
    $state = $state->withAddedStep(new ToolUseStep());
    // failed steps: emulate by creating ToolUseStep with error executions
    $failedExecs = new ToolExecutions(
        new ToolExecution(
            toolCall: new ToolCall('noop', []),
            result: Result::failure(new \Exception('x')),
            startedAt: new \DateTimeImmutable(),
            endedAt: new \DateTimeImmutable()
        )
    );
    $failedOutput = new Messages(Message::asTool(''));
    $failedStep1 = new ToolUseStep(outputMessages: $failedOutput, toolCalls: null, toolExecutions: $failedExecs);
    $failedStep2 = new ToolUseStep(outputMessages: $failedOutput, toolCalls: null, toolExecutions: $failedExecs);
    $state = $state->withAddedStep($failedStep1);
    $state = $state->withAddedStep($failedStep2);
    $state = $state->withCurrentStep($failedStep2);

    $limit = new RetryLimit(2, static fn(ToolUseState $s) => $s->steps()->all(), static fn(ToolUseStep $step) => $step->hasErrors());
    // tail failures == maxRetries => ForbidContinuation (hard stop)
    expect($limit->evaluate($state)->decision)->toBe(ContinuationDecision::ForbidContinuation);
});

it('stops after a failure because continuation outcome is recorded', function () {
    $driver = new class implements CanUseTools {
        private int $calls = 0;

        public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep {
            $this->calls++;
            if ($this->calls === 1) {
                return new ToolUseStep(
                    toolCalls: new ToolCalls(new ToolCall('_noop', []))
                );
            }
            throw new \RuntimeException('driver boom');
        }
    };

    $criteria = new ContinuationCriteria(
        new StepsLimit(5, static fn(ToolUseState $state): int => $state->stepCount()),
        new ErrorPresenceCheck(static fn(ToolUseState $state): bool => $state->currentStep()?->hasErrors() ?? false),
        new ToolCallPresenceCheck(
            static fn(ToolUseState $state): bool => $state->stepCount() === 0
                ? true
                : ($state->currentStep()?->hasToolCalls() ?? false)
        ),
    );

    $toolUse = ToolUseFactory::default(
        tools: new Tools(),
        continuationCriteria: $criteria,
        driver: $driver,
    );

    $state = new ToolUseState();
    $state = $toolUse->nextStep($state);
    $failedState = $toolUse->nextStep($state);

    expect($failedState->status())->toBe(ToolUseStatus::Failed);
    expect($toolUse->hasNextStep($failedState))->toBeFalse();
});

it('evaluates continuation before post-step processors run', function () {
    $driver = new class implements CanUseTools {
        public function useTools(ToolUseState $state, Tools $tools, CanExecuteToolCalls $executor): ToolUseStep {
            return new ToolUseStep(
                toolCalls: new ToolCalls(new ToolCall('_noop', []))
            );
        }
    };

    $criteria = new ContinuationCriteria(
        new StepsLimit(2, static fn(ToolUseState $state): int => $state->stepCount()),
        ContinuationCriteria::when(
            static fn(ToolUseState $state): ContinuationDecision => match (true) {
                $state->metadata()->get('stop') === true => ContinuationDecision::AllowStop,
                default => ContinuationDecision::RequestContinuation,
            }
        ),
    );

    $processor = new class implements CanProcessAnyState {
        public function canProcess(object $state): bool {
            return $state instanceof ToolUseState;
        }

        public function process(object $state, ?callable $next = null): object {
            $newState = $next ? $next($state) : $state;
            assert($newState instanceof ToolUseState);
            return $newState->withMetadata('stop', true);
        }
    };

    $toolUse = ToolUseFactory::default(
        tools: new Tools(),
        processors: new StateProcessors($processor),
        continuationCriteria: $criteria,
        driver: $driver,
    );

    $finalState = $toolUse->finalStep(new ToolUseState());

    expect($finalState->stepCount())->toBe(2);
});
