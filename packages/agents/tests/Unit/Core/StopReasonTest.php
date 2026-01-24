<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Agents\Agent\Continuation\StopReason;

it('defines all stop reasons', function () {
    $values = array_map(
        static fn(StopReason $reason): string => $reason->value,
        StopReason::cases(),
    );

    expect(count($values))->toBe(9);
    expect($values)->toBe([
        'completed',
        'steps_limit',
        'token_limit',
        'time_limit',
        'retry_limit',
        'error',
        'finish_reason',
        'guard',
        'user_requested',
    ]);
});
