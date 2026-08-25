<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Render\ContentPreview;

/**
 * Read-only operator projection for workspace-backed named sessions.
 */
final readonly class WorkspaceSessionCatalog
{
    public function __construct(
        private ArenaStore $arena,
        private ArenaHistoryCompiler $history = new ArenaHistoryCompiler,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $sessions = [];
        foreach ($this->arena->sessionRefNames() as $ref) {
            $view = $this->viewForRef($ref);
            if ($view === null) {
                continue;
            }
            $sessions[$view['sessionId']] = $view;
        }
        ksort($sessions, SORT_STRING);

        return array_values($sessions);
    }

    /** @return array<string, mixed>|null */
    public function show(SessionId $sessionId, bool $full): ?array
    {
        $ref = new SessionCompatibilityRef($sessionId);
        $view = $this->viewForRef($ref->refName());
        if ($view === null) {
            return null;
        }
        $head = $this->arena->readRef($ref->refName())->head;
        if ($head === null) {
            return null;
        }
        $history = $this->history->compile($this->arena, $head);
        $preview = ContentPreview::from($history->messages->toString(), $full);

        $view['messageCount'] = $history->messages->count();
        $view['messageCharacters'] = $preview->characters;
        $view['messages'] = $preview->content;
        $view['truncated'] = $preview->truncated;
        if ($preview->truncated) {
            $view['help'] = ['Run sessions show '.$sessionId->toString().' with --full for complete messages.'];
        }

        return $view;
    }

    /** @return array<string, mixed>|null */
    private function viewForRef(string $ref): ?array
    {
        $head = $this->arena->readOptionalRef($ref)?->head;
        if ($head === null) {
            return null;
        }
        $history = $this->history->compile($this->arena, $head);
        if ($history->root === null) {
            return null;
        }
        $root = $this->arena->get($history->root);
        if (! $root instanceof CanonicalConversationRoot || $root->session() === null) {
            return null;
        }
        $metadata = $root->session();
        $expected = new SessionCompatibilityRef(SessionId::from($metadata->name()));
        if ($expected->refName() !== $ref) {
            throw new WorkspaceSessionException('Tell session ref does not match its canonical session metadata.');
        }

        return [
            'sessionId' => $metadata->name(),
            'status' => 'active',
            'version' => null,
            'agentName' => 'workspace',
            'agentLabel' => 'Workspace session',
            'createdAt' => null,
            'updatedAt' => null,
            'storage' => 'arena',
            'source' => $metadata->sourceFingerprint() === null ? 'workspace' : 'legacy-migrated',
        ];
    }
}
