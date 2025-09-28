<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred;

use Cognesy\Experimental\Pipeline2\Contracts\CanProcessPayload;
use Cognesy\Experimental\Pipeline2\Contracts\OperatorFactory;
use Cognesy\Experimental\Pipeline2\Deferred\Actions\ExecuteCall;
use Cognesy\Experimental\Pipeline2\Deferred\Actions\ExecuteOperatorCall;
use Cognesy\Experimental\Pipeline2\Deferred\Actions\HandleBoundary;
use Cognesy\Experimental\Pipeline2\Deferred\Actions\IdentityFn;
use Cognesy\Experimental\Pipeline2\Deferred\Data\Boundary;
use Cognesy\Experimental\Pipeline2\Deferred\Data\ExecutionSnapshot;
use Cognesy\Experimental\Pipeline2\Deferred\Data\ResumeState;
use Cognesy\Experimental\Pipeline2\Deferred\Exceptions\SuspendSignal;
use Cognesy\Experimental\Pipeline2\Op;
use Cognesy\Experimental\Pipeline2\OperatorList;
use Cognesy\Experimental\Pipeline2\PipelineDefinition;

final class DeferredRuntime
{
    public function __construct(
        private OperatorFactory $operatorFactory
    ) {}

    /**
     * Drive execution from the current snapshot to the next checkpoint (one boundary),
     * then return the updated snapshot. Rebuilds callables from config each time.
     */
    public function nextStep(
        PipelineDefinition $definition,
        ExecutionSnapshot $snapshot,
        ?CanProcessPayload $terminalFn = null,
    ): ExecutionSnapshot {
        // rebuild pipeline
        $ops = $this->compile($definition);
        $terminalFn ??= new IdentityFn();
        $resumeState = $this->initResumeBoundary($snapshot);
        $call = $this->buildPipeline(
            operators: $ops,
            terminalFn: $terminalFn,
            atBoundaryFn: new HandleBoundary($snapshot, $resumeState)
        );
        // execute until next boundary or finish
        return $this->executeCall($call, $snapshot);
    }

    // INTERNAL //////////////////////////////////////////////

    private function compile(PipelineDefinition $definition): OperatorList {
        // Rehydrate operators from config to pure callables each tick.
        return OperatorList::with(...array_map(
            fn(Op $spec) => $this->operatorFactory->create($spec),
            iterator_to_array($definition),
        ));
    }

    private function initResumeBoundary(ExecutionSnapshot $snapshot): ResumeState {
        $skipUntil = $snapshot->index >= 0
            ? new Boundary($snapshot->index, $snapshot->stage)
            : null;
        $skipReached = ($skipUntil === null); // fresh run suspends at the first boundary
        return new ResumeState($skipUntil, $skipReached);
    }

    private function buildPipeline(
        OperatorList $operators,
        CanProcessPayload $terminalFn,
        HandleBoundary $atBoundaryFn
    ): CanProcessPayload {
        $operatorCount = $operators->count();
        $call = ExecuteCall::terminal(
            nextFn: $terminalFn,
            index: $operatorCount,
            atBoundaryFn: $atBoundaryFn,
        );
        for ($index = $operatorCount - 1; $index >= 0; $index--) {
            $operator = $operators->itemAt($index);
            $downstreamFn = $call;
            $call = new ExecuteOperatorCall(
                $operator, $downstreamFn, $index, $atBoundaryFn
            );
        }
        return $call;
    }

    /**
     * Execute the pipeline and translate a suspension into a snapshot, or mark finished.
     */
    private function executeCall(
        CanProcessPayload $call,
        ExecutionSnapshot $snapshot
    ): ExecutionSnapshot {
        try {
            $result = $call($snapshot->payload);
            return ExecutionSnapshot::finished($snapshot->executionId, $result);
        } catch (SuspendSignal $s) {
            return $s->snapshot;
        }
    }
}
