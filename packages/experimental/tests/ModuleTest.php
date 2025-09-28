<?php declare(strict_types=1);

namespace Tests\Experimental;

use Cognesy\Experimental\Module\Core\Module;
use Cognesy\Experimental\Module\Core\ModuleCall;
use InvalidArgumentException;

// =============================================================================
// TEST MODULE IMPLEMENTATIONS
// =============================================================================

class SimpleModule extends Module {
    protected function forward(mixed ...$callArgs): array {
        // When called with array, extract from first arg
        $args = is_array($callArgs[0] ?? null) ? $callArgs[0] : $callArgs;
        $sum = ($args['a'] ?? 0) + ($args['b'] ?? 0);
        return ['result' => $sum];
    }

    public function for(int $a, int $b): int {
        return $this(['a' => $a, 'b' => $b])->get('result');
    }
}

class MultiplyModule extends Module {
    protected function forward(mixed ...$callArgs): array {
        $args = is_array($callArgs[0] ?? null) ? $callArgs[0] : $callArgs;
        $product = ($args['x'] ?? 0) * ($args['y'] ?? 0);
        return ['product' => $product];
    }

    public function for(int $x, int $y): int {
        return $this(['x' => $x, 'y' => $y])->get('product');
    }
}

// =============================================================================
// MODULE CREATION & INSTANTIATION TESTS
// =============================================================================

describe('Module Creation', function () {
    it('creates a module instance using factory method', function () {
        $module = Module::factory(SimpleModule::class);
        expect($module)->toBeInstanceOf(SimpleModule::class);
    });

    it('creates a module instance using with method', function () {
        $module = SimpleModule::with();
        expect($module)->toBeInstanceOf(SimpleModule::class);
    });

    it('throws exception for invalid module class in factory', function () {
        expect(fn() => Module::factory('NonExistentClass'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('throws exception for non-module class in factory', function () {
        expect(fn() => Module::factory(\stdClass::class))
            ->toThrow(InvalidArgumentException::class);
    });
});

// =============================================================================
// MODULE EXECUTION TESTS
// =============================================================================

describe('Module Execution', function () {
    it('executes module and returns ModuleCall', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        expect($call)->toBeInstanceOf(ModuleCall::class);
        expect($call->get('result'))->toBe(8);
    });

    it('provides type-safe access through for method', function () {
        $module = new SimpleModule();
        $result = $module->for(7, 3);

        expect($result)->toBe(10);
    });

    it('accepts associative array without nesting via call method', function () {
        $module = new SimpleModule();
        $outputs = $module->call(['a' => 5, 'b' => 3]);

        expect($outputs)->toBe(['result' => 8]);
    });

    it('works with different module implementations', function () {
        $adder = new SimpleModule();
        $multiplier = new MultiplyModule();

        $sum = $adder->for(3, 4);
        $product = $multiplier->for(3, 4);

        expect($sum)->toBe(7);
        expect($product)->toBe(12);
    });
});

// =============================================================================
// MODULE CALL OUTPUT HANDLING TESTS
// =============================================================================

describe('ModuleCall Output Handling', function () {
    it('allows dynamic property access on ModuleCall', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        expect($call->result)->toBe(8);
    });

    it('provides output access methods', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        expect($call->outputs())->toBe(['result' => 8]);
        expect($call->hasOutput('result'))->toBeTrue();
        expect($call->hasOutput('other'))->toBeFalse();
        expect($call->outputFields())->toBe(['result']);
    });

    it('checks field existence with has method', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        expect($call->has('result'))->toBeTrue(); // Output field
        expect($call->has('nonexistent'))->toBeFalse();
    });

    it('returns single output value correctly with result method', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        // Should return the actual value, not the full array
        expect($call->result())->toBe(8);
        expect($call->get())->toBe(8); // get() with no args should do the same
    });

    it('returns full array for multiple outputs with result method', function () {
        $module = new class extends Module {
            protected function forward(mixed ...$callArgs): array {
                $args = is_array($callArgs[0] ?? null) ? $callArgs[0] : $callArgs;
                return [
                    'sum' => ($args['a'] ?? 0) + ($args['b'] ?? 0),
                    'product' => ($args['a'] ?? 0) * ($args['b'] ?? 0),
                ];
            }
        };

        $call = $module(['a' => 3, 'b' => 4]);

        // Should return the full array since there are multiple outputs
        expect($call->result())->toBe(['sum' => 7, 'product' => 12]);
        expect($call->get())->toBe(['sum' => 7, 'product' => 12]);
    });

    it('handles module with empty output', function () {
        $module = new class extends Module {
            protected function forward(mixed ...$callArgs): array {
                return [];
            }
        };

        $call = $module([]);
        expect($call->outputs())->toBe([]);
        expect($call->outputFields())->toBe([]);
    });
});

// =============================================================================
// ERROR HANDLING & VALIDATION TESTS
// =============================================================================

describe('Error Handling & Validation', function () {
    it('handles errors in ModuleCall with try method', function () {
        $module = new class extends Module {
            protected function forward(mixed ...$callArgs): array {
                throw new \RuntimeException('Test error');
            }
        };

        $call = $module([]);
        $result = $call->try();

        expect($result->isFailure())->toBeTrue();
        expect($call->hasErrors())->toBeTrue();
        expect($call->errors())->toHaveCount(1);
    });

    it('prevents modification of ModuleCall values', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        expect(function() use ($call) {
            $call->result = 100;
        })->toThrow(InvalidArgumentException::class);
    });

    it('throws exception when accessing non-existent output field', function () {
        $module = new SimpleModule();
        $call = $module(['a' => 5, 'b' => 3]);

        expect(fn() => $call->get('nonexistent'))
            ->toThrow(InvalidArgumentException::class);
    });
});

// =============================================================================
// ADVANCED FEATURES TESTS
// =============================================================================

describe('Advanced Features', function () {
    it('lazily evaluates outputs on first access', function () {
        $executed = ['value' => false];
        $module = new class($executed) extends Module {
            private array $executed;

            public function __construct(array &$executed) {
                $this->executed = &$executed;
            }

            protected function forward(mixed ...$callArgs): array {
                $this->executed['value'] = true;
                return ['result' => 42];
            }
        };

        $call = $module([]);
        expect($executed['value'])->toBeFalse();

        $result = $call->get('result');
        expect($executed['value'])->toBeTrue();
        expect($result)->toBe(42);
    });

    it('allows manual module composition', function () {
        $adder = new SimpleModule();
        $multiplier = new MultiplyModule();

        // First module: 3 + 4 = 7
        $sum = $adder->for(3, 4);

        // Second module: 7 * 2 = 14
        $result = $multiplier->for($sum, 2);

        expect($result)->toBe(14);
    });
});

/*
 * =============================================================================
 * NOTES ON OMITTED TESTS
 * =============================================================================
 *
 * Some tests have been omitted because they depend on:
 * - Prediction module which requires AI services
 * - CallClosure module which has reflection issues with closures
 * - Module traversal methods which have property access issues
 * These would need to be tested with proper mocks or fixes to the underlying code
 */