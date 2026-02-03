<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\Core\Stop\StopReason;

it('defines all stop reasons', function () {
    $values = array_map(
        static fn(StopReason $reason): string => $reason->value,
        StopReason::cases(),
    );

    expect(count($values))->toBe(10);
    expect($values)->toBe([
        'completed',
        'steps_limit',
        'token_limit',
        'time_limit',
        'retry_limit',
        'error',
        'stop_requested',
        'finish_reason',
        'user_requested',
        'unknown',
    ]);
});
