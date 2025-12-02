<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Exception;

/**
 * Dependency Cycle Exception
 *
 * Thrown when a circular dependency is detected or would be created.
 */
final class DependencyCycleException extends BeadsException
{
    /**
     * @param  array<string>  $cycle  Task IDs forming the cycle
     */
    public function __construct(
        string $message,
        public readonly array $cycle = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string>  $cycle
     */
    public static function detected(array $cycle): self
    {
        $cycleStr = implode(' -> ', $cycle);

        return new self(
            "Circular dependency detected: {$cycleStr}",
            $cycle,
        );
    }

    /**
     * @param  array<string>  $cycle
     */
    public static function wouldCreate(array $cycle): self
    {
        $cycleStr = implode(' -> ', $cycle);

        return new self(
            "Adding this dependency would create a cycle: {$cycleStr}",
            $cycle,
        );
    }
}
