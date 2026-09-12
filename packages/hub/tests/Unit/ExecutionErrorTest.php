<?php

declare(strict_types=1);

use Cognesy\InstructorHub\Data\ExecutionError;

test('classifies explicit LLM API failures from child-process exception classes', function (
    string $exception,
    string $type,
    string $label,
): void {
    $error = ExecutionError::fromOutput("PHP Fatal error: Uncaught {$exception}: failed", 255);

    expect($error->type)->toBe($type)
        ->and($error->statusLabel())->toBe($label)
        ->and(strlen($label))->toBeLessThanOrEqual(8)
        ->and($error->isLlmApiFailure())->toBeTrue();
})->with([
    'authentication' => [
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderAuthenticationException',
        'llm_authentication',
        'AUTH',
    ],
    'quota' => [
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderQuotaExceededException',
        'llm_quota',
        'QUOTA',
    ],
    'rate limit' => [
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderRateLimitException',
        'llm_rate_limit',
        'RATE',
    ],
    'network' => [
        'Cognesy\\Http\\Exceptions\\NetworkException',
        'llm_network',
        'NETWORK',
    ],
    'unavailable provider' => [
        'Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderTransientException',
        'llm_unavailable',
        'REMOTE',
    ],
]);

test('keeps a rejected LLM request distinct from an API failure', function (): void {
    $error = ExecutionError::fromOutput(
        'PHP Fatal error: Uncaught Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderInvalidRequestException: bad request',
        255,
    );

    expect($error->type)->toBe('llm_request_rejected')
        ->and($error->statusLabel())->toBe('REQUEST')
        ->and($error->isLlmApiFailure())->toBeFalse()
        ->and($error->typeDescription())->toBe('LLM API request rejected');
});

test('keeps non-provider failures in their existing categories', function (): void {
    $error = ExecutionError::fromOutput('PHP Fatal error: Uncaught InvalidArgumentException: invalid example', 255);

    expect($error->type)->toBe('fatal_error')
        ->and($error->statusLabel())->toBeNull()
        ->and($error->isLlmApiFailure())->toBeFalse();
});

test('upgrades legacy generic status data when its output has an explicit provider exception', function (): void {
    $error = ExecutionError::fromArray([
        'type' => 'fatal_error',
        'message' => 'old summary',
        'output' => 'PHP Fatal error: Uncaught Cognesy\\Polyglot\\Inference\\Exceptions\\ProviderQuotaExceededException: quota',
        'exitCode' => 255,
    ]);

    expect($error->type)->toBe('llm_quota')
        ->and($error->typeDescription())->toBe('LLM API quota');
});
