<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

it('keeps previously created pipelines isolated from later added steps', function () {
    $builder = Pipeline::builder()
        ->through(fn(int $x): int => $x + 1);

    $pipeline1 = $builder->create();

    $builder->through(fn(int $x): int => $x * 10);
    $pipeline2 = $builder->create();

    $result1 = $pipeline1->executeWith(ProcessingState::with(1))->value();
    $result2 = $pipeline2->executeWith(ProcessingState::with(1))->value();

    expect($result1)->toBe(2);
    expect($result2)->toBe(20);
});

it('keeps middleware hooks and finalizers isolated across create snapshots', function () {
    $events = [];

    $middlewareA = new class($events) implements CanProcessState {
        public function __construct(private array &$events) {}

        #[\Override]
        public function process(CanCarryState $state, ?callable $next = null): CanCarryState
        {
            $this->events[] = 'mwA';
            return $next ? $next($state) : $state;
        }
    };

    $middlewareB = new class($events) implements CanProcessState {
        public function __construct(private array &$events) {}

        #[\Override]
        public function process(CanCarryState $state, ?callable $next = null): CanCarryState
        {
            $this->events[] = 'mwB';
            return $next ? $next($state) : $state;
        }
    };

    $builder = Pipeline::builder()
        ->through(fn(int $x): int => $x + 1)
        ->withOperator($middlewareA)
        ->beforeEach(function (CanCarryState $state) use (&$events): CanCarryState {
            $events[] = 'hookA';
            return $state;
        })
        ->finally(function (CanCarryState $state) use (&$events): CanCarryState {
            $events[] = 'finA';
            return $state;
        });

    $pipeline1 = $builder->create();

    $builder
        ->withOperator($middlewareB)
        ->beforeEach(function (CanCarryState $state) use (&$events): CanCarryState {
            $events[] = 'hookB';
            return $state;
        })
        ->finally(function (CanCarryState $state) use (&$events): CanCarryState {
            $events[] = 'finB';
            return $state;
        });

    $pipeline2 = $builder->create();

    $events = [];
    $result1 = $pipeline1->executeWith(ProcessingState::with(1))->value();
    $pipeline1Events = $events;

    $events = [];
    $result2 = $pipeline2->executeWith(ProcessingState::with(1))->value();
    $pipeline2Events = $events;

    expect($result1)->toBe(2);
    expect($result2)->toBe(2);
    expect(in_array('mwB', $pipeline1Events, true))->toBeFalse();
    expect(in_array('hookB', $pipeline1Events, true))->toBeFalse();
    expect(in_array('finB', $pipeline1Events, true))->toBeFalse();
    expect(in_array('mwB', $pipeline2Events, true))->toBeTrue();
    expect(in_array('hookB', $pipeline2Events, true))->toBeTrue();
    expect(in_array('finB', $pipeline2Events, true))->toBeTrue();
});
