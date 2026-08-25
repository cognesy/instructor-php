<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalValidationException;
use Cognesy\Tell\Runtime\TellPaths;
use Generator;

/**
 * Selects one workspace-native named session and imports legacy state once.
 */
final readonly class WorkspaceSessionRunner
{
    public function __construct(
        private ArenaStore $arena,
        private TellPaths $paths,
        private LegacySessionMigrator $migrator = new LegacySessionMigrator,
        private ArenaHistoryCompiler $history = new ArenaHistoryCompiler,
    ) {}

    public function execute(
        SessionId $sessionId,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): WorkspaceSessionExecution {
        $states = $this->iterate($sessionId, $loop, $definition, $prompt);
        foreach ($states as $_) {
        }

        return $states->getReturn();
    }

    /** @return Generator<int, \Cognesy\Agents\Data\AgentState, mixed, WorkspaceSessionExecution> */
    public function iterate(
        SessionId $sessionId,
        AgentLoop $loop,
        AgentDefinition $definition,
        string $prompt,
    ): Generator {
        try {
            $compatibility = new SessionCompatibilityRef($sessionId);
            // Validate the display name before touching either persistent store.
            $compatibility->metadata();
        } catch (CanonicalValidationException $exception) {
            throw new WorkspaceSessionException(
                'Tell session name cannot be represented safely in a workspace.',
                previous: $exception,
            );
        }

        $existing = $this->arena->readOptionalRef($compatibility->refName());
        $warnings = [];
        if ($existing === null) {
            $snapshot = (new LegacySessionSource($this->paths))->snapshot($sessionId);
            if ($snapshot !== null) {
                $this->migrator->migrate($this->arena, $compatibility, $snapshot);
            }
        } elseif ($existing->head !== null) {
            $warnings = $this->warningsForChangedLegacySource($compatibility, $existing->head);
        }

        $states = (new WorkspaceTurnRunner(
            arena: $this->arena,
            ref: $compatibility->refName(),
            session: $compatibility->metadata(),
        ))->iterate($loop, $definition, $prompt);
        foreach ($states as $state) {
            yield $state;
        }

        return new WorkspaceSessionExecution($states->getReturn(), $warnings);
    }

    /** @return list<string> */
    private function warningsForChangedLegacySource(
        SessionCompatibilityRef $compatibility,
        CanonicalHash $head,
    ): array {
        $history = $this->history->compile($this->arena, $head);
        if ($history->root === null) {
            return [];
        }
        $root = $this->arena->get($history->root);
        if (! $root instanceof CanonicalConversationRoot || $root->session() === null) {
            return [];
        }
        $source = (new LegacySessionSource($this->paths))->sourceFingerprint($compatibility->session());
        $migrated = $root->session()->sourceFingerprint();
        if ($source === null || $migrated === null || $source->equals($migrated)) {
            return [];
        }

        return [
            'Legacy session source changed after migration; workspace arena history remains authoritative.',
        ];
    }
}
