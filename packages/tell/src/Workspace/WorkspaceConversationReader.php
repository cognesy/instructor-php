<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\SessionId;

/**
 * Resolves the public default or named-session selector into verified arena history.
 */
final readonly class WorkspaceConversationReader
{
    public function __construct(
        private ArenaStore $arena,
        private ArenaHistoryCompiler $history = new ArenaHistoryCompiler,
    ) {}

    public function read(?SessionId $sessionId = null): WorkspaceConversationInspection
    {
        $compatibility = $sessionId === null ? null : new SessionCompatibilityRef($sessionId);
        $reference = $this->arena->readOptionalRef($compatibility?->refName() ?? 'main') ?? ArenaRef::empty();

        return new WorkspaceConversationInspection(
            sessionId: $sessionId,
            head: $reference->head,
            history: $this->history->compile($this->arena, $reference->head),
        );
    }
}
