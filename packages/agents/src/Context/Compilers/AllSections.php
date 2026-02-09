<?php declare(strict_types=1);

namespace Cognesy\Agents\Context\Compilers;

use Cognesy\Agents\Core\Contracts\CanCompileMessages;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Messages\Messages;

final class AllSections implements CanCompileMessages
{
    #[\Override]
    public function compile(AgentState $state): Messages
    {
        return $state->store()->toMessages();
    }
}
