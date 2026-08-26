<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Canonical\CanonicalHash;

/**
 * Resolves the public default or named-session selector into verified arena history.
 */
final readonly class WorkspaceConversationReader
{
    public function __construct(
        private ArenaStore $arena,
        private ArenaHistoryCompiler $history = new ArenaHistoryCompiler,
    ) {}

    public function read(?SessionId $sessionId = null, ?BranchSelection $branch = null): WorkspaceConversationInspection
    {
        if ($sessionId !== null && $branch !== null) {
            throw new \InvalidArgumentException('--branch and --session cannot be used together.');
        }
        $compatibility = $sessionId === null ? null : new SessionCompatibilityRef($sessionId);
        $reference = $this->arena->readOptionalRef($compatibility?->refName() ?? $branch->ref ?? 'main') ?? ArenaRef::empty();

        return new WorkspaceConversationInspection(
            sessionId: $sessionId,
            head: $reference->head,
            history: $this->history->compile($this->arena, $reference->head),
            branch: $branch,
        );
    }

    /** Read one immutable canonical conversation head or root without consulting a mutable ref. */
    public function readImmutable(CanonicalHash $reference): WorkspaceConversationInspection
    {
        return new WorkspaceConversationInspection(
            sessionId: null,
            head: $reference,
            history: $this->history->compile($this->arena, $reference),
            immutableRef: $reference,
        );
    }
}
