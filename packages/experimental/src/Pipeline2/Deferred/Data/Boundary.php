<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Data;

use Cognesy\Experimental\Pipeline2\Deferred\Enums\ExecutionStage;

/**
 * Represents a specific checkpoint in the pipeline's execution.
 */
class Boundary
{
    public function __construct(
        public int $index,
        public ExecutionStage $stage,
    ) {}

    public function isReached(int $index, ExecutionStage $stage) : bool {
        return $this->index === $index && $this->stage === $stage;
    }
}

