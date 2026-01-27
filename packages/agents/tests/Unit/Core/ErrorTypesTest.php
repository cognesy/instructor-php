<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\ErrorHandling\Data\ErrorContext;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorHandlingDecision;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorType;

it('defines all error types', function () {
    $values = array_map(
        static fn(ErrorType $type): string => $type->value,
        ErrorType::cases(),
    );

    expect(count($values))->toBe(6);
    expect($values)->toBe([
        'tool',
        'model',
        'validation',
        'rate_limit',
        'timeout',
        'unknown',
    ]);
});

it('defines all error handling decisions', function () {
    $values = array_map(
        static fn(ErrorHandlingDecision $decision): string => $decision->value,
        ErrorHandlingDecision::cases(),
    );

    expect($values)->toBe(['stop', 'retry', 'ignore']);
});

it('returns a zeroed error context', function () {
    $context = ErrorContext::none();

    expect($context->type)->toBe(ErrorType::Unknown);
    expect($context->consecutiveFailures)->toBe(0);
    expect($context->totalFailures)->toBe(0);
    expect($context->message)->toBeNull();
    expect($context->toolName)->toBeNull();
    expect($context->metadata)->toBe([]);
});
