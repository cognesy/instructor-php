<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\MessageCompilation;

use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Messages\Messages;

/**
 * Compile a message view from a state carrying a message store.
 *
 * @template TState of HasMessageStore
 */
interface CanCompileMessages
{
    /**
     * @param HasMessageStore $state state providing access to a message store
     */
    public function compile(HasMessageStore $state): Messages;
}
