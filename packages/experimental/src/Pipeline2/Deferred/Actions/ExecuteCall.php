<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Actions;

use Cognesy\Experimental\Pipeline2\Contracts\CanProcessPayload;
use Cognesy\Experimental\Pipeline2\Deferred\Enums\ExecutionStage;

/**
 * An invokable class that handles the "next" call in the pipeline,
 * wrapping the downstream call with boundary checkpoints.
 */
class ExecuteCall
{
    private function __construct(
        private CanProcessPayload $nextFn,
        private int $index,
        private HandleBoundary $atBoundaryFn,
        private ExecutionStage $beforeStage,
        private ExecutionStage $afterStage,
    ) {}

    public static function next(
        CanProcessPayload $nextFn,
        int $index,
        HandleBoundary $atBoundaryFn,
    ) : ExecuteCall {
        return new self(
            nextFn: $nextFn,
            index: $index,
            atBoundaryFn: $atBoundaryFn,
            beforeStage: ExecutionStage::BeforeNext,
            afterStage: ExecutionStage::AfterNext,
        );
    }

    public static function terminal(
        CanProcessPayload $nextFn,
        int $index,
        HandleBoundary $atBoundaryFn,
    ) : ExecuteCall {
        return new self(
            nextFn: $nextFn,
            index: $index,
            atBoundaryFn: $atBoundaryFn,
            beforeStage: ExecutionStage::TerminalIn,
            afterStage: ExecutionStage::Terminal,
        );
    }

    public function __invoke(mixed $payload): mixed {
        $payload = ($this->atBoundaryFn)(
            index: $this->index,
            stage: $this->beforeStage,
            payload: $payload
        );
        $payload = ($this->nextFn)($payload);
        $payload = ($this->atBoundaryFn)(
            index: $this->index,
            stage: $this->afterStage,
            payload: $payload
        );
        return $payload;
    }
}
