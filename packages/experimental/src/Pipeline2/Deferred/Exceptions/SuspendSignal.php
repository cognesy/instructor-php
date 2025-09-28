<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Exceptions;

use Cognesy\Experimental\Pipeline2\Deferred\Data\ExecutionSnapshot;

final class SuspendSignal extends \RuntimeException
{
    public function __construct(public readonly ExecutionSnapshot $snapshot)
    {
        parent::__construct('Deferred execution suspended at a checkpoint.');
    }
}
