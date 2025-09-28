<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Data;

use Cognesy\Experimental\Pipeline2\Deferred\Enums\ExecutionStage;

/**
 * Manages the resume state of a pipeline execution.
 */
class ResumeState
{
    public function __construct(
        public ?Boundary $skipUntil,
        public bool $skipReached,
    ) {}

    public function trySuspend(int $index, ExecutionStage $stage) : bool {
        if (!$this->skipReached) {
            if ($this->skipUntil?->isReached($index, $stage)) {
                $this->skipReached = true; // resume past the saved boundary
            }
            return false;
        }
        return true;
    }
}

