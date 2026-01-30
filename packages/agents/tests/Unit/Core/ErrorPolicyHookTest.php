<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Hooks\ErrorPolicyHook;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

function makeToolErrorState(): AgentState {
    $execution = new ToolExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new \RuntimeException('tool failed')),
        startedAt: new DateTimeImmutable(),
        completedAt: new DateTimeImmutable(),
    );

    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
    );

    return AgentState::empty()
        ->withNewStepExecution()
        ->withCurrentStep($step);
}

it('maps error policy decisions to continuation decisions', function (ErrorPolicy $policy, ContinuationDecision $expected) {
    $hook = new ErrorPolicyHook($policy);
    $state = makeToolErrorState();

    $processed = $hook->process($state, HookType::AfterStep);
    $evaluations = $processed->evaluations();

    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe($expected);
})->with([
    [ErrorPolicy::stopOnAnyError(), ContinuationDecision::ForbidContinuation],
    [ErrorPolicy::retryToolErrors(3), ContinuationDecision::RequestContinuation],
    [ErrorPolicy::ignoreToolErrors(), ContinuationDecision::AllowContinuation],
]);

it('exposes error policy context in evaluation', function () {
    $hook = new ErrorPolicyHook(ErrorPolicy::retryToolErrors(5));
    $state = makeToolErrorState();

    $processed = $hook->process($state, HookType::AfterStep);
    $evaluation = $processed->evaluations()[0] ?? null;

    expect($evaluation)->not->toBeNull();
    expect($evaluation?->context)->toMatchArray([
        'errorType' => 'tool',
        'consecutiveFailures' => 1,
        'totalFailures' => 1,
        'maxRetries' => 5,
        'handling' => 'retry',
        'toolName' => 'tool',
    ]);
});
