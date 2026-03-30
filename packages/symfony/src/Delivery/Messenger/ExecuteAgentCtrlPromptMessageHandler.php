<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Messenger;

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntime;

final readonly class ExecuteAgentCtrlPromptMessageHandler
{
    public function __construct(
        private SymfonyAgentCtrlRuntime $runtime,
    ) {}

    public function __invoke(ExecuteAgentCtrlPromptMessage $message): AgentResponse
    {
        return $this->runtime->execute($message->prompt, $message->backend);
    }
}
