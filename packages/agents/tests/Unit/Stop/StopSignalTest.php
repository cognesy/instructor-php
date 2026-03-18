<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Stop;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Continuation\StopSignals;

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

it('creates a user-requested signal via factory', function () {
    $signal = StopSignal::userRequested('cancelled by user', ['key' => 'val'], 'MySource');

    expect($signal->reason)->toBe(StopReason::UserRequested)
        ->and($signal->message)->toBe('cancelled by user')
        ->and($signal->context)->toBe(['key' => 'val'])
        ->and($signal->source)->toBe('MySource');
});

it('creates a user-requested signal with defaults', function () {
    $signal = StopSignal::userRequested();

    expect($signal->reason)->toBe(StopReason::UserRequested)
        ->and($signal->message)->toBe('')
        ->and($signal->context)->toBe([])
        ->and($signal->source)->toBeNull();
});

it('orders signals by priority', function () {
    $low = new StopSignal(StopReason::Completed);
    $high = new StopSignal(StopReason::ErrorForbade);

    expect($high->compare($low))->toBeLessThan(0);
});

// ── StopSignals::highest() ─────────────────────────────────────

it('highest returns null for empty collection', function () {
    expect(StopSignals::empty()->highest())->toBeNull();
});

it('highest returns the only signal when collection has one', function () {
    $signal = new StopSignal(StopReason::StepsLimitReached);
    $signals = StopSignals::empty()->withSignal($signal);

    expect($signals->highest())->toBe($signal);
});

it('highest returns the most authoritative signal regardless of insertion order', function () {
    $stepsLimit = new StopSignal(StopReason::StepsLimitReached, 'steps');
    $userCancel = new StopSignal(StopReason::UserRequested, 'user');

    // Add steps-limit first, user-cancel second
    $signals = StopSignals::empty()
        ->withSignal($stepsLimit)
        ->withSignal($userCancel);

    // UserRequested (priority 2) beats StepsLimitReached (priority 3)
    expect($signals->highest()?->reason)->toBe(StopReason::UserRequested);
});

it('highest returns first when signals have equal priority', function () {
    $a = new StopSignal(StopReason::StepsLimitReached, 'first');
    $b = new StopSignal(StopReason::StepsLimitReached, 'second');

    $signals = StopSignals::empty()->withSignal($a)->withSignal($b);

    expect($signals->highest()?->message)->toBe('first');
});
