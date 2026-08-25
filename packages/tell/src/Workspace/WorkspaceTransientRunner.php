<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Messages\Messages;
use Cognesy\Tell\Canonical\CanonicalValidationException;
use Cognesy\Tell\Runtime\TellPaths;

/**
 * Executes against selected durable context without mutating either state store.
 */
final readonly class WorkspaceTransientRunner
{
    public function __construct(
        private ArenaStore $arena,
        private TellPaths $paths,
        private ArenaHistoryCompiler $history = new ArenaHistoryCompiler,
    ) {}

    public function execute(
        ?SessionId $sessionId,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): AgentState {
        $messages = $this->messages($sessionId);
        $seed = (new DefinitionStateFactory)
            ->instantiateAgentState($definition)
            ->withMessages($messages)
            ->withUserMessage($prompt);

        return $loop->execute($seed);
    }

    private function messages(?SessionId $sessionId): Messages
    {
        if ($sessionId === null) {
            $reference = $this->arena->readOptionalRef('main') ?? ArenaRef::empty();

            return $this->history->compile($this->arena, $reference->head)->messages;
        }

        try {
            $compatibility = new SessionCompatibilityRef($sessionId);
            // Match durable named-session validation without triggering migration.
            $compatibility->metadata();
        } catch (CanonicalValidationException $exception) {
            throw new WorkspaceSessionException(
                'Tell session name cannot be represented safely in a workspace.',
                previous: $exception,
            );
        }

        $reference = $this->arena->readOptionalRef($compatibility->refName());
        if ($reference !== null) {
            return $this->history->compile($this->arena, $reference->head)->messages;
        }

        // A pre-arena session remains a read-only compatibility source here.
        // Unlike WorkspaceSessionRunner, transient execution never imports it.
        $snapshot = (new LegacySessionSource($this->paths))->snapshot($sessionId);

        return $snapshot?->session->state()->messages() ?? Messages::empty();
    }
}
