<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\MessageCompilation\Compilers;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\MessageCompilation\CanCompileMessages;
use Cognesy\Messages\Messages;

final class AllSections implements CanCompileMessages
{
    #[\Override]
    public function compile(AgentState $state): Messages
    {
        return $state->store()->toMessages();
    }
}
