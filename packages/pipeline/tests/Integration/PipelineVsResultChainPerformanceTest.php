<?php declare(strict_types=1);

use Cognesy\Pipeline\Legacy\Chain\ResultChain;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

describe('Pipeline vs ResultChain Performance Comparison', function () {
    
    beforeEach(function () {
        $this->testData = 'test input string for processing';
        $this->iterations = 1000;
        
        // Simple processing steps for comparison
        $this->processors = [
            fn(string $input) => $input . ' step1',
            fn(string $input) => $input . ' step2', 
            fn(string $input) => $input . ' step3',
            fn(string $input) => str_replace('test', 'processed', $input),
            fn(string $input) => strtoupper($input),
        ];
    });

    test('compares memory usage between Pipeline and ResultChain', function () {
        // Measure Pipeline memory usage
        $pipelineMemoryStart = memory_get_usage(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $pipeline = Pipeline::builder()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->create();
                
            $result = $pipeline->executeWith(ProcessingState::with($this->testData))->value();
        }
        
        $pipelineMemoryEnd = memory_get_usage(true);
        $pipelineMemoryUsed = $pipelineMemoryEnd - $pipelineMemoryStart;
        
        // Force garbage collection
        gc_collect_cycles();
        
        // Measure ResultChain memory usage
        $chainMemoryStart = memory_get_usage(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = ResultChain::make()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->process($this->testData);
        }
        
        $chainMemoryEnd = memory_get_usage(true);
        $chainMemoryUsed = $chainMemoryEnd - $chainMemoryStart;
        
        // Calculate per-call memory usage
        $pipelineMemoryPerCall = $pipelineMemoryUsed / $this->iterations;
        $chainMemoryPerCall = $chainMemoryUsed / $this->iterations;
        
        // Output results
        echo "\n=== Memory Usage Comparison (over {$this->iterations} iterations) ===\n";
        echo sprintf("Pipeline memory per call: %.2f bytes\n", $pipelineMemoryPerCall);
        echo sprintf("ResultChain memory per call: %.2f bytes\n", $chainMemoryPerCall);
        echo sprintf("Memory overhead: %.2f bytes (%.1f%% increase)\n", 
            $pipelineMemoryPerCall - $chainMemoryPerCall,
            $chainMemoryPerCall ? (($pipelineMemoryPerCall / $chainMemoryPerCall) - 1) * 100 : 0
        );
        
        // Ensure both produce the same result
        $pipelineResult = Pipeline::builder()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4])
            ->create()
            ->executeWith(ProcessingState::with($this->testData))->value();
            
        $chainResult = ResultChain::make()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4])
            ->process($this->testData);
            
        expect($pipelineResult)->toBe($chainResult);
    });

    test('compares execution time between Pipeline and ResultChain', function () {
        // Measure Pipeline execution time
        $pipelineStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $pipeline = Pipeline::builder()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->create();
                
            $result = $pipeline->executeWith(ProcessingState::with($this->testData))->value();
        }
        
        $pipelineEnd = hrtime(true);
        $pipelineTime = $pipelineEnd - $pipelineStart;
        
        // Measure ResultChain execution time
        $chainStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = ResultChain::make()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->process($this->testData);
        }
        
        $chainEnd = hrtime(true);
        $chainTime = $chainEnd - $chainStart;
        
        // Convert to microseconds and calculate per-call
        $pipelineTimePerCallUs = ($pipelineTime / 1000) / $this->iterations;
        $chainTimePerCallUs = ($chainTime / 1000) / $this->iterations;
        
        // Output results
        echo "\n=== Execution Time Comparison (over {$this->iterations} iterations) ===\n";
        echo sprintf("Pipeline time per call: %.3f microseconds\n", $pipelineTimePerCallUs);
        echo sprintf("ResultChain time per call: %.3f microseconds\n", $chainTimePerCallUs);
        echo sprintf("Time overhead: %.3f microseconds (%.1f%% increase)\n", 
            $pipelineTimePerCallUs - $chainTimePerCallUs,
            (($pipelineTimePerCallUs / $chainTimePerCallUs) - 1) * 100
        );
        
        // Both should complete in reasonable time
        expect($pipelineTimePerCallUs)->toBeLessThan(1000); // Less than 1ms per call
        expect($chainTimePerCallUs)->toBeLessThan(1000); // Less than 1ms per call
    });

    test('compares construction vs execution overhead', function () {
        // Test Pipeline construction overhead
        $pipelineConstructionStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $pipeline = Pipeline::builder()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->create();
        }
        
        $pipelineConstructionEnd = hrtime(true);
        $pipelineConstructionTime = $pipelineConstructionEnd - $pipelineConstructionStart;
        
        // Test Pipeline execution overhead (pre-built pipeline)
        $preBuildPipeline = Pipeline::builder()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4])
            ->create();
            
        $pipelineExecutionStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = $preBuildPipeline->executeWith(ProcessingState::with($this->testData))->value();
        }
        
        $pipelineExecutionEnd = hrtime(true);
        $pipelineExecutionTime = $pipelineExecutionEnd - $pipelineExecutionStart;
        
        // Test ResultChain construction overhead
        $chainConstructionStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $chain = ResultChain::make()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4]);
        }
        
        $chainConstructionEnd = hrtime(true);
        $chainConstructionTime = $chainConstructionEnd - $chainConstructionStart;
        
        // Test ResultChain execution overhead (pre-built chain)
        $preBuildChain = ResultChain::make()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4]);
            
        $chainExecutionStart = hrtime(true);
        
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = $preBuildChain->process($this->testData);
        }
        
        $chainExecutionEnd = hrtime(true);
        $chainExecutionTime = $chainExecutionEnd - $chainExecutionStart;
        
        // Convert to microseconds per call
        $pipelineConstructionPerCallUs = ($pipelineConstructionTime / 1000) / $this->iterations;
        $pipelineExecutionPerCallUs = ($pipelineExecutionTime / 1000) / $this->iterations;
        $chainConstructionPerCallUs = ($chainConstructionTime / 1000) / $this->iterations;
        $chainExecutionPerCallUs = ($chainExecutionTime / 1000) / $this->iterations;
        
        echo "\n=== Construction vs Execution Overhead (over {$this->iterations} iterations) ===\n";
        echo sprintf("Pipeline construction per call: %.3f microseconds\n", $pipelineConstructionPerCallUs);
        echo sprintf("Pipeline execution per call: %.3f microseconds\n", $pipelineExecutionPerCallUs);
        echo sprintf("ResultChain construction per call: %.3f microseconds\n", $chainConstructionPerCallUs);
        echo sprintf("ResultChain execution per call: %.3f microseconds\n", $chainExecutionPerCallUs);
        echo "\n";
        echo sprintf("Pipeline total overhead: %.3f microseconds\n", $pipelineConstructionPerCallUs + $pipelineExecutionPerCallUs);
        echo sprintf("ResultChain total overhead: %.3f microseconds\n", $chainConstructionPerCallUs + $chainExecutionPerCallUs);
        
        // Validate both approaches work
        expect($pipelineConstructionPerCallUs)->toBeGreaterThan(0);
        expect($pipelineExecutionPerCallUs)->toBeGreaterThan(0);
        expect($chainConstructionPerCallUs)->toBeGreaterThan(0);
        expect($chainExecutionPerCallUs)->toBeGreaterThan(0);
    });
});