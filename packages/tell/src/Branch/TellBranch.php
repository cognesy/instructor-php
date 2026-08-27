<?php

declare(strict_types=1);

namespace Cognesy\Tell\Branch;

use Cognesy\Tell\TellContext;
use Cognesy\Tell\TellConversationView;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchName;
use Cognesy\Tell\Workspace\BranchResolver;
use Cognesy\Tell\Workspace\TellWorkspace as StoredWorkspace;
use Cognesy\Tell\Workspace\WorkspaceContextInspector;
use Cognesy\Tell\Workspace\WorkspaceConversationInspection;
use Cognesy\Tell\Workspace\WorkspaceConversationReader;
use Cognesy\Tell\Workspace\WorkspaceException;

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

    public function info(): TellBranchInfo
    {
        return (new TellBranches($this->agents, $this->directory))->show($this->name);
    }

    public function history(int $limit = 20, bool $full = false): TellConversationView
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Tell history limit must be between 1 and 100.');
        }
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            turns: array_slice($inspection->historyRows($full), -$limit),
        );
    }

    public function transcript(bool $full = false): TellConversationView
    {
        $inspection = $this->inspection();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $inspection->history()->root?->toString(),
            messages: $inspection->transcriptRows($full),
        );
    }

    public function context(?TellRequest $request = null): TellContext
    {
        $request ??= TellRequest::prompt('Inspect Tell branch context.');
        $request = $request->directory === '' ? $request->withDirectory($this->directory) : $request;
        $definition = $this->agents->definition($request->toOptions());

        return new TellContext((new WorkspaceContextInspector)->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    /** Pin the current branch head as an immutable read-only handle. */
    public function pin(): TellRef
    {
        $head = $this->inspection()->head();
        if ($head === null) {
            throw new WorkspaceException("Tell branch '{$this->name}' is empty and cannot be pinned.");
        }

        return new TellRef($this->agents, $this->directory, $head->toString());
    }

    /** Pin this branch's immutable conversation root. */
    public function root(): TellRef
    {
        $root = $this->inspection()->history()->root;
        if ($root === null) {
            throw new WorkspaceException("Tell branch '{$this->name}' is empty and has no conversation root.");
        }

        return new TellRef($this->agents, $this->directory, $root->toString());
    }

    private function inspection(): WorkspaceConversationInspection
    {
        $arena = new ArenaStore($this->workspace());
        $selection = (new BranchResolver($arena))->resolve($this->name);

        return (new WorkspaceConversationReader($arena))->read(branch: $selection);
    }

    private function workspace(): StoredWorkspace
    {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            throw new WorkspaceException('Tell branch inspection requires an initialized workspace. Call workspace()->initialize() first.');
        }

        return $workspace;
    }
}
