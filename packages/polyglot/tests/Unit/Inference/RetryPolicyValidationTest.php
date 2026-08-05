<?php declare(strict_types=1);

use Cognesy\Polyglot\Embeddings\Config\EmbeddingsRetryPolicy;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Config\LengthRecovery;
use Cognesy\Polyglot\Support\Retry\RetryBackoff;
use Cognesy\Polyglot\Support\Retry\RetryJitter;

final class RetryPolicyValidationThrowable extends RuntimeException {}

// ---- Invalid numeric values fail fast (previously reached random_int/usleep) ----

it('rejects a negative inference baseDelayMs at construction', function () {
    expect(fn() => new InferenceRetryPolicy(baseDelayMs: -1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a negative inference maxDelayMs at construction', function () {
    expect(fn() => new InferenceRetryPolicy(maxDelayMs: -1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a negative embeddings baseDelayMs at construction', function () {
    expect(fn() => new EmbeddingsRetryPolicy(baseDelayMs: -1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a negative embeddings maxDelayMs at construction', function () {
    expect(fn() => new EmbeddingsRetryPolicy(maxDelayMs: -1))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an inference maxAttempts below 1', function () {
    expect(fn() => new InferenceRetryPolicy(maxAttempts: 0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a malformed retryOnStatus list', function () {
    expect(fn() => new InferenceRetryPolicy(retryOnStatus: ['not-an-int']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a malformed retryOnExceptions list', function () {
    expect(fn() => new InferenceRetryPolicy(retryOnExceptions: ['NotAThrowableClass']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects present malformed serialized retry lists', function (string $field, mixed $value) {
    expect(fn() => InferenceRetryPolicy::fromArray([$field => $value]))
        ->toThrow(InvalidArgumentException::class, $field);
})->with([
    'camel status string' => ['retryOnStatus', 'not-a-list'],
    'camel status integer' => ['retryOnStatus', 429],
    'camel status object' => ['retryOnStatus', new stdClass()],
    'camel status null' => ['retryOnStatus', null],
    'snake status string' => ['retry_on_status', 'not-a-list'],
    'snake status integer' => ['retry_on_status', 429],
    'snake status object' => ['retry_on_status', new stdClass()],
    'snake status null' => ['retry_on_status', null],
    'camel exceptions string' => ['retryOnExceptions', 'not-a-list'],
    'camel exceptions integer' => ['retryOnExceptions', 1],
    'camel exceptions object' => ['retryOnExceptions', new stdClass()],
    'camel exceptions null' => ['retryOnExceptions', null],
    'snake exceptions string' => ['retry_on_exceptions', 'not-a-list'],
    'snake exceptions integer' => ['retry_on_exceptions', 1],
    'snake exceptions object' => ['retry_on_exceptions', new stdClass()],
    'snake exceptions null' => ['retry_on_exceptions', null],
]);

it('rejects resource values for serialized retry lists', function (string $field) {
    $resource = fopen('php://memory', 'r');
    expect($resource)->not->toBeFalse();

    try {
        expect(fn() => InferenceRetryPolicy::fromArray([$field => $resource]))
            ->toThrow(InvalidArgumentException::class, $field);
    } finally {
        fclose($resource);
    }
})->with(['retryOnStatus', 'retryOnExceptions', 'retry_on_status', 'retry_on_exceptions']);

it('rejects invalid HTTP status codes at both retry policy constructors', function (int $status) {
    expect(fn() => new InferenceRetryPolicy(retryOnStatus: [$status]))
        ->toThrow(InvalidArgumentException::class, 'retryOnStatus')
        ->and(fn() => new EmbeddingsRetryPolicy(retryOnStatus: [$status]))
        ->toThrow(InvalidArgumentException::class, 'retryOnStatus');
})->with([-1, 0, 99, 600]);

it('rejects malformed retry entries during serialized hydration', function (array $data, string $field) {
    expect(fn() => InferenceRetryPolicy::fromArray($data))
        ->toThrow(InvalidArgumentException::class, $field);
})->with([
    'status entry' => [['retryOnStatus' => ['429']], 'retryOnStatus'],
    'exception entry' => [['retryOnExceptions' => ['NotAThrowableClass']], 'retryOnExceptions'],
]);

it('keeps defaults when serialized retry lists are omitted', function () {
    $policy = InferenceRetryPolicy::fromArray([]);

    expect($policy->retryOnStatus)->toBe([408, 429, 500, 502, 503, 504])
        ->and($policy->retryOnExceptions)->not->toBeEmpty();
});

it('hydrates valid camel and snake retry lists', function () {
    $camel = InferenceRetryPolicy::fromArray([
        'retryOnStatus' => [418, 429],
        'retryOnExceptions' => [RetryPolicyValidationThrowable::class],
    ]);
    $snake = InferenceRetryPolicy::fromArray([
        'retry_on_status' => [500, 503],
        'retry_on_exceptions' => [RetryPolicyValidationThrowable::class],
    ]);

    expect($camel->retryOnStatus)->toBe([418, 429])
        ->and($camel->retryOnExceptions)->toBe([RetryPolicyValidationThrowable::class])
        ->and($snake->retryOnStatus)->toBe([500, 503])
        ->and($snake->retryOnExceptions)->toBe([RetryPolicyValidationThrowable::class]);
});

it('gives camel-case retry lists precedence over snake-case aliases', function () {
    $policy = InferenceRetryPolicy::fromArray([
        'retryOnStatus' => [418],
        'retry_on_status' => [503],
    ]);

    expect($policy->retryOnStatus)->toBe([418]);
});

it('rejects a malformed canonical retry list even when the snake alias is valid', function () {
    expect(fn() => InferenceRetryPolicy::fromArray([
        'retryOnStatus' => null,
        'retry_on_status' => [503],
    ]))->toThrow(InvalidArgumentException::class, 'retryOnStatus');
});

it('deliberately normalizes associative retry arrays to lists', function () {
    $policy = InferenceRetryPolicy::fromArray([
        'retryOnStatus' => ['rate-limit' => 429],
        'retryOnExceptions' => ['runtime' => RetryPolicyValidationThrowable::class],
    ]);

    expect($policy->retryOnStatus)->toBe([429])
        ->and($policy->retryOnExceptions)->toBe([RetryPolicyValidationThrowable::class]);
});

it('shares exception-list invariants with embeddings retry policies', function () {
    expect(fn() => new EmbeddingsRetryPolicy(retryOnExceptions: ['NotAThrowableClass']))
        ->toThrow(InvalidArgumentException::class, 'retryOnExceptions');

    $policy = new EmbeddingsRetryPolicy(
        retryOnExceptions: [RetryPolicyValidationThrowable::class],
    );
    expect($policy->retryOnExceptions)->toBe([RetryPolicyValidationThrowable::class]);
});

// ---- Unknown string modes are rejected, not silently defaulted ----

it('rejects an unknown inference jitter mode', function () {
    expect(fn() => new InferenceRetryPolicy(jitter: 'wobble'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown embeddings jitter mode', function () {
    expect(fn() => new EmbeddingsRetryPolicy(jitter: 'wobble'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown inference lengthRecovery mode', function () {
    expect(fn() => new InferenceRetryPolicy(lengthRecovery: 'bogus'))
        ->toThrow(InvalidArgumentException::class);
});

// ---- Valid modes resolve to typed enums and keep string compatibility ----

it('resolves valid string modes to typed enums while preserving string props', function () {
    $policy = new InferenceRetryPolicy(jitter: 'equal', lengthRecovery: 'continue');

    expect($policy->jitter)->toBe('equal')
        ->and($policy->jitterMode)->toBe(RetryJitter::Equal)
        ->and($policy->lengthRecovery)->toBe('continue')
        ->and($policy->lengthRecoveryMode)->toBe(LengthRecovery::Continue);
});

// ---- Serialization / hydration round-trips for valid values ----

it('round-trips inference retry policy through toArray/fromArray', function () {
    $policy = new InferenceRetryPolicy(
        maxAttempts: 5,
        baseDelayMs: 100,
        maxDelayMs: 4000,
        jitter: 'none',
        lengthRecovery: 'increase_max_tokens',
        lengthMaxAttempts: 2,
        maxTokensIncrement: 256,
    );

    $restored = InferenceRetryPolicy::fromArray($policy->toArray());

    expect($restored->maxAttempts)->toBe(5)
        ->and($restored->baseDelayMs)->toBe(100)
        ->and($restored->maxDelayMs)->toBe(4000)
        ->and($restored->jitter)->toBe('none')
        ->and($restored->jitterMode)->toBe(RetryJitter::None)
        ->and($restored->lengthRecovery)->toBe('increase_max_tokens')
        ->and($restored->lengthRecoveryMode)->toBe(LengthRecovery::IncreaseMaxTokens);
});

it('hydrates snake_case serialized policy data (backward compatibility)', function () {
    $policy = InferenceRetryPolicy::fromArray([
        'max_attempts' => 3,
        'base_delay_ms' => 50,
        'max_delay_ms' => 2000,
        'length_recovery' => 'continue',
    ]);

    expect($policy->maxAttempts)->toBe(3)
        ->and($policy->baseDelayMs)->toBe(50)
        ->and($policy->lengthRecoveryMode)->toBe(LengthRecovery::Continue);
});

// ---- Shared backoff: bounded, non-negative, deterministic for 'none' ----

it('computes a deterministic capped delay for the none jitter mode', function () {
    // base 100, attempt 3 => 100 * 2^2 = 400, under the 8000 cap
    expect(RetryBackoff::delayMs(3, 100, 8000, RetryJitter::None))->toBe(400);
    // exponential growth capped at maxDelayMs
    expect(RetryBackoff::delayMs(10, 100, 1000, RetryJitter::None))->toBe(1000);
});

it('keeps full and equal jitter delays within non-negative bounds', function (RetryJitter $mode) {
    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $delay = RetryBackoff::delayMs($attempt, 100, 2000, $mode);
        expect($delay)->toBeGreaterThanOrEqual(0)
            ->toBeLessThanOrEqual(2000);
    }
})->with([
    'full' => RetryJitter::Full,
    'equal' => RetryJitter::Equal,
]);

it('returns zero delay for a zero base without hitting random_int on invalid ranges', function () {
    expect(RetryBackoff::delayMs(1, 0, 0, RetryJitter::Full))->toBe(0)
        ->and(RetryBackoff::delayMs(3, 0, 8000, RetryJitter::Equal))->toBe(0);
});
