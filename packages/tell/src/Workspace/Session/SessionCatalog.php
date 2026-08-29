<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Session;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Render\ContentPreview;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;

/**
 * Read-only operator projection for workspace-backed named sessions.
 */
final readonly class SessionCatalog
{
    public function __construct(
        private FilesystemArena $arena,
        private HistoryCompiler $history = new HistoryCompiler(),
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(): array {
        $sessions = [];
        foreach ($this->arena->refNames('sessions') as $ref) {
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
    public function show(SessionId $sessionId, bool $full): ?array {
        $ref = new SessionRef($sessionId);
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
            $view['help'] = ['Run sessions show ' . $sessionId->toString() . ' with --full for complete messages.'];
        }

        return $view;
    }

    /** @return array<string, mixed>|null */
    private function viewForRef(string $ref): ?array {
        $head = $this->arena->readOptionalRef($ref)?->head;
        if ($head === null) {
            return null;
        }
        $history = $this->history->compile($this->arena, $head);
        if ($history->root === null) {
            return null;
        }
        $root = $this->arena->get($history->root);
        if (!$root instanceof ConversationRoot || $root->session() === null) {
            return null;
        }
        $metadata = $root->session();
        $expected = new SessionRef(SessionId::from($metadata->name()));
        if ($expected->refName() !== $ref) {
            throw new SessionException('Tell session ref does not match its canonical session metadata.');
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
            'source' => 'workspace',
        ];
    }
}
