<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Continuation\AgentErrorContextResolver;
use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorType;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Exceptions\ProviderRateLimitException;
use Cognesy\Utils\Result\Result;

it('classifies tool errors', function () {
    $execution = new AgentExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new \RuntimeException('tool failed')),
        startedAt: new \DateTimeImmutable(),
        endedAt: new \DateTimeImmutable(),
    );
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
    );
    $state = AgentState::empty()
        ->withAddedStep($step)
        ->withCurrentStep($step);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::Tool);
    expect($context->toolName)->toBe('tool');
    expect($context->consecutiveFailures)->toBe(1);
    expect($context->totalFailures)->toBe(1);
});

it('classifies rate limit errors', function () {
    $execution = new AgentExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new ProviderRateLimitException('rate limit')),
        startedAt: new \DateTimeImmutable(),
        endedAt: new \DateTimeImmutable(),
    );
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
    );
    $state = AgentState::empty()
        ->withAddedStep($step)
        ->withCurrentStep($step);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::RateLimit);
});

it('classifies non-tool errors as model errors', function () {
    $step = new AgentStep(
        errors: [new \RuntimeException('model failed')],
    );
    $state = AgentState::empty()
        ->withAddedStep($step)
        ->withCurrentStep($step);

    $resolver = new AgentErrorContextResolver();
    $context = $resolver->resolve($state);

    expect($context->type)->toBe(ErrorType::Model);
});
