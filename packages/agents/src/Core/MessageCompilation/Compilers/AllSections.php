<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\MessageCompilation\Compilers;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\MessageCompilation\CanCompileMessages;
use Cognesy\Messages\Messages;

final class AllSections implements CanCompileMessages
{
    #[\Override]
    public function compile(AgentState $state): Messages
    {
        return $state->store()->toMessages();
    }
}
