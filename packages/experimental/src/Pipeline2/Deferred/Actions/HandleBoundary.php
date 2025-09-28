<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Actions;

use Cognesy\Experimental\Pipeline2\Deferred\Data\ExecutionSnapshot;
use Cognesy\Experimental\Pipeline2\Deferred\Data\ResumeState;
use Cognesy\Experimental\Pipeline2\Deferred\Enums\ExecutionStage;
use Cognesy\Experimental\Pipeline2\Deferred\Exceptions\SuspendSignal;

/**
 * An invokable class that handles pipeline suspension and resumption at boundaries.
 */
class HandleBoundary
{
    public function __construct(
        private ExecutionSnapshot $snapshot,
        private ResumeState $resumeState,
    ) {}

    public function __invoke(
        int $index,
        ExecutionStage $stage,
        mixed $payload
    ): mixed {
        if (!$this->resumeState->trySuspend($index, $stage)) {
            return $payload;
        }
        // Suspend at the first boundary after skipUntil.
        throw new SuspendSignal(ExecutionSnapshot::progress(
            executionId: $this->snapshot->executionId,
            index: $index,
            stage: $stage,
            payload: $payload,
        ));
    }
}
