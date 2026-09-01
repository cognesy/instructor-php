<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Session;

use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Core\Contract\Workspace\CanManageTellSessions;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Data\TellSessionRemoval;
use Cognesy\Tell\Data\TellSessionView;
use InvalidArgumentException;

/** Application-facing session inventory and lifecycle operations. */
final readonly class TellSessions implements CanManageTellSessions
{
    public function __construct(
        private CanOpenTellWorkspace $workspaces,
        private string $directory,
    ) {}

    /** @return list<TellSessionView> */
    #[\Override]
    public function list(): array {
        if ($this->workspaces->discover($this->directory) === null) {
            return [];
        }

        return array_map(
            static fn (array $session): TellSessionView => new TellSessionView($session),
            (new SessionCatalog($this->workspaces->open($this->directory)->arena))->list(),
        );
    }

    #[\Override]
    public function show(string $id, bool $full = false): ?TellSessionView {
        $catalog = new SessionCatalog($this->workspace()->arena);
        $view = $catalog->show(SessionId::from($id), $full);

        return $view === null ? null : new TellSessionView($view);
    }

    #[\Override]
    public function remove(string $id): TellSessionRemoval {
        $session = SessionId::from($id);
        $arena = $this->workspace()->arena;
        $ref = (new SessionRef($session))->refName();
        $reference = $arena->readOptionalRef($ref);
        $removed = $reference?->head !== null;
        if ($removed) {
            $arena->compareAndSwapToEmpty($ref, $reference->head);
        }

        return new TellSessionRemoval($session->toString(), $removed);
    }

    private function workspace(): \Cognesy\Tell\Core\Workspace\TellWorkspaceContext {
        if ($this->workspaces->discover($this->directory) === null) {
            throw new InvalidArgumentException('Tell sessions require an initialized workspace.');
        }

        return $this->workspaces->open($this->directory);
    }
}
