<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Contracts;

use Cognesy\Experimental\RLM\Data\RlmInvocation;
use Cognesy\Experimental\RLM\Data\RlmResult;

/**
 * Recursive Language Model — a single-call loop that plans/tools/writes/finalizes.
 */
interface RecursiveLanguageModel
{
    public function run(RlmInvocation $invocation): RlmResult;
}

