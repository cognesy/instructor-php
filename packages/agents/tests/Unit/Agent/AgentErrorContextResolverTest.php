<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Collections\ToolExecutions;
use Cognesy\Agents\Agent\Continuation\AgentErrorContextResolver;
use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Data\StepResult;
use Cognesy\Agents\Agent\ErrorHandling\ErrorType;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Exceptions\ProviderRateLimitException;
use Cognesy\Utils\Result\Result;

it('classifies tool errors', function () {
    $execution = new ToolExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new \RuntimeException('tool failed')),
        startedAt: new \DateTimeImmutable(),
        endedAt: new \DateTimeImmutable(),
    );
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
    );
    $stepResult = new StepResult(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
    );
    $state = AgentState::empty()
        ->recordStepResult($stepResult);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::Tool);
    expect($context->toolName)->toBe('tool');
    expect($context->consecutiveFailures)->toBe(1);
    expect($context->totalFailures)->toBe(1);
});

it('classifies rate limit errors', function () {
    $execution = new ToolExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new ProviderRateLimitException('rate limit')),
        startedAt: new \DateTimeImmutable(),
        endedAt: new \DateTimeImmutable(),
    );
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
    );
    $stepResult = new StepResult(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
    );
    $state = AgentState::empty()
        ->recordStepResult($stepResult);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::RateLimit);
});

it('classifies non-tool errors as model errors', function () {
    $step = new AgentStep(
        errors: [new \RuntimeException('model failed')],
    );
    $stepResult = new StepResult(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
    );
    $state = AgentState::empty()
        ->recordStepResult($stepResult);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::Model);
});
