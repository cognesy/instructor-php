<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Contracts;

/**
 * [R1x] Represents a single, stateless operation within a pipeline.
 *
 * Operators implement the middleware pattern, allowing them to perform logic
 * before and after passing the payload to the rest of the pipeline chain.
 */
interface Operator
{
    /**
     * Determines if this operator can process the given payload.
     * The runtime will skip operators that return false.
     *
     * @param mixed $payload The current data being processed.
     * @return bool True if the operator can handle the payload, false otherwise.
     */
    public function supports(mixed $payload): bool;

    /**
     * Processes the payload using "around" middleware semantics.
     *
     * @param mixed $payload The input data for this step.
     * @param Continuation $next A callable representing the rest of the pipeline.
     * Invoking `$next->handle($payload)` continues execution.
     * @return mixed The result of the operation.
     */
    public function handle(mixed $payload, Continuation $next): mixed;
}