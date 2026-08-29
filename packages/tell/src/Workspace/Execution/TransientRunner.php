<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Execution;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Messages\Messages;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Workspace\Arena\RecordException;
use Cognesy\Tell\Workspace\Arena\Ref;
use Cognesy\Tell\Workspace\Session\SessionException;
use Cognesy\Tell\Workspace\Session\SessionRef;
use Generator;

/**
 * Executes against selected durable context without mutating either state store.
 */
final readonly class TransientRunner
{
    public function __construct(
        private FilesystemArena $arena,
        private HistoryCompiler $history = new HistoryCompiler(),
        private string $ref = 'main',
    ) {}

    public function execute(
        ?SessionId $sessionId,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): AgentState {
        $states = $this->iterate($sessionId, $loop, $definition, $prompt);
        foreach ($states as $_) {
        }

        return $states->getReturn();
    }

    /** @return Generator<int, AgentState, mixed, AgentState> */
    public function iterate(
        ?SessionId $sessionId,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator {
        $messages = $this->messages($sessionId);
        $seed = (new DefinitionStateFactory())
            ->instantiateAgentState($definition)
            ->withMessages($messages)
            ->withUserMessage($prompt);

        $state = $seed;
        foreach ($loop->iterate($seed) as $checkpoint) {
            $state = $checkpoint;
            yield $checkpoint;
        }

        return $state;
    }

    private function messages(?SessionId $sessionId): Messages {
        if ($sessionId === null) {
            $reference = $this->arena->readOptionalRef($this->ref) ?? Ref::empty();

            return $this->history->compile($this->arena, $reference->head)->messages;
        }

        try {
            $session = new SessionRef($sessionId);
            $session->metadata();
        } catch (RecordException $exception) {
            throw new SessionException(
                'Tell session name cannot be represented safely in a workspace.',
                previous: $exception,
            );
        }

        $reference = $this->arena->readOptionalRef($session->refName()) ?? Ref::empty();

        return $this->history->compile($this->arena, $reference->head)->messages;
    }
}
