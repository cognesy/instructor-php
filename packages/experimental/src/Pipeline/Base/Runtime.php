<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Base;

use Cognesy\Experimental\Pipeline\Contracts\Execution;
use Cognesy\Experimental\Pipeline\Contracts\Observer;
use Cognesy\Experimental\Pipeline\Contracts\OperatorFactory;
use Cognesy\Experimental\Pipeline\PipelineDefinition;

final class Runtime
{
    public function __construct(
        private OperatorFactory $operatorFactory,
        private ?Observer $observer = null,
    ) {}

    public static function new(?Observer $observer = null): self {
        return new self(new DefaultOperatorFactory(), $observer);
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
        $operators = array_map(
            fn($spec) => $this->operatorFactory->create($spec),
            iterator_to_array($definition),
        );

        return new DefaultExecution(
            $operators,
            $initialPayload,
            $terminal ?? fn(mixed $payload): mixed => $payload, // Default identity terminal
            $this->observer,
        );
    }
}
