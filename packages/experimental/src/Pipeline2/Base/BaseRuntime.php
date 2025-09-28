<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Base;

use Cognesy\Experimental\Pipeline2\Contracts\Execution;
use Cognesy\Experimental\Pipeline2\Contracts\OperatorFactory;
use Cognesy\Experimental\Pipeline2\PipelineDefinition;

final class BaseRuntime
{
    public function __construct(
        private OperatorFactory $operatorFactory,
    ) {}

    public static function new(): self {
        return new self(new BaseOperatorFactory());
    }

    /**
     * Starts a new pipeline execution.
     *
     * @param PipelineDefinition $definition The pipeline to execute.
     * @param mixed $initialPayload The starting data for the pipeline.
     * @param callable|null $terminal An optional final operation to call.
     * @return Execution A controllable instance for the execution.
     */
    public function start(
        PipelineDefinition $definition,
        mixed $initialPayload,
        ?callable $terminal = null,
    ): Execution {
        $operators = $this->compileDefinition($definition);

        return new BaseExecution(
            operators: $operators,
            initialPayload: $initialPayload,
            terminal: $terminal ?? fn(mixed $payload): mixed => $payload,
        );
    }

    private function compileDefinition(PipelineDefinition $definition) : array {
        return array_map(
            fn($spec) => $this->operatorFactory->create($spec),
            iterator_to_array($definition),
        );
    }

    private function createCallChain(
        array $callables,
        ?callable $terminalFn = null
    ): callable {
        $terminalFn ??= fn($payload) => $payload; // Default identity terminal
        return array_reduce( // Reduce to a single callable
            array_reverse($callables), // Reverse to maintain order in reduction
            fn($next, $current) => fn($payload) => $current($payload, $next), // Chain current to next
            $terminalFn // Initial value is the terminal function
        );
    }

    /**
     * Iterable/pausable call chain using coroutine operators.
     *
     * Operator contract (yielding operator):
     *   function(): \Generator {
     *       $payload = yield;               // receive inbound payload
     *       // pre-processing...
     *       $downstream = yield $payload;   // pass to next, pause here
     *       // post-processing...
     *       return $downstream;             // or transformed result
     *   }
     *
     * Usage:
     *   $iter = $this->createCallChainIterator($ops)($initial);
     *   foreach ($iter as $step) {
     *       // $step = ['index'=>int, 'stage'=>'before-next'|'terminal'|'after-next', 'payload'=>mixed]
     *       // decide when to continue...
     *   }
     *   $final = $iter->getReturn();
     */
    private function createCallChainIterator(
        array $yieldingOps,
        ?callable $terminalFn = null,
    ): callable {
        $terminalFn ??= fn(mixed $p): mixed => $p;

        return function (mixed $initial) use ($yieldingOps, $terminalFn): \Generator {
            // Instantiate and prime all operator coroutines.
            $gens = array_map(
                function (callable $op): \Generator {
                    $g = $op();   // create generator instance
                    $g->rewind(); // prime to first yield (waiting for inbound payload)
                    return $g;
                },
                $yieldingOps
            );

            $payload = $initial;

            // Forward pass: run each operator until it yields a payload for the next operator.
            foreach ($gens as $i => $g) {
                $g->send($payload);        // deliver inbound payload
                $payload = $g->current();  // outbound payload to the next operator
                yield ['index' => $i, 'stage' => 'before-next', 'payload' => $payload];
            }

            // Terminal step.
            $payload = $terminalFn($payload);
            yield ['index' => count($gens), 'stage' => 'terminal', 'payload' => $payload];

            // Backward pass: resume operators in reverse to allow post-processing.
            for ($i = count($gens) - 1; $i >= 0; $i--) {
                $g = $gens[$i];
                $g->send($payload);        // resume after the yield-to-next
                $payload = $g->getReturn(); // collect operator's return value
                yield ['index' => $i, 'stage' => 'after-next', 'payload' => $payload];
            }

            return $payload; // final result
        };
    }
}
