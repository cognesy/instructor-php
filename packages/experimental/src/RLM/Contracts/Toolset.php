<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Contracts;

use Cognesy\Experimental\RLM\Data\Handles\ResultHandle;

/**
 * Toolset callable by the model; must return handles, never large blobs.
 */
interface Toolset
{
    /**
     * @param array<string,mixed> $args
     */
    public function call(string $name, array $args): ResultHandle;
}

