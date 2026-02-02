<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Stop;

use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;

it('roundtrips stop signals through arrays', function () {
    $signal = new StopSignal(
        reason: StopReason::StepsLimitReached,
        message: 'Steps limit reached',
        context: ['currentSteps' => 10],
        source: 'Guard',
    );

    $restored = StopSignal::fromArray($signal->toArray());

    expect($restored->reason)->toBe(StopReason::StepsLimitReached)
        ->and($restored->message)->toBe('Steps limit reached')
        ->and($restored->context)->toBe(['currentSteps' => 10])
        ->and($restored->source)->toBe('Guard');
});

it('orders signals by priority', function () {
    $low = new StopSignal(StopReason::Completed);
    $high = new StopSignal(StopReason::ErrorForbade);

    expect($high->compare($low))->toBeLessThan(0);
});
