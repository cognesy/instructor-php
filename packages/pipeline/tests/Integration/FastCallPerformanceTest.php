<?php declare(strict_types=1);

use Cognesy\Pipeline\Legacy\Chain\ResultChain;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

describe('FastCall Performance Test', function () {
    
    beforeEach(function () {
        $this->testData = 'test input string for processing';
        $this->iterations = 1000;
        
        // Simple processing steps for comparison
        $this->userProcessors = [
            fn(string $input) => $input . ' step1',
            fn(string $input) => $input . ' step2', 
            fn(string $input) => $input . ' step3',
            fn(string $input) => str_replace('test', 'processed', $input),
            fn(string $input) => strtoupper($input),
        ];
        
        // Fast processors that work with ProcessingState
        $this->fastProcessors = [
            fn(ProcessingState $state, ?callable $next) => $next ? $next($state->withResult(Result::success($state->value() . ' step1'))) : $state->withResult(Result::success($state->value() . ' step1')),
            fn(ProcessingState $state, ?callable $next) => $next ? $next($state->withResult(Result::success($state->value() . ' step2'))) : $state->withResult(Result::success($state->value() . ' step2')),
            fn(ProcessingState $state, ?callable $next) => $next ? $next($state->withResult(Result::success($state->value() . ' step3'))) : $state->withResult(Result::success($state->value() . ' step3')),
            fn(ProcessingState $state, ?callable $next) => $next ? $next($state->withResult(Result::success(str_replace('test', 'processed', $state->value())))) : $state->withResult(Result::success(str_replace('test', 'processed', $state->value()))),
            fn(ProcessingState $state, ?callable $next) => $next ? $next($state->withResult(Result::success(strtoupper($state->value())))) : $state->withResult(Result::success(strtoupper($state->value()))),
        ];
    });

    test('compares execution time: Pipeline vs Pipeline FastCall vs ResultChain', function () {
        // Measure regular Pipeline execution time
        $regularPipelineStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $pipeline = Pipeline::builder()
                ->through($this->userProcessors[0])
                ->through($this->userProcessors[1])
                ->through($this->userProcessors[2])
                ->through($this->userProcessors[3])
                ->through($this->userProcessors[4])
                ->create();
                
            $result = $pipeline->executeWith($this->testData)->value();
        }
        
        $regularPipelineEnd = hrtime(true);
        $regularPipelineTime = $regularPipelineEnd - $regularPipelineStart;
        
        // Measure FastCall Pipeline execution time
        $fastPipelineStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $pipeline = Pipeline::builder()
                ->throughRaw($this->fastProcessors[0])
                ->throughRaw($this->fastProcessors[1])
                ->throughRaw($this->fastProcessors[2])
                ->throughRaw($this->fastProcessors[3])
                ->throughRaw($this->fastProcessors[4])
                ->create();
                
            $result = $pipeline->executeWith($this->testData)->value();
        }
        
        $fastPipelineEnd = hrtime(true);
        $fastPipelineTime = $fastPipelineEnd - $fastPipelineStart;
        
        // Measure ResultChain execution time
        $chainStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = ResultChain::make()
                ->through($this->userProcessors[0])
                ->through($this->userProcessors[1])
                ->through($this->userProcessors[2])
                ->through($this->userProcessors[3])
                ->through($this->userProcessors[4])
                ->process($this->testData);
        }
        
        $chainEnd = hrtime(true);
        $chainTime = $chainEnd - $chainStart;
        
        // Convert to microseconds and calculate per-call
        $regularPipelineTimePerCallUs = ($regularPipelineTime / 1000) / $this->iterations;
        $fastPipelineTimePerCallUs = ($fastPipelineTime / 1000) / $this->iterations;
        $chainTimePerCallUs = ($chainTime / 1000) / $this->iterations;
        
        // Output results
        echo "\n=== FastCall Performance Comparison (over {$this->iterations} iterations) ===\n";
        echo sprintf("Regular Pipeline time per call: %.3f microseconds\n", $regularPipelineTimePerCallUs);
        echo sprintf("Fast Pipeline time per call: %.3f microseconds\n", $fastPipelineTimePerCallUs);
        echo sprintf("ResultChain time per call: %.3f microseconds\n", $chainTimePerCallUs);
        echo "\n";
        echo sprintf("FastCall improvement vs Regular: %.3f microseconds (%.1f%% faster)\n", 
            $regularPipelineTimePerCallUs - $fastPipelineTimePerCallUs,
            (($regularPipelineTimePerCallUs / max($fastPipelineTimePerCallUs, 0.001)) - 1) * 100
        );
        echo sprintf("FastCall vs ResultChain: %.3f microseconds overhead (%.1f%% slower)\n", 
            $fastPipelineTimePerCallUs - $chainTimePerCallUs,
            (($fastPipelineTimePerCallUs / max($chainTimePerCallUs, 0.001)) - 1) * 100
        );
        
        // Verify all produce the same result
        $regularResult = Pipeline::builder()
            ->through($this->userProcessors[0])
            ->through($this->userProcessors[1])
            ->through($this->userProcessors[2])
            ->through($this->userProcessors[3])
            ->through($this->userProcessors[4])
            ->create()
            ->executeWith($this->testData)->value();
            
        $fastResult = Pipeline::builder()
            ->throughRaw($this->fastProcessors[0])
            ->throughRaw($this->fastProcessors[1])
            ->throughRaw($this->fastProcessors[2])
            ->throughRaw($this->fastProcessors[3])
            ->throughRaw($this->fastProcessors[4])
            ->create()
            ->executeWith($this->testData)->value();
            
        $chainResult = ResultChain::make()
            ->through($this->userProcessors[0])
            ->through($this->userProcessors[1])
            ->through($this->userProcessors[2])
            ->through($this->userProcessors[3])
            ->through($this->userProcessors[4])
            ->process($this->testData);
            
        expect($regularResult)->toBe($chainResult);
        expect($fastResult)->toBe($chainResult);
        
        // FastCall should be faster than regular Pipeline
        expect($fastPipelineTimePerCallUs)->toBeLessThan($regularPipelineTimePerCallUs);
    });
});