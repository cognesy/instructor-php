<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Agents\Agent\ErrorHandling\ErrorContext;
use Cognesy\Agents\Agent\ErrorHandling\ErrorHandlingDecision;
use Cognesy\Agents\Agent\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\Agent\ErrorHandling\ErrorType;

it('stops on any error with the default policy', function (ErrorType $type) {
    $policy = ErrorPolicy::stopOnAnyError();
    $context = new ErrorContext(
        type: $type,
        consecutiveFailures: 1,
        totalFailures: 1,
    );

    expect($policy->evaluate($context))->toBe(ErrorHandlingDecision::Stop);
})->with([
    [ErrorType::Tool],
    [ErrorType::Model],
    [ErrorType::Validation],
    [ErrorType::RateLimit],
    [ErrorType::Timeout],
    [ErrorType::Unknown],
]);

it('retries tool errors in retryToolErrors policy', function () {
    $policy = ErrorPolicy::retryToolErrors(3);
    $context = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 1,
        totalFailures: 1,
    );

    expect($policy->evaluate($context))->toBe(ErrorHandlingDecision::Retry);
});

it('respects max retries during evaluation', function () {
    $policy = ErrorPolicy::retryToolErrors(2);
    $first = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 1,
        totalFailures: 1,
    );
    $second = new ErrorContext(
        type: ErrorType::Tool,
        consecutiveFailures: 2,
        totalFailures: 2,
    );

    expect($policy->evaluate($first))->toBe(ErrorHandlingDecision::Retry);
    expect($policy->evaluate($second))->toBe(ErrorHandlingDecision::Stop);
});

it('applies fluent modifiers', function () {
    $policy = ErrorPolicy::stopOnAnyError()
        ->withMaxRetries(4)
        ->withToolErrorHandling(ErrorHandlingDecision::Retry);

    expect($policy->maxRetries)->toBe(4);
    expect($policy->onToolError)->toBe(ErrorHandlingDecision::Retry);
});
