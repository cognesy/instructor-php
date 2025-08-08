<?php declare(strict_types=1);

use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

class BuilderTag implements TagInterface {
    public function __construct(public readonly string $operation) {}
}

describe('PipelineBuilder Enhanced Methods', function () {

    describe('map() method', function () {
        it('adds transformation processor', function () {
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)
                ->create()
                ->value();
            
            expect($result)->toBe(20);
        });

        it('chains multiple map operations', function () {
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)    // 20
                ->map(fn($x) => $x + 5)    // 25
                ->map(fn($x) => $x / 5)    // 5
                ->create()
                ->value();
            
            expect($result)->toBe(5);
        });

        it('is equivalent to through() method', function () {
            $mapResult = Pipeline::for(10)
                ->map(fn($x) => $x * 2)
                ->create()
                ->value();
            
            $throughResult = Pipeline::for(10)
                ->through(fn($x) => $x * 2)
                ->create()
                ->value();
            
            expect($mapResult)->toBe($throughResult);
        });
    });

    describe('flatMap() method', function () {
        it('flattens nested results', function () {
            $result = Pipeline::for(10)
                ->flatMap(fn($x) => $x * 2)
                ->create()
                ->value();
            
            expect($result)->toBe(20);
        });

        it('handles ProcessingState returns', function () {
            $result = Pipeline::for(10)
                ->flatMap(fn($x) => ProcessingState::with($x * 2, [new BuilderTag('flatmapped')]))
                ->create()
                ->state();
            
            expect($result->value())->toBe(20);
            expect($result->tags()->only(BuilderTag::class)->first()->operation)->toBe('flatmapped');
        });

        it('chains with other operations', function () {
            $result = Pipeline::for(5)
                ->map(fn($x) => $x * 2)        // 10
                ->flatMap(fn($x) => $x + 5)    // 15
                ->map(fn($x) => $x * 2)        // 30
                ->create()
                ->value();
            
            expect($result)->toBe(30);
        });

        it('merges tags from flatMapped ProcessingState', function () {
            $result = Pipeline::for(10)
                ->withTags(new BuilderTag('initial'))
                ->flatMap(fn($x) => ProcessingState::with($x * 2, [new BuilderTag('flatmapped')]))
                ->create()
                ->state();
            
            $tags = $result->allTags(BuilderTag::class);
            expect($tags)->toHaveCount(2);
            expect($tags[0]->operation)->toBe('initial');
            expect($tags[1]->operation)->toBe('flatmapped');
        });
    });

    describe('filter() method', function () {
        it('passes values that match predicate', function () {
            $result = Pipeline::for(10)
                ->filter(fn($x) => $x > 5)
                ->create()
                ->value();
            
            expect($result)->toBe(10);
        });

        it('stops pipeline when predicate fails', function () {
            $executed = false;
            
            $pending = Pipeline::for(10)
                ->filter(fn($x) => $x > 15)  // Will fail
                ->map(function($x) use (&$executed) {
                    $executed = true;
                    return $x * 2;
                })
                ->create();
            
            expect($pending->isFailure())->toBeTrue();
            expect($executed)->toBeFalse();
        });

        it('chains with other operations', function () {
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)      // 20
                ->filter(fn($x) => $x > 15)  // passes
                ->map(fn($x) => $x + 5)      // 25
                ->create()
                ->value();
            
            expect($result)->toBe(25);
        });

        it('preserves tags when filter passes', function () {
            $result = Pipeline::for(10)
                ->withTags(new BuilderTag('filtered'))
                ->filter(fn($x) => $x > 5)
                ->create()
                ->state();
            
            expect($result->tags()->only(BuilderTag::class)->first()->operation)->toBe('filtered');
        });
    });

    describe('monadic composition in builder', function () {
        it('combines map, filter, and flatMap', function () {
            $result = Pipeline::for(5)
                ->map(fn($x) => $x * 2)                    // 10
                ->filter(fn($x) => $x > 5)                 // passes
                ->flatMap(fn($x) => $x + 5)                // 15
                ->map(fn($x) => $x * 2)                    // 30
                ->create()
                ->value();
            
            expect($result)->toBe(30);
        });

        it('short-circuits on first filter failure', function () {
            $step1Executed = false;
            $step2Executed = false;
            $step3Executed = false;
            
            $pending = Pipeline::for(5)
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
                ->create();
            
            expect($pending->isFailure())->toBeTrue();
            expect($step1Executed)->toBeTrue();   // Executed before filter
            expect($step2Executed)->toBeFalse();  // Not executed after filter fails
            expect($step3Executed)->toBeFalse();  // Not executed after filter fails
        });

        it('maintains type safety through chain', function () {
            $result = Pipeline::for('hello')
                ->map(fn($s) => strtoupper($s))           // 'HELLO'
                ->filter(fn($s) => strlen($s) > 3)        // passes
                ->flatMap(fn($s) => $s . ' WORLD')        // 'HELLO WORLD'
                ->map(fn($s) => str_replace(' ', '_', $s)) // 'HELLO_WORLD'
                ->create()
                ->value();
            
            expect($result)->toBe('HELLO_WORLD');
        });
    });

    describe('integration with existing builder methods', function () {
        it('works with when() conditional', function () {
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)                           // 20
                ->when(fn($x) => $x > 15, fn($x) => $x + 10)      // 30
                ->filter(fn($x) => $x > 25)                       // passes
                ->create()
                ->value();
            
            expect($result)->toBe(30);
        });

        it('works with tap() side effects', function () {
            $sideEffect = null;
            
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)
                ->tap(function($value) use (&$sideEffect) {
                    $sideEffect = $value;
                })
                ->filter(fn($x) => $x > 15)
                ->create()
                ->value();
            
            expect($result)->toBe(20);
            expect($sideEffect)->toBe(20);
        });

        it('works with middleware', function () {
            $middlewareExecuted = false;
            
            $middleware = new class($middlewareExecuted) implements \Cognesy\Pipeline\Contracts\CanControlStateProcessing {
                public function __construct(private &$executed) {}
                
                public function handle(\Cognesy\Pipeline\ProcessingState $state, callable $next): \Cognesy\Pipeline\ProcessingState {
                    $this->executed = true;
                    return $next($state);
                }
            };
            
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)
                ->withMiddleware($middleware)
                ->filter(fn($x) => $x > 5)
                ->create()
                ->value();
            
            expect($result)->toBe(20);
            expect($middlewareExecuted)->toBeTrue();
        });
    });

    describe('error handling in builder chain', function () {
        it('handles exceptions in map', function () {
            $pending = Pipeline::for(10)
                ->map(fn($x) => throw new \RuntimeException('Map error'))
                ->filter(fn($x) => $x > 5)
                ->create();
            
            expect($pending->isFailure())->toBeTrue();
            expect($pending->exception()->getMessage())->toBe('Map error');
        });

        it('handles exceptions in flatMap', function () {
            $pending = Pipeline::for(10)
                ->flatMap(fn($x) => throw new \RuntimeException('FlatMap error'))
                ->map(fn($x) => $x * 2)
                ->create();
            
            expect($pending->isFailure())->toBeTrue();
            expect($pending->exception()->getMessage())->toBe('FlatMap error');
        });

        it('continues processing after successful filter', function () {
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)      // 20
                ->filter(fn($x) => $x > 5)   // passes
                ->map(fn($x) => $x + 5)      // 25
                ->filter(fn($x) => $x > 20)  // passes
                ->create()
                ->value();
            
            expect($result)->toBe(25);
        });

        it('stops processing after unsuccessful filter', function () {
            $result = Pipeline::for(10)
                ->map(fn($x) => $x * 2)      // 20
                ->filter(fn($x) => $x > 5)   // passes
                ->map(fn($x) => $x + 5)      // 25
                ->filter(fn($x) => $x > 30)  // fails
                ->create()
                ->result();

            expect($result->isFailure())->toBeTrue();
        });
    });
});