<?php

use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\TagInterface;

// Test tags for validation
class PipelineMiddlewareTag implements TagInterface {
    public function __construct(public readonly string $message) {}
}

class ProcessorHookTag implements TagInterface {
    public function __construct(public readonly string $message) {}
}

// Test middleware for pipeline-level concerns
class TestPipelineMiddleware implements PipelineMiddlewareInterface {
    public function __construct(private string $name) {}
    
    public function handle(Computation $computation, callable $next): Computation {
        $before = new PipelineMiddlewareTag("Pipeline MW {$this->name} - Before Chain");
        $result = $next($computation->with($before));
        $after = new PipelineMiddlewareTag("Pipeline MW {$this->name} - After Chain");
        return $result->with($after);
    }
}

describe('Dual Middleware Architecture', function () {
    
    it('executes pipeline middleware around entire processor chain', function () {
        $result = Pipeline::for(1)
            ->withMiddleware(new TestPipelineMiddleware('A'))
            ->through(fn($x) => $x + 1)  // P1: 1 → 2
            ->through(fn($x) => $x * 2)  // P2: 2 → 4
            ->process()
            ->computation();
            
        expect($result->value())->toBe(4);
        expect($result->count(PipelineMiddlewareTag::class))->toBe(2); // Before + After chain
        
        $tags = $result->all(PipelineMiddlewareTag::class);
        expect($tags[0]->message)->toBe('Pipeline MW A - Before Chain');
        expect($tags[1]->message)->toBe('Pipeline MW A - After Chain');
    });
    
    it('executes processor hooks around each individual processor', function () {
        $hookExecutions = [];
        
        $result = Pipeline::for(1)
            ->beforeEach(function($comp) use (&$hookExecutions) {
                $hookExecutions[] = "Before P: {$comp->value()}";
                return $comp->with(new ProcessorHookTag("Before hook"));
            })
            ->afterEach(function($comp) use (&$hookExecutions) {
                $hookExecutions[] = "After P: {$comp->value()}";
                return $comp->with(new ProcessorHookTag("After hook"));
            })
            ->through(fn($x) => $x + 1)  // P1: 1 → 2
            ->through(fn($x) => $x * 2)  // P2: 2 → 4
            ->process()
            ->computation();
            
        expect($result->value())->toBe(4);
        expect($result->count(ProcessorHookTag::class))->toBe(4); // 2 hooks × 2 processors
        
        expect($hookExecutions)->toBe([
            'Before P: 1',
            'After P: 2',
            'Before P: 2', 
            'After P: 4'
        ]);
    });
    
    it('combines pipeline middleware with processor hooks correctly', function () {
        $executions = [];
        
        $result = Pipeline::for(1)
            ->withMiddleware(new TestPipelineMiddleware('Chain'))
            ->beforeEach(function($comp) use (&$executions) {
                $executions[] = "Hook before: {$comp->value()}";
                return $comp;
            })
            ->through(fn($x) => $x + 10)
            ->afterEach(function($comp) use (&$executions) {
                $executions[] = "Hook after: {$comp->value()}";
                return $comp;
            })
            ->through(fn($x) => $x * 2)
            ->process()
            ->computation();
            
        expect($result->value())->toBe(22); // (1 + 10) * 2
        
        // Pipeline middleware: 2 tags (before + after chain)
        expect($result->count(PipelineMiddlewareTag::class))->toBe(2);
        
        // Hook executions: 2 per processor = 4 total
        expect($executions)->toHaveCount(4);
        expect($executions)->toBe([
            'Hook before: 1',
            'Hook after: 11',
            'Hook before: 11',
            'Hook after: 22'
        ]);
    });
    
    it('demonstrates pipeline middleware for cross-cutting concerns', function () {
        $retryAttempts = 0;
        
        // Simulate retry middleware that wraps entire chain
        $retryMiddleware = new class($retryAttempts) implements PipelineMiddlewareInterface {
            public function __construct(private int &$attempts) {}
            
            public function handle(Computation $computation, callable $next): Computation {
                $maxRetries = 3;
                
                for ($i = 1; $i <= $maxRetries; $i++) {
                    $this->attempts++;
                    
                    try {
                        $result = $next($computation);
                        if ($result->isSuccess()) {
                            return $result->with(new PipelineMiddlewareTag("Success on attempt $i"));
                        }
                    } catch (Exception $e) {
                        if ($i === $maxRetries) {
                            throw $e;
                        }
                        // Continue to next retry
                    }
                }
                
                return $computation->withResult(\Cognesy\Utils\Result\Result::failure(new Exception("Max retries exceeded")));
            }
        };
        
        $processorExecutions = 0;
        
        $result = Pipeline::for(['data' => 'test'])
            ->withMiddleware($retryMiddleware)
            ->through(function($data) use (&$processorExecutions) {
                $processorExecutions++;
                
                // Fail first 2 attempts, succeed on 3rd
                if ($processorExecutions < 3) {
                    throw new Exception("Processor failure #{$processorExecutions}");
                }
                
                return $data['data'] . ' processed';
            })
            ->process()
            ->computation();
            
        expect($result->value())->toBe('test processed');
        expect($retryAttempts)->toBe(3); // Middleware called 3 times
        expect($processorExecutions)->toBe(3); // Processor called 3 times (entire chain restarted)
        
        $tag = $result->first(PipelineMiddlewareTag::class);
        expect($tag->message)->toBe('Success on attempt 3');
    });
    
    it('demonstrates processor hooks for step-level concerns', function () {
        $stepValidations = [];
        
        $result = Pipeline::for(5)
            ->beforeEach(function($comp) use (&$stepValidations) {
                $value = $comp->value();
                $stepValidations[] = "Validating input: $value";
                
                if ($value < 0) {
                    throw new Exception("Invalid negative input: $value");
                }
                
                return $comp;
            })
            ->through(fn($x) => $x * 2)      // P1: 5 → 10
            ->through(fn($x) => $x - 3)      // P2: 10 → 7  
            ->through(fn($x) => $x + 1)      // P3: 7 → 8
            ->process()
            ->computation();
            
        expect($result->value())->toBe(8);
        expect($stepValidations)->toBe([
            'Validating input: 5',
            'Validating input: 10', 
            'Validating input: 7'
        ]);
    });
    
    it('shows finishWhen hook can terminate processing between steps', function () {
        $processedSteps = [];
        
        $result = Pipeline::for(1)
            ->through(function($x) use (&$processedSteps) {
                $processedSteps[] = "Step 1: $x → " . ($x + 5);
                return $x + 5; // 1 → 6
            })
            ->finishWhen(fn($comp) => $comp->value() > 5) // Should terminate here
            ->through(function($x) use (&$processedSteps) {
                $processedSteps[] = "Step 2: $x → " . ($x * 10);
                return $x * 10; // This should not execute
            })
            ->through(function($x) use (&$processedSteps) {
                $processedSteps[] = "Step 3: $x → " . ($x + 100);
                return $x + 100; // This should not execute
            })
            ->process()
            ->computation();
            
        expect($result->value())->toBe(6); // Stopped after first processor
        expect($processedSteps)->toBe(['Step 1: 1 → 6']); // Only first step executed
    });
    
    it('validates middleware and hook execution order', function () {
        $executionOrder = [];
        
        $orderMiddleware = new class($executionOrder) implements PipelineMiddlewareInterface {
            public function __construct(private array &$order) {}
            
            public function handle(Computation $computation, callable $next): Computation {
                $this->order[] = 'Pipeline MW: Before Chain';
                $result = $next($computation);
                $this->order[] = 'Pipeline MW: After Chain';
                return $result;
            }
        };
        
        $result = Pipeline::for(1)
            ->withMiddleware($orderMiddleware)
            ->beforeEach(function($comp) use (&$executionOrder) {
                $executionOrder[] = "Hook: Before P{$comp->value()}";
                return $comp;
            })
            ->through(fn($x) => $x + 1)
            ->afterEach(function($comp) use (&$executionOrder) {
                $executionOrder[] = "Hook: After P{$comp->value()}";
                return $comp;
            })
            ->through(fn($x) => $x * 2)
            ->process()
            ->value(); // Force execution
            
        expect($result)->toBe(4); // Verify pipeline worked
        expect($executionOrder)->toContain('Pipeline MW: Before Chain');
        expect($executionOrder)->toContain('Hook: Before P1');
        expect($executionOrder)->toHaveCount(6); // Should have 6 total executions
    });
});