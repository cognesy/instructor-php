<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

/**
 * Represents a tag that can be attached to a ProcessingState.
 * 
 * Tags are immutable metadata objects that provide cross-cutting concerns
 * like observability, retry logic, error handling, and tracing information.
 * 
 * Each tag implementation should be immutable and contain only
 * data relevant to its specific concern.
 */
interface TagInterface
{
    // Marker interface - tags are identified by their type
}