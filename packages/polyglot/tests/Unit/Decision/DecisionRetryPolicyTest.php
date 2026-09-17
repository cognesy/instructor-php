<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Support\Retry\RetryAfter;
use Cognesy\Polyglot\Support\Retry\RetryJitter;

it('round-trips Decision retry policy fields and aliases', function () {
    $policy = DecisionRetryPolicy::fromArray([
        'max_attempts' => 4,
        'base_delay_ms' => 20,
        'max_delay_ms' => 900,
        'jitter' => 'none',
        'retry_on_status' => [429, 529],
        'retry_on_exceptions' => [RuntimeException::class],
        'respect_retry_after' => false,
    ]);

    expect($policy->maxAttempts)->toBe(4)
        ->and($policy->retryOnStatus)->toBe([429, 529])
        ->and($policy->retryOnExceptions)->toBe([RuntimeException::class])
        ->and($policy->respectRetryAfter)->toBeFalse()
        ->and($policy->jitterMode)->toBe(RetryJitter::None)
        ->and(DecisionRetryPolicy::fromArray($policy->toArray())->toArray())->toBe($policy->toArray());
});

it('honors delta-seconds and HTTP-date Retry-After values within the delay cap', function () {
    $now = 1_800_000_000;
    $date = gmdate('D, d M Y H:i:s \\G\\M\\T', $now + 4);
    $policy = new DecisionRetryPolicy(
        maxAttempts: 2,
        baseDelayMs: 100,
        maxDelayMs: 5000,
        jitter: 'none',
    );

    expect($policy->delayMsForAttempt(new DecisionRateLimitException('rate', 429, '3'), 1, $now))->toBe(3000)
        ->and($policy->delayMsForAttempt(new DecisionRateLimitException('rate', 429, $date), 1, $now))->toBe(4000)
        ->and($policy->delayMsForAttempt(new DecisionRateLimitException('rate', 429, '999999999999999999999'), 1, $now))->toBe(5000)
        ->and($policy->delayMsForAttempt(new DecisionRateLimitException('rate', 429, 'invalid'), 1, $now))->toBe(100);
});

it('can ignore Retry-After and rejects invalid retry policy values', function () {
    $policy = new DecisionRetryPolicy(
        maxAttempts: 2,
        baseDelayMs: 100,
        maxDelayMs: 5000,
        jitter: 'none',
        respectRetryAfter: false,
    );

    expect($policy->delayMsForAttempt(new DecisionRateLimitException('rate', 429, '3'), 1))->toBe(100)
        ->and($policy->delayMsForAttempt(new DecisionRateLimitException('rate', 429), PHP_INT_MAX))->toBe(5000)
        ->and(fn () => new DecisionRetryPolicy(maxAttempts: 0))->toThrow(InvalidArgumentException::class, 'Must be >= 1')
        ->and(fn () => new DecisionRetryPolicy(retryOnStatus: [99]))->toThrow(InvalidArgumentException::class, 'HTTP status')
        ->and(fn () => DecisionRetryPolicy::fromArray(['retry_on_status' => '429']))
        ->toThrow(InvalidArgumentException::class, 'expected array');
});

it('parses Retry-After without integer overflow and rejects noncanonical dates', function () {
    $now = 1_800_000_000;

    expect(RetryAfter::delayMs('0002', 5000, $now))->toBe(2000)
        ->and(RetryAfter::delayMs('0', 5000, $now))->toBe(0)
        ->and(RetryAfter::delayMs('Sunday, 15 Jan 2027 00:00:00 GMT', 5000, $now))->toBe(0)
        ->and(RetryAfter::delayMs(null, 5000, $now))->toBe(0);
});
