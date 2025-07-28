<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

/**
 * Represents a stamp that can be attached to an Envelope.
 * 
 * Stamps are immutable metadata objects that provide cross-cutting concerns
 * like observability, retry logic, error handling, and tracing information.
 * 
 * Each stamp implementation should be immutable and contain only
 * data relevant to its specific concern.
 */
interface StampInterface
{
    // Marker interface - stamps are identified by their type
}