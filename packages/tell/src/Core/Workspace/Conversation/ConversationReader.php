<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Conversation;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Core\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Core\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Core\Workspace\Arena\Ref;
use Cognesy\Tell\Core\Workspace\Branch\ResolvedBranch;
use Cognesy\Tell\Core\Workspace\Session\SessionRef;
use InvalidArgumentException;

/**
 * Resolves the public default or named-session selector into verified arena history.
 */
final readonly class ConversationReader
{
    public function __construct(
        private CanUseTellWorkspaceArena $arena,
        private HistoryCompiler $history = new HistoryCompiler(),
    ) {}

    public function read(?SessionId $sessionId = null, ?ResolvedBranch $branch = null): ConversationInspection {
        if ($sessionId !== null && $branch !== null) {
            throw new InvalidArgumentException('--branch and --session cannot be used together.');
        }
        $session = $sessionId === null ? null : new SessionRef($sessionId);
        $reference = $this->arena->readOptionalRef($session?->refName() ?? $branch->ref ?? 'main') ?? Ref::empty();

        return new ConversationInspection(
            sessionId: $sessionId,
            head: $reference->head,
            history: $this->history->compile($this->arena, $reference->head),
            branch: $branch,
        );
    }

    /** Read one immutable canonical conversation head or root without consulting a mutable ref. */
    public function readImmutable(ObjectHash $reference): ConversationInspection {
        return new ConversationInspection(
            sessionId: null,
            head: $reference,
            history: $this->history->compile($this->arena, $reference),
            immutableRef: $reference,
        );
    }
}
