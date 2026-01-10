<?php declare(strict_types=1);

use Cognesy\Pipeline\Legacy\Chain\ResultChain;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

describe('Accurate Memory Measurement', function () {
    
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

    test('measures actual object allocation using memory_get_usage(false)', function () {
        // Force garbage collection before measurement
        gc_collect_cycles();
        
        // Measure Pipeline memory usage (without reusing pool memory)
        $pipelineMemoryStart = memory_get_usage(false); // false = actual script memory usage
        
        $pipelineResults = [];
        for ($i = 0; $i < $this->iterations; $i++) {
            $pipeline = Pipeline::builder()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->create();
                
            $result = $pipeline->executeWith(ProcessingState::with($this->testData))->value();
            // Store results to prevent premature garbage collection
            $pipelineResults[] = $result;
        }
        
        $pipelineMemoryEnd = memory_get_usage(false);
        $pipelineMemoryUsed = $pipelineMemoryEnd - $pipelineMemoryStart;
        
        // Clear results and force garbage collection
        $pipelineResults = null;
        gc_collect_cycles();
        
        // Measure ResultChain memory usage
        $chainMemoryStart = memory_get_usage(false);
        
        $chainResults = [];
        for ($i = 0; $i < $this->iterations; $i++) {
            $result = ResultChain::make()
                ->through($this->processors[0])
                ->through($this->processors[1])
                ->through($this->processors[2])
                ->through($this->processors[3])
                ->through($this->processors[4])
                ->process($this->testData);
            $chainResults[] = $result;
        }
        
        $chainMemoryEnd = memory_get_usage(false);
        $chainMemoryUsed = $chainMemoryEnd - $chainMemoryStart;
        
        // Calculate per-call memory usage
        $pipelineMemoryPerCall = $pipelineMemoryUsed / $this->iterations;
        $chainMemoryPerCall = $chainMemoryUsed / $this->iterations;
        
        // Output results
        echo "\n=== Accurate Memory Usage Comparison (over {$this->iterations} iterations) ===\n";
        echo sprintf("Pipeline total memory used: %d bytes\n", $pipelineMemoryUsed);
        echo sprintf("ResultChain total memory used: %d bytes\n", $chainMemoryUsed);
        echo sprintf("Pipeline memory per call: %.2f bytes\n", $pipelineMemoryPerCall);
        echo sprintf("ResultChain memory per call: %.2f bytes\n", $chainMemoryPerCall);
        
        if ($pipelineMemoryPerCall > 0 && $chainMemoryPerCall > 0) {
            $overhead = $pipelineMemoryPerCall - $chainMemoryPerCall;
            $percentIncrease = (($pipelineMemoryPerCall / $chainMemoryPerCall) - 1) * 100;
            echo sprintf("Memory overhead: %.2f bytes (%.1f%% increase)\n", $overhead, $percentIncrease);
        }
        
        // Both should use some memory
        expect($pipelineMemoryPerCall)->toBeGreaterThan(0);
        expect($chainMemoryPerCall)->toBeGreaterThan(0);
    });

    test('measures single call memory footprint', function () {
        gc_collect_cycles();
        
        // Measure single Pipeline call
        $beforePipeline = memory_get_usage(false);
        
        $pipeline = Pipeline::builder()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4])
            ->create();
            
        $afterConstruction = memory_get_usage(false);
        $result = $pipeline->executeWith(ProcessingState::with($this->testData))->value();
        $afterExecution = memory_get_usage(false);
        
        $pipelineConstructionMemory = $afterConstruction - $beforePipeline;
        $pipelineExecutionMemory = $afterExecution - $afterConstruction;
        $pipelineTotalMemory = $afterExecution - $beforePipeline;
        
        // Clear and measure single ResultChain call
        $pipeline = null;
        $result = null;
        gc_collect_cycles();
        
        $beforeChain = memory_get_usage(false);
        
        $chain = ResultChain::make()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4]);
            
        $afterChainConstruction = memory_get_usage(false);
        $result = $chain->process($this->testData);
        $afterChainExecution = memory_get_usage(false);
        
        $chainConstructionMemory = $afterChainConstruction - $beforeChain;
        $chainExecutionMemory = $afterChainExecution - $afterChainConstruction;
        $chainTotalMemory = $afterChainExecution - $beforeChain;
        
        echo "\n=== Single Call Memory Footprint ===\n";
        echo sprintf("Pipeline construction: %d bytes\n", $pipelineConstructionMemory);
        echo sprintf("Pipeline execution: %d bytes\n", $pipelineExecutionMemory);
        echo sprintf("Pipeline total: %d bytes\n", $pipelineTotalMemory);
        echo "\n";
        echo sprintf("ResultChain construction: %d bytes\n", $chainConstructionMemory);
        echo sprintf("ResultChain execution: %d bytes\n", $chainExecutionMemory);
        echo sprintf("ResultChain total: %d bytes\n", $chainTotalMemory);
        echo "\n";
        echo sprintf("Memory overhead: %d bytes (%.1f%% increase)\n", 
            $pipelineTotalMemory - $chainTotalMemory,
            (($pipelineTotalMemory / max($chainTotalMemory, 1)) - 1) * 100
        );
        
        expect($pipelineTotalMemory)->toBeGreaterThan(0);
        expect($chainTotalMemory)->toBeGreaterThan(0);
    });

    test('analyzes object creation patterns', function () {
        // Create a single pipeline and analyze its structure
        $pipeline = Pipeline::builder()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4])
            ->create();
            
        // Analyze internal structure
        $reflection = new ReflectionClass($pipeline);
        $stepsProperty = $reflection->getProperty('steps');
        
        $steps = $stepsProperty->getValue($pipeline);
        
        $middlewareProperty = $reflection->getProperty('middleware');
        
        $middleware = $middlewareProperty->getValue($pipeline);
        
        echo "\n=== Pipeline Internal Structure Analysis ===\n";
        echo sprintf("Steps count: %d\n", count($steps));
        echo sprintf("Middleware count: %d\n", count($middleware));
        echo sprintf("Steps object class: %s\n", get_class($steps));
        
        // Create ResultChain and analyze
        $chain = ResultChain::make()
            ->through($this->processors[0])
            ->through($this->processors[1])
            ->through($this->processors[2])
            ->through($this->processors[3])
            ->through($this->processors[4]);
            
        $chainReflection = new ReflectionClass($chain);
        $processorsProperty = $chainReflection->getProperty('processors');
        
        $chainProcessors = $processorsProperty->getValue($chain);
        
        echo "\n=== ResultChain Internal Structure Analysis ===\n";
        echo sprintf("Processors count: %d\n", count($chainProcessors));
        echo sprintf("Each processor is a Closure object\n");
        
        // Both should have the expected number of processors
        expect(count($steps))->toBe(5);
        expect(count($chainProcessors))->toBe(5);
    });
});