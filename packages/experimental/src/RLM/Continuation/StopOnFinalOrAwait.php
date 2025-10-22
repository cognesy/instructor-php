<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Continuation;

use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;

/**
 * Stop when state indicates final or await has been reached.
 *
 * This is a lightweight criterion; the concrete state class should expose
 * a simple boolean for terminal/await conditions.
 */
final class StopOnFinalOrAwait implements CanDecideToContinue
{
    #[\Override]
    public function canContinue(object $state): bool {
        if (!method_exists($state, 'isTerminal')) {
            return true;
        }
        /** @var bool $terminal */
        $terminal = (bool) $state->isTerminal();
        return !$terminal;
    }
}

