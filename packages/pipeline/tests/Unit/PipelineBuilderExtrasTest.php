<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

class BuilderTag implements TagInterface {
    public function __construct(public readonly string $operation) {}
}

describe('PipelineBuilder Enhanced Methods', function () {

    describe('map() method', function () {
        it('adds transformation processor', function () {
            $result = Pipeline::builder()
                ->map(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($result)->toBe(20);
        });

        it('chains multiple map operations', function () {
            $result = Pipeline::builder()
                ->map(fn($x) => $x * 2)    // 20
                ->map(fn($x) => $x + 5)    // 25
                ->map(fn($x) => $x / 5)    // 5
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($result)->toBe(5);
        });

        it('is equivalent to through() method', function () {
            $mapResult = Pipeline::builder()
                ->map(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            $throughResult = Pipeline::builder()
                ->through(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($mapResult)->toBe($throughResult);
        });
    });

    describe('map() method', function () {
        it('flattens nested results', function () {
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(20);
        });

        it('handles ProcessingState returns', function () {
            $state = Pipeline::builder()
                ->map(fn($x) => ProcessingState::with($x * 2, [new BuilderTag('mapped')]))
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->state();
            
            expect($state->value())->toBe(20);
            expect($state->tags()->only(BuilderTag::class)->first()->operation)->toBe('mapped');
        });

        it('chains with other operations', function () {
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)        // 10
                ->map(fn($x) => $x + 5)        // 15
                ->map(fn($x) => $x * 2)        // 30
                ->create()
                ->executeWith(ProcessingState::with(5))
                ->value();
            
            expect($value)->toBe(30);
        });

        it('merges tags from mapped ProcessingState', function () {
            $state = Pipeline::builder()
                ->map(fn($x) => ProcessingState::with($x * 2, [new BuilderTag('mapped')]))
                ->create()
                ->executeWith(ProcessingState::with(10, [new BuilderTag('initial')]))
                ->state();
            
            $tags = $state->allTags(BuilderTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->operation)->toBe('initial');
            expect($tags[1]->operation)->toBe('mapped');
        });
    });

    describe('filter() method', function () {
        it('passes values that match predicate', function () {
            $value = Pipeline::builder()
                ->filter(fn($x) => $x > 5)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(10);
        });

        it('stops pipeline when predicate fails', function () {
            $executed = false;
            
            $pending = Pipeline::builder()
                ->filter(fn($x) => $x > 15)  // Will fail
                ->map(function($x) use (&$executed) {
                    $executed = true;
                    return $x * 2;
                })
                ->create()
                ->executeWith(ProcessingState::with(10));

            expect($pending->isFailure())->toBeTrue();
            expect($executed)->toBeFalse();
        });

        it('chains with other operations', function () {
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)      // 20
                ->filter(fn($x) => $x > 15)  // passes
                ->map(fn($x) => $x + 5)      // 25
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(25);
        });

        it('preserves tags when filter passes', function () {
            $state = Pipeline::builder()
                ->filter(fn($x) => $x > 5)
                ->create()
                ->executeWith(ProcessingState::with(10, [new BuilderTag('filtered')]))
                ->state();
            
            expect($state->tags()->only(BuilderTag::class)->first()->operation)->toBe('filtered');
        });
    });

    describe('monadic composition in builder', function () {
        it('combines map, filter, and map', function () {
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)                    // 10
                ->filter(fn($x) => $x > 5)                 // passes
                ->map(fn($x) => $x + 5)                // 15
                ->map(fn($x) => $x * 2)                    // 30
                ->create()
                ->executeWith(ProcessingState::with(5))
                ->value();
            
            expect($value)->toBe(30);
        });

        it('short-circuits on first filter failure', function () {
            $step1Executed = false;
            $step2Executed = false;
            $step3Executed = false;
            
            $pending = Pipeline::builder()
                ->map(function($x) use (&$step1Executed) {
                    $step1Executed = true;
                    return $x * 2;  // 10
                })
                ->filter(fn($x) => $x > 15)  // fails
                ->map(function($x) use (&$step2Executed) {
                    $step2Executed = true;
                    return $x + 5;
                })
                ->map(function($x) use (&$step3Executed) {
                    $step3Executed = true;
                    return $x * 2;
                })
                ->create()
                ->executeWith(ProcessingState::with(5));

            expect($pending->isFailure())->toBeTrue();
            expect($step1Executed)->toBeTrue();   // Executed before filter
            expect($step2Executed)->toBeFalse();  // Not executed after filter fails
            expect($step3Executed)->toBeFalse();  // Not executed after filter fails
        });

        it('maintains type safety through chain', function () {
            $value = Pipeline::builder()
                ->map(fn($s) => strtoupper($s))           // 'HELLO'
                ->filter(fn($s) => strlen($s) > 3)        // passes
                ->map(fn($s) => $s . ' WORLD')        // 'HELLO WORLD'
                ->map(fn($s) => str_replace(' ', '_', $s)) // 'HELLO_WORLD'
                ->create()
                ->executeWith(ProcessingState::with('hello'))
                ->value();
            
            expect($value)->toBe('HELLO_WORLD');
        });
    });

    describe('integration with existing builder methods', function () {
        it('works with when() conditional', function () {
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)                           // 20
                ->when(fn($x) => $x > 15, fn($x) => $x + 10)      // 30
                ->filter(fn($x) => $x > 25)                       // passes
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(30);
        });

        it('works with tap() side effects', function () {
            $sideEffect = null;
            
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)
                ->tap(function($value) use (&$sideEffect) {
                    $sideEffect = $value;
                })
                ->filter(fn($x) => $x > 15)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(20);
            expect($sideEffect)->toBe(20);
        });

        it('works with middleware', function () {
            $middlewareExecuted = false;
            
            $middleware = new class($middlewareExecuted) implements CanProcessState {
                public function __construct(private bool &$executed) {}
                
                public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
                    $this->executed = true;
                    return $next($state);
                }
            };
            
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)
                ->withOperator($middleware)
                ->filter(fn($x) => $x > 5)
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(20);
            expect($middlewareExecuted)->toBeTrue();
        });
    });

    describe('error handling in builder chain', function () {
        it('handles exceptions in map', function () {
            $pending = Pipeline::builder()
                ->map(fn($x) => throw new \RuntimeException('Map error'))
                ->filter(fn($x) => $x > 5)
                ->create()
                ->executeWith(ProcessingState::with(10));

            expect($pending->isFailure())->toBeTrue();
            expect($pending->exception()->getMessage())->toBe('Map error');
        });

        it('continues processing after successful filter', function () {
            $value = Pipeline::builder()
                ->map(fn($x) => $x * 2)      // 20
                ->filter(fn($x) => $x > 5)   // passes
                ->map(fn($x) => $x + 5)      // 25
                ->filter(fn($x) => $x > 20)  // passes
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->value();
            
            expect($value)->toBe(25);
        });

        it('stops processing after unsuccessful filter', function () {
            $result = Pipeline::builder()
                ->map(fn($x) => $x * 2)      // 20
                ->filter(fn($x) => $x > 5)   // passes
                ->map(fn($x) => $x + 5)      // 25
                ->filter(fn($x) => $x > 30)  // fails
                ->create()
                ->executeWith(ProcessingState::with(10))
                ->result();

            expect($result->isFailure())->toBeTrue();
        });
    });
});