<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline\Base;

use Cognesy\Experimental\Pipeline\Contracts\Next;

final class PipelineContinuation implements Next
{
    public function __construct(
        private DefaultExecution $execution,
        private int $nextIndex,
    ) {}

    public function handle(mixed $payload): mixed {
        // Delegate back to the main execution object to run from the next index
        return $this->execution->runFrom($this->nextIndex, $payload);
    }
}