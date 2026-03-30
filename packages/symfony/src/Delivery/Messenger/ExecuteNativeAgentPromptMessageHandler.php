<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Messenger;

use Cognesy\Agents\Session\Actions\SendMessage;
use Cognesy\Agents\Session\Contracts\CanManageAgentSessions;
use Cognesy\Agents\Session\Data\AgentSession;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Contracts\CanInstantiateAgentLoop;
use Cognesy\Agents\Template\Contracts\CanManageAgentDefinitions;

final readonly class ExecuteNativeAgentPromptMessageHandler
{
    public function __construct(
        private CanManageAgentDefinitions $definitions,
        private CanManageAgentSessions $sessions,
        private CanInstantiateAgentLoop $loopFactory,
    ) {}

    public function __invoke(ExecuteNativeAgentPromptMessage $message): AgentSession
    {
        $sessionId = match ($message->sessionId) {
            null => $this->sessions->create($this->definitions->get($message->definition))->sessionId(),
            default => SessionId::from($message->sessionId),
        };

        return $this->sessions->execute($sessionId, new SendMessage(
            message: $message->prompt,
            loopFactory: $this->loopFactory,
        ));
    }
}
