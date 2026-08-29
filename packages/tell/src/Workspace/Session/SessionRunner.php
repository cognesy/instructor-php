<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Session;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Tell\Runtime\TellRunOutcome;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\RecordException;
use Cognesy\Tell\Workspace\Execution\TurnRunner;
use Generator;

/**
 * Selects one Arena-backed named session.
 */
final readonly class SessionRunner
{
    public function __construct(
        private FilesystemArena $arena,
    ) {}

    public function execute(
        SessionId $sessionId,
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
        SessionId $sessionId,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
        ?TellRunOutcome $outcome = null,
    ): Generator {
        try {
            $session = new SessionRef($sessionId);
            $session->metadata();
        } catch (RecordException $exception) {
            throw new SessionException(
                'Tell session name cannot be represented safely in a workspace.',
                previous: $exception,
            );
        }

        $states = (new TurnRunner(
            arena: $this->arena,
            ref: $session->refName(),
            session: $session->metadata(),
        ))->iterate($loop, $definition, $prompt, $outcome);
        foreach ($states as $state) {
            yield $state;
        }

        return $states->getReturn();
    }
}
