<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Contracts;

use Cognesy\Experimental\RLM\Data\RlmInvocation;
use Cognesy\Experimental\RLM\Data\RlmResult;

/**
 * Runtime/controller that builds recursion trees, aggregates results, enforces policies.
 */
interface ExecutionRuntime
{
    public function execute(RlmInvocation $root): RlmResult;
}

