<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Conversation\ContextInspector;
use Cognesy\Tell\Workspace\Conversation\ConversationInspection;
use Cognesy\Tell\Workspace\Conversation\ConversationReader;
use Cognesy\Tell\Workspace\TellRef;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceState as StoredWorkspace;
use InvalidArgumentException;

/** Read-only handle to one named Tell branch, independent of current checkout. */
final readonly class TellBranch
{
    public string $name;

    public function __construct(
        private TellAgentFactory $agents,
        private string $directory,
        string $name,
    ) {
        $this->name = $name === 'main' ? 'main' : BranchName::from($name)->toString();
        $this->inspection();
    }

    public function info(): TellBranchInfo {
        return (new TellBranches($this->agents, $this->directory))->show($this->name);
    }

    public function history(int $limit = 20, bool $full = false): TellConversationView {
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Tell history limit must be between 1 and 100.');
        }
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            turns: array_slice($inspection->historyRows($full), -$limit),
        );
    }

    public function transcript(bool $full = false): TellConversationView {
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            messages: $inspection->transcriptRows($full),
        );
    }

    public function context(?TellRequest $request = null): TellContext {
        $request ??= TellRequest::prompt('Inspect Tell branch context.');
        $request = $request->directory === '' ? $request->withDirectory($this->directory) : $request;
        $definition = $this->agents->definition($request->toOptions());

        return new TellContext((new ContextInspector())->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    /** Pin the current branch head as an immutable read-only handle. */
    public function pin(): TellRef {
        $head = $this->inspection()->head();
        if ($head === null) {
            throw new WorkspaceException("Tell branch '{$this->name}' is empty and cannot be pinned.");
        }

        return new TellRef($this->agents, $this->directory, $head->toString());
    }

    /** Pin this branch's immutable conversation root. */
    public function root(): TellRef {
        $root = $this->inspection()->history()->root;
        if ($root === null) {
            throw new WorkspaceException("Tell branch '{$this->name}' is empty and has no conversation root.");
        }

        return new TellRef($this->agents, $this->directory, $root->toString());
    }

    private function inspection(): ConversationInspection {
        $workspace = $this->workspace();
        $arena = new FilesystemArena($workspace);
        $selection = (new BranchResolver($arena, $workspace))->resolve($this->name);

        return (new ConversationReader($arena))->read(branch: $selection);
    }

    private function workspace(): StoredWorkspace {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            throw new WorkspaceException('Tell branch inspection requires an initialized workspace. Call workspace()->initialize() first.');
        }

        return $workspace;
    }
}
