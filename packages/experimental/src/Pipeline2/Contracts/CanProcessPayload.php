<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Contracts;

/**
 * Interface for processing payloads in a pipeline.
 *
 * This interface defines a contract for classes that can process a given payload.
 * Implementing classes should provide logic to handle and potentially modify the payload.
 *
 * @template TPayload
 */
interface CanProcessPayload
{
    /**
     * Processes the given payload and returns the (potentially modified) payload.
     *
     * @param mixed<TPayload> $payload The current payload to be processed.
     * @return mixed<TPayload> The processed payload.
     */
    public function __invoke(mixed $payload): mixed;
}
