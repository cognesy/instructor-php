<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Hooks\ErrorPolicyHook;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use tmp\ErrorHandling\ErrorPolicy;

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

it('maps error policy decisions to stop signals or continuation requests', function (ErrorPolicy $policy, string $expected) {
    $hook = new ErrorPolicyHook($policy);
    $state = makeToolErrorState();

    $processed = $hook->process($state, HookType::AfterStep);

    $signal = $processed->pendingStopSignal();

    match ($expected) {
        'stop' => expect($signal?->reason)->toBe(StopReason::ErrorForbade),
        'retry' => expect($processed->continuationRequested())->toBeTrue(),
        'ignore' => expect($signal)->toBeNull()
            ->and($processed->continuationRequested())->toBeFalse(),
    };
})->with([
    [ErrorPolicy::stopOnAnyError(), 'stop'],
    [ErrorPolicy::retryToolErrors(3), 'retry'],
    [ErrorPolicy::ignoreToolErrors(), 'ignore'],
])->skip('hooks not integrated yet');

it('exposes error policy context in stop signal', function () {
    $hook = new ErrorPolicyHook(ErrorPolicy::stopOnAnyError());
    $state = makeToolErrorState();

    $processed = $hook->process($state, HookType::AfterStep);
    $signal = $processed->pendingStopSignal();

    expect($signal)->not->toBeNull();
    expect($signal?->context)->toMatchArray([
        'errorType' => 'tool',
        'consecutiveFailures' => 1,
        'totalFailures' => 1,
        'maxRetries' => 0,
        'handling' => 'stop',
        'toolName' => 'tool',
    ]);
})->skip('hooks not integrated yet');
