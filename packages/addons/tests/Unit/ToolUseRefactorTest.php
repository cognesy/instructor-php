<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\ContinuationCriteria\FinishReasonCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\RetryLimit;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../Support/FakeInferenceDriver.php';

function _sum(int $a, int $b): int { return $a + $b; }

it('continues loop on tool failure and formats error message', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls([ new ToolCall('_sum', ['a' => 2]) ]) // missing required 'b'
        ),
    ]);

    $tools = (new \Cognesy\Addons\ToolUse\Tools())
        ->withTool(FunctionTool::fromCallable(_sum(...)));
        
    $state = (new \Cognesy\Addons\ToolUse\Data\ToolUseState($tools))
        ->withMessages(\Cognesy\Messages\Messages::fromString('Test failure handling'));
        
    $toolUse = new ToolUse(
        state: $state,
        driver: new ToolCallingDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver))
    );

    $step = $toolUse->nextStep();

    expect($step->toolExecutions()->hasErrors())->toBeTrue();
    $msgs = $step->messages()->toArray();
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

it('stops on configured finish reasons (FinishReasonCheck)', function () {
    $state = new ToolUseState();
    $resp = new InferenceResponse(content: '', finishReason: 'stop');
    $step = new ToolUseStep('', null, null, null, null, $resp);
    $state = $state->withAddedStep($step);
    $state->withCurrentStep($step);

    $check = new FinishReasonCheck([InferenceFinishReason::Stop]);
    expect($check->canContinue($state))->toBeFalse();
});

it('limits retries based on consecutive failed steps (RetryLimit)', function () {
    $state = new ToolUseState();
    // success step (no errors): empty tool executions
    $state = $state->withAddedStep(new ToolUseStep());
    // failed steps: emulate by creating ToolUseStep with error executions
    $failedExecs = new \Cognesy\Addons\ToolUse\Data\Collections\ToolExecutions([
        new \Cognesy\Addons\ToolUse\Data\ToolExecution(
            new ToolCall('noop', []),
            \Cognesy\Utils\Result\Result::failure(new Exception('x')),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        )
    ]);
    $failedStep1 = new ToolUseStep('', null, $failedExecs);
    $failedStep2 = new ToolUseStep('', null, $failedExecs);
    $state = $state->withAddedStep($failedStep1);
    $state = $state->withAddedStep($failedStep2);
    $state = $state->withCurrentStep($failedStep2);

    $limit = new RetryLimit(2);
    expect($limit->canContinue($state))->toBeFalse(); // tail failures == maxRetries => stop
});
