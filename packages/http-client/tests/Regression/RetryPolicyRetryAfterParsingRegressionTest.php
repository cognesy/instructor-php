<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RetryPolicy;

it('uses numeric retry-after seconds when provided', function() {
    $policy = new RetryPolicy(
        baseDelayMs: 250,
        jitter: 'none',
        respectRetryAfter: true,
    );
    $response = HttpResponse::sync(429, ['Retry-After' => '2'], '');

    expect($policy->delayMsForAttempt(1, $response))->toBe(2000);
});

it('parses retry-after RFC7231 HTTP date values', function() {
    $policy = new RetryPolicy(
        baseDelayMs: 250,
        jitter: 'none',
        respectRetryAfter: true,
    );
    $httpDate = gmdate('D, d M Y H:i:s \G\M\T', time() + 2);
    $response = HttpResponse::sync(429, ['Retry-After' => $httpDate], '');

    $delay = $policy->delayMsForAttempt(1, $response);

    expect($delay)->toBeGreaterThanOrEqual(1000)
        ->and($delay)->toBeLessThanOrEqual(3000);
});

it('ignores invalid retry-after date strings', function() {
    $policy = new RetryPolicy(
        baseDelayMs: 250,
        jitter: 'none',
        respectRetryAfter: true,
    );
    $response = HttpResponse::sync(429, ['Retry-After' => '1 hour'], '');

    expect($policy->delayMsForAttempt(1, $response))->toBe(250);
});

it('caps very large numeric retry-after values at max delay', function() {
    $policy = new RetryPolicy(
        baseDelayMs: 250,
        maxDelayMs: 8000,
        jitter: 'none',
        respectRetryAfter: true,
    );
    $response = HttpResponse::sync(429, ['Retry-After' => '999999999999999999999999'], '');

    expect($policy->delayMsForAttempt(1, $response))->toBe(8000);
});

it('ignores negative and fractional numeric retry-after values', function() {
    $policy = new RetryPolicy(
        baseDelayMs: 250,
        jitter: 'none',
        respectRetryAfter: true,
    );

    expect($policy->delayMsForAttempt(1, HttpResponse::sync(429, ['Retry-After' => '-1'], '')))->toBe(250)
        ->and($policy->delayMsForAttempt(1, HttpResponse::sync(429, ['Retry-After' => '1.5'], '')))->toBe(250);
});
