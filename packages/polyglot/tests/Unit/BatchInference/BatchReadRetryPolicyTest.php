<?php

declare(strict_types=1);

use Cognesy\Polyglot\BatchInference\Config\BatchReadRetryPolicy;

it('saturates retry delay without overflow or unbounded zero-base iteration', function () {
    $policy = new BatchReadRetryPolicy(PHP_INT_MAX, 3, 20);
    expect($policy->delayAfter(1))->toBe(3)
        ->and($policy->delayAfter(2))->toBe(6)
        ->and($policy->delayAfter(3))->toBe(12)
        ->and($policy->delayAfter(4))->toBe(20)
        ->and($policy->delayAfter(PHP_INT_MAX))->toBe(20)
        ->and((new BatchReadRetryPolicy(PHP_INT_MAX, 0, 20))->delayAfter(PHP_INT_MAX))->toBe(0);
});
