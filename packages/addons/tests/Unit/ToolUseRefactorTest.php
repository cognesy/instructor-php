<?php declare(strict_types=1);

use Cognesy\Addons\Core\Continuation\Criteria\FinishReasonCheck;
use Cognesy\Addons\Core\Continuation\Criteria\RetryLimit;
use Cognesy\Addons\ToolUse\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Contracts\CanUseTools;
use Cognesy\Addons\ToolUse\Data\ToolExecution;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolExecutor;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Result\Result;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _sum(int $a, int $b): int { return $a + $b; }

it('continues loop on tool failure and formats error message', function () {
    $driver = new FakeInferenceDriver([
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

    // Debug: Let's see what's actually in the messages
    dump('Messages:', $msgs);

    $invocationNames = [];
    foreach ($msgs as $m) {
        $invocationNames[] = $m['_metadata']['tool_calls'][0]['function']['name'] ?? null;
    }

    // Debug: Let's see what invocation names we found
    dump('Invocation names:', $invocationNames);

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
        public function useTools(ToolUseState $state, Tools $tools, ToolExecutor $executor): ToolUseStep
        {
            throw new RuntimeException('driver exception');
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
    expect($check->canContinue($state))->toBeFalse();
});

it('limits retries based on consecutive failed steps (RetryLimit)', function () {
    $state = new ToolUseState();
    // success step (no errors): empty tool executions
    $state = $state->withAddedStep(new ToolUseStep());
    // failed steps: emulate by creating ToolUseStep with error executions
    $failedExecs = new ToolExecutions(
        new ToolExecution(
            toolCall: new ToolCall('noop', []),
            result: Result::failure(new Exception('x')),
            startedAt: new DateTimeImmutable(),
            endedAt: new DateTimeImmutable()
        )
    );
    $failedOutput = new Messages(Message::asTool(''));
    $failedStep1 = new ToolUseStep(outputMessages: $failedOutput, toolCalls: null, toolExecutions: $failedExecs);
    $failedStep2 = new ToolUseStep(outputMessages: $failedOutput, toolCalls: null, toolExecutions: $failedExecs);
    $state = $state->withAddedStep($failedStep1);
    $state = $state->withAddedStep($failedStep2);
    $state = $state->withCurrentStep($failedStep2);

    $limit = new RetryLimit(2, static fn(ToolUseState $s) => $s->steps()->all(), static fn(ToolUseStep $step) => $step->hasErrors());
    expect($limit->canContinue($state))->toBeFalse(); // tail failures == maxRetries => stop
});
