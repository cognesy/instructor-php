<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Actions;

use Cognesy\Agents\Capability\Tasks\TaskList;
use Cognesy\Agents\Capability\Tasks\TodoWriteTool;
use Cognesy\Agents\Session\AgentSession;
use Cognesy\Agents\Session\Contracts\CanExecuteSessionAction;

final readonly class UpdateTask implements CanExecuteSessionAction
{
    public function __construct(
        private TaskList $taskList,
    ) {}

    public static function fromArray(array $tasks): self {
        return new self(TaskList::fromArray($tasks));
    }

    #[\Override]
    public function executeOn(AgentSession $session): AgentSession {
        return $session->withState(
            $session->state()->withMetadata(TodoWriteTool::metadataKey(), $this->taskList->toArray())
        );
    }
}
