<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Continuation;

/**
 * Generic continuation criteria contract.
 * 
 * @template TState of object
 */
interface CanDecideToContinue
{
    /**
     * Determine if the process should continue based on current state.
     * 
     * @param TState $state
     * @return bool True if process should continue, false to stop
     */
    public function canContinue(object $state): bool;
}