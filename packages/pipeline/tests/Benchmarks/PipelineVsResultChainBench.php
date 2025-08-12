<?php declare(strict_types=1);

use Cognesy\Pipeline\Legacy\Chain\ResultChain;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

/**
 * Benchmark comparing Pipeline vs ResultChain performance
 * 
 * Measures the overhead of Pipeline's architecture compared to ResultChain
 * for equivalent operations (no middleware/hooks vs no advanced features).
 */
class PipelineVsResultChainBench
{
    private function getTestData(): string
    {
        return 'test input string for processing';
    }
    
    private function getProcessors(): array
    {
        return [
            fn(string $input) => $input . ' step1',
            fn(string $input) => $input . ' step2', 
            fn(string $input) => $input . ' step3',
            fn(string $input) => str_replace('test', 'processed', $input),
            fn(string $input) => strtoupper($input),
        ];
    }

    /**
     * @Revs(1000)
     * @Iterations(10)
     * @Warmup(3)
     */
    public function benchPipelineBasic(): void
    {
        $testData = $this->getTestData();
        $processors = $this->getProcessors();
        
        $pipeline = Pipeline::builder()
            ->through($processors[0])
            ->through($processors[1])
            ->through($processors[2])
            ->through($processors[3])
            ->through($processors[4])
            ->create();
            
        // Execute pipeline (this is where the actual work happens due to lazy evaluation)
        $result = $pipeline->executeWith(ProcessingState::with($testData))->value();
    }

    /**
     * @Revs(1000) 
     * @Iterations(10)
     * @Warmup(3)
     */
    public function benchResultChainBasic(): void
    {
        $testData = $this->getTestData();
        $processors = $this->getProcessors();
        
        $result = ResultChain::make()
            ->through($processors[0])
            ->through($processors[1])
            ->through($processors[2])
            ->through($processors[3])
            ->through($processors[4])
            ->process($testData);
    }

    /**
     * @Revs(1000)
     * @Iterations(10)
     * @Warmup(3)
     */
    public function benchPipelineConstruction(): void
    {
        $processors = $this->getProcessors();
        
        // Measure just the construction overhead (no execution)
        $pipeline = Pipeline::builder()
            ->through($processors[0])
            ->through($processors[1]) 
            ->through($processors[2])
            ->through($processors[3])
            ->through($processors[4])
            ->create();
    }

    /**
     * @Revs(1000)
     * @Iterations(10)
     * @Warmup(3)
     */
    public function benchResultChainConstruction(): void
    {
        $processors = $this->getProcessors();
        
        // Measure just the construction overhead (no execution)
        $chain = ResultChain::make()
            ->through($processors[0])
            ->through($processors[1])
            ->through($processors[2])
            ->through($processors[3])
            ->through($processors[4]);
    }

    /**
     * @Revs(1000)
     * @Iterations(10)
     * @Warmup(3)
     */
    public function benchPipelineExecution(): void
    {
        $testData = $this->getTestData();
        $processors = $this->getProcessors();
        
        // Pre-built pipeline to measure just execution overhead
        static $pipeline = null;
        if ($pipeline === null) {
            $pipeline = Pipeline::builder()
                ->through($processors[0])
                ->through($processors[1])
                ->through($processors[2])
                ->through($processors[3])
                ->through($processors[4])
                ->create();
        }
        
        $result = $pipeline->executeWith(ProcessingState::with($testData))->value();
    }

    /**
     * @Revs(1000)
     * @Iterations(10) 
     * @Warmup(3)
     */
    public function benchResultChainExecution(): void
    {
        $testData = $this->getTestData();
        $processors = $this->getProcessors();
        
        // Pre-built chain to measure just execution overhead
        static $chain = null;
        if ($chain === null) {
            $chain = ResultChain::make()
                ->through($processors[0])
                ->through($processors[1])
                ->through($processors[2])
                ->through($processors[3])
                ->through($processors[4]);
        }
        
        $result = $chain->process($testData);
    }
}