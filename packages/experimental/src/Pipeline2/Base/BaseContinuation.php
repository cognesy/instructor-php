<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Base;

use Cognesy\Experimental\Pipeline2\Contracts\Continuation;

final class BaseContinuation implements Continuation
{
    public function __construct(
        private BaseExecution $execution,
        private int $nextIndex,
    ) {}

    #[\Override]
    public function handle(mixed $payload): mixed {
        // Delegate back to the main execution object to run from the next index
        return $this->execution->runFrom($this->nextIndex, $payload);
    }
}