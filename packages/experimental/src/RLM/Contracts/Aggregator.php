<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Contracts;

use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;

/**
 * Deterministic reduction of handles to a single result handle.
 */
interface Aggregator
{
    /**
     * @param ResultHandle[] $handles
     */
    public function reduce(array $handles): ResultHandle;
}

