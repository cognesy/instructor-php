<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Continuation\AgentErrorContextResolver;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorType;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Exceptions\ProviderRateLimitException;
use Cognesy\Utils\Result\Result;

it('classifies tool errors', function () {
    $execution = new ToolExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new \RuntimeException('tool failed')),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
    );
    $stepId = 'step-1';
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
        id: $stepId,
    );
    $stepExecution = new StepExecution(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
        stepNumber: 1,
        id: $stepId,
    );
    $state = AgentState::empty()->recordStepExecution($stepExecution);

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
        completedAt: new \DateTimeImmutable(),
    );
    $stepId = 'step-1';
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
        id: $stepId,
    );
    $stepExecution = new StepExecution(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
        stepNumber: 1,
        id: $stepId,
    );
    $state = AgentState::empty()->recordStepExecution($stepExecution);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::RateLimit);
});

it('classifies non-tool errors as model errors', function () {
    $stepId = 'step-1';
    $step = new AgentStep(
        errors: new ErrorList(new \RuntimeException('model failed')),
        id: $stepId,
    );
    $stepExecution = new StepExecution(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
        stepNumber: 1,
        id: $stepId,
    );
    $state = AgentState::empty()->recordStepExecution($stepExecution);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::Model);
});
