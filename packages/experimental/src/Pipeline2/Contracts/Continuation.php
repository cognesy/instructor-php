<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Contracts;

/**
 * Represents the next step in the processing chain.
 *
 * An implementation of this interface is passed to an Operator's handle method,
 * allowing the operator to delegate processing to the rest of the chain.
 */
interface Continuation
{
    /**
     * Passes the payload to the next processor in the chain.
     *
     * @param mixed $payload The payload to process.
     * @return mixed The result of the rest of the processing chain.
     */
    public function handle(mixed $payload): mixed;
}
