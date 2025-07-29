<?php declare(strict_types=1);

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PendingPipelineExecution;
use Cognesy\Pipeline\StampInterface;
use Cognesy\Utils\Result\Result;

// Test stamp for unit testing
class PendingTestStamp implements StampInterface
{
    public function __construct(public readonly string $label) {}
}

describe('PendingPipelineExecution Unit Tests', function () {
    describe('Construction', function () {
        it('constructs with callable', function () {
            $pending = new PendingPipelineExecution(fn() => 'test');
            expect($pending)->toBeInstanceOf(PendingPipelineExecution::class);
        });

        it('does not execute computation on construction', function () {
            $executed = false;
            
            new PendingPipelineExecution(function() use (&$executed) {
                $executed = true;
                return 'test';
            });
            
            expect($executed)->toBeFalse();
        });
    });

    describe('Value Extraction', function () {
        it('extracts value from envelope result', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap(42);
            });
            
            expect($pending->payload())->toBe(42);
        });

        it('extracts value from direct Result', function () {
            $pending = new PendingPipelineExecution(function() {
                return Result::success('direct result');
            });
            
            expect($pending->payload())->toBe('direct result');
        });

        it('returns raw value for non-envelope/result', function () {
            $pending = new PendingPipelineExecution(function() {
                return ['raw' => 'data'];
            });
            
            expect($pending->payload())->toBe(['raw' => 'data']);
        });

        it('returns null for failed envelope', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap(Result::failure(new Exception('error')));
            });
            
            expect($pending->payload())->toBeNull();
        });
    });

    describe('Result Extraction', function () {
        it('extracts Result from envelope', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap('test data');
            });
            
            $result = $pending->result();
            expect($result)->toBeInstanceOf(Result::class);
            expect($result->unwrap())->toBe('test data');
            expect($result->isSuccess())->toBeTrue();
        });

        it('returns Result directly', function () {
            $originalResult = Result::success('direct');
            $pending = new PendingPipelineExecution(function() use ($originalResult) {
                return $originalResult;
            });
            
            expect($pending->result())->toBe($originalResult);
        });

        it('wraps non-result values in success Result', function () {
            $pending = new PendingPipelineExecution(function() {
                return 'wrapped';
            });
            
            $result = $pending->result();
            expect($result->unwrap())->toBe('wrapped');
            expect($result->isSuccess())->toBeTrue();
        });
    });

    describe('Envelope Extraction', function () {
        it('returns envelope directly', function () {
            $originalEnvelope = Envelope::wrap('test', [new PendingTestStamp('original')]);
            
            $pending = new PendingPipelineExecution(function() use ($originalEnvelope) {
                return $originalEnvelope;
            });
            
            $envelope = $pending->envelope();
            expect($envelope)->toBe($originalEnvelope);
            expect($envelope->has(PendingTestStamp::class))->toBeTrue();
        });

        it('wraps non-envelope results', function () {
            $pending = new PendingPipelineExecution(function() {
                return Result::success('to wrap');
            });
            
            $envelope = $pending->envelope();
            expect($envelope)->toBeInstanceOf(Envelope::class);
            expect($envelope->result()->unwrap())->toBe('to wrap');
        });
    });

    describe('Success/Failure Checking', function () {
        it('returns true for successful envelope', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap('success');
            });
            
            expect($pending->success())->toBeTrue();
        });

        it('returns false for failed envelope', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap(Result::failure(new Exception('failed')));
            });
            
            expect($pending->success())->toBeFalse();
        });

        it('returns true for successful Result', function () {
            $pending = new PendingPipelineExecution(function() {
                return Result::success('ok');
            });
            
            expect($pending->success())->toBeTrue();
        });

        it('returns false for failed Result', function () {
            $pending = new PendingPipelineExecution(function() {
                return Result::failure(new Exception('error'));
            });
            
            expect($pending->success())->toBeFalse();
        });

        it('returns false when computation throws', function () {
            $pending = new PendingPipelineExecution(function() {
                throw new Exception('computation error');
            });
            
            expect($pending->success())->toBeFalse();
        });
    });

    describe('Failure Extraction', function () {
        it('returns null for successful envelope', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap('success');
            });
            
            expect($pending->failure())->toBeNull();
        });

        it('returns error for failed envelope', function () {
            $error = new Exception('envelope error');
            $pending = new PendingPipelineExecution(function() use ($error) {
                return Envelope::wrap(Result::failure($error));
            });
            
            expect($pending->failure())->toBe($error);
        });

        it('returns error for failed Result', function () {
            $error = new Exception('result error');
            $pending = new PendingPipelineExecution(function() use ($error) {
                return Result::failure($error);
            });
            
            expect($pending->failure())->toBe($error);
        });

        it('returns exception when computation throws', function () {
            $pending = new PendingPipelineExecution(function() {
                throw new Exception('thrown error');
            });
            
            $failure = $pending->failure();
            expect($failure)->toBeInstanceOf(Exception::class);
            expect($failure->getMessage())->toBe('thrown error');
        });
    });

    describe('Stream Processing', function () {
        it('streams iterable envelope content', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap([1, 2, 3]);
            });
            
            $items = [];
            foreach ($pending->stream() as $item) {
                $items[] = $item;
            }
            
            expect($items)->toBe([1, 2, 3]);
        });

        it('streams single value as single item', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap('single');
            });
            
            $items = [];
            foreach ($pending->stream() as $item) {
                $items[] = $item;
            }
            
            expect($items)->toBe(['single']);
        });

        it('returns empty stream for failed result', function () {
            $pending = new PendingPipelineExecution(function() {
                return Envelope::wrap(Result::failure(new Exception('error')));
            });
            
            $items = [];
            foreach ($pending->stream() as $item) {
                $items[] = $item;
            }
            
            expect($items)->toBe([]);
        });
    });

    describe('Transformations', function () {
        describe('map()', function () {
            it('transforms successful envelope value', function () {
                $pending = new PendingPipelineExecution(function() {
                    return Envelope::wrap(10, [new PendingTestStamp('original')]);
                });
                
                $mapped = $pending->map(fn($x) => $x * 2);
                $envelope = $mapped->envelope();
                
                expect($envelope->result()->unwrap())->toBe(20);
                expect($envelope->has(PendingTestStamp::class))->toBeTrue(); // Stamps preserved
            });

            it('preserves failure in envelope', function () {
                $pending = new PendingPipelineExecution(function() {
                    return Envelope::wrap(Result::failure(new Exception('error')));
                });
                
                $mapped = $pending->map(fn($x) => $x * 2); // Should not execute
                
                expect($mapped->success())->toBeFalse();
                expect($mapped->failure()->getMessage())->toBe('error');
            });

            it('transforms direct Result', function () {
                $pending = new PendingPipelineExecution(function() {
                    return Result::success(5);
                });
                
                $mapped = $pending->map(fn($x) => $x + 3);
                
                expect($mapped->result()->unwrap())->toBe(8);
            });
        });

        describe('mapEnvelope()', function () {
            it('transforms envelope directly', function () {
                $pending = new PendingPipelineExecution(function() {
                    return Envelope::wrap('test');
                });
                
                $mapped = $pending->mapEnvelope(function($env) {
                    return $env
                        ->with(new PendingTestStamp('transformed'))
                        ->withMessage(Result::success(strtoupper($env->result()->unwrap())));
                });
                
                $envelope = $mapped->envelope();
                expect($envelope->result()->unwrap())->toBe('TEST');
                expect($envelope->has(PendingTestStamp::class))->toBeTrue();
            });

            it('wraps non-envelope results', function () {
                $pending = new PendingPipelineExecution(function() {
                    return 'raw value';
                });
                
                $mapped = $pending->mapEnvelope(function($env) {
                    return $env->with(new PendingTestStamp('wrapped'));
                });
                
                $envelope = $mapped->envelope();
                expect($envelope->result()->unwrap())->toBe('raw value');
                expect($envelope->has(PendingTestStamp::class))->toBeTrue();
            });
        });

        describe('then()', function () {
            it('chains computation with successful envelope', function () {
                $pending = new PendingPipelineExecution(function() {
                    return Envelope::wrap(5, [new PendingTestStamp('chained')]);
                });
                
                $chained = $pending->then(fn($x) => $x * 3);
                $envelope = $chained->envelope();
                
                expect($envelope->result()->unwrap())->toBe(15);
                expect($envelope->has(PendingTestStamp::class))->toBeTrue(); // Stamps preserved
            });

            it('short-circuits on failure', function () {
                $pending = new PendingPipelineExecution(function() {
                    return Envelope::wrap(Result::failure(new Exception('failed')));
                });
                
                $chained = $pending->then(fn($x) => $x * 3); // Should not execute
                
                expect($chained->success())->toBeFalse();
                expect($chained->failure()->getMessage())->toBe('failed');
            });
        });
    });

    describe('Lazy Evaluation', function () {
        it('executes computation only once', function () {
            $executionCount = 0;
            
            $pending = new PendingPipelineExecution(function() use (&$executionCount) {
                $executionCount++;
                return 'result';
            });
            
            // Multiple accesses
            $pending->payload();
            $pending->result();
            $pending->envelope();
            $pending->success();
            
            expect($executionCount)->toBe(1);
        });

        it('caches result across transformations', function () {
            $executionCount = 0;
            
            $pending = new PendingPipelineExecution(function() use (&$executionCount) {
                $executionCount++;
                return 10;
            });
            
            $mapped = $pending->map(fn($x) => $x * 2);
            
            // Access multiple times
            $mapped->payload();
            $mapped->payload();
            
            expect($executionCount)->toBe(1);
        });

        it('creates new execution context for transformations', function () {
            $baseExecutions = 0;
            $transformExecutions = 0;
            
            $base = new PendingPipelineExecution(function() use (&$baseExecutions) {
                $baseExecutions++;
                return 5;
            });
            
            $transformed = $base->map(function($x) use (&$transformExecutions) {
                $transformExecutions++;
                return $x * 2;
            });
            
            // Execute base only
            $base->payload();
            expect($baseExecutions)->toBe(1);
            expect($transformExecutions)->toBe(0);
            
            // Execute transformed
            $transformed->payload();
            expect($baseExecutions)->toBe(1); // Base used cached result
            expect($transformExecutions)->toBe(1);
        });
    });
});