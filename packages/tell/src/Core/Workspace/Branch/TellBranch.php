<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Branch;

use Cognesy\Tell\Data\TellContext;
use Cognesy\Tell\Data\TellClearResult;
use Cognesy\Tell\Data\TellCompactionResult;
use Cognesy\Tell\Data\TellConversationView;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Core\Contract\Agent\CanBuildTellAgent;
use Cognesy\Tell\Core\Contract\Observation\CanTraceTellExecution;
use Cognesy\Tell\Core\Contract\Workspace\CanOpenTellWorkspace;
use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranch;
use Cognesy\Tell\Data\TellBranchInfo;
use Cognesy\Tell\Core\Workspace\Conversation\ContextInspector;
use Cognesy\Tell\Core\Workspace\Conversation\ConversationInspection;
use Cognesy\Tell\Core\Workspace\Conversation\ConversationReader;
use Cognesy\Tell\Core\Workspace\Compaction\CompactionRunner;
use Cognesy\Tell\Core\Workspace\TellRef;
use Cognesy\Tell\Core\Workspace\TellWorkspaceContext;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use InvalidArgumentException;

/** Read-only handle to one named Tell branch, independent of current checkout. */
final readonly class TellBranch implements CanUseTellBranch
{
    private const int MAX_HINT_CHARACTERS = 500;

    public string $name;

    public function __construct(
        private CanBuildTellAgent $agents,
        private CanTraceTellExecution $tracer,
        private CanOpenTellWorkspace $workspaces,
        private string $directory,
        string $name,
        private bool $invocationLocal = true,
    ) {
        $this->name = $name === 'main' ? 'main' : BranchName::fromStored($name)->toString();
        $this->inspection();
    }

    #[\Override]
    public function info(): TellBranchInfo {
        return (new TellBranches($this->workspaces, $this->directory))->show($this->name);
    }

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
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
            totalCount: count($inspection->history()->turns),
            truncated: count($inspection->history()->turns) > $limit,
        );
    }

    #[\Override]
    public function transcript(bool $full = false): TellConversationView {
        $inspection = $this->inspection();

        $history = $inspection->history();

        return new TellConversationView(
            selector: $inspection->selector(),
            head: $inspection->head()?->toString(),
            root: $history->root?->toString(),
            messages: $inspection->transcriptRows($full),
            toolCallCount: array_sum(array_map(
                static fn ($entry): int => count($entry->turn->toolCalls()),
                $history->turns,
            )),
            toolResultCount: array_sum(array_map(
                static fn ($entry): int => count($entry->turn->toolResults()),
                $history->turns,
            )),
        );
    }

    #[\Override]
    public function context(?TellRequest $request = null): TellContext {
        $request ??= TellRequest::prompt('Inspect Tell branch context.');
        $request = $this->inBranch($request);
        $definition = $this->agents->definition($request);

        return new TellContext((new ContextInspector())->inspect(
            conversation: $this->inspection(),
            definition: $definition,
            connection: $request->connection,
        ));
    }

    /** Pin the current branch head as an immutable read-only handle. */
    #[\Override]
    public function pin(): TellRef {
        $head = $this->inspection()->head();
        if ($head === null) {
            throw new WorkspaceException("Tell branch '{$this->name}' is empty and cannot be pinned.");
        }

        return new TellRef($this->agents, $this->workspaces, $this->directory, $head->toString());
    }

    /** Pin this branch's immutable conversation root. */
    #[\Override]
    public function root(): TellRef {
        $root = $this->inspection()->history()->root;
        if ($root === null) {
            throw new WorkspaceException("Tell branch '{$this->name}' is empty and has no conversation root.");
        }

        return new TellRef($this->agents, $this->workspaces, $this->directory, $root->toString());
    }

    #[\Override]
    public function clear(): TellClearResult {
        $inspection = $this->inspection();
        $previousHead = $inspection->head();
        $result = $this->workspace()->arena->compareAndSwapToEmpty($this->refName(), $previousHead);

        return new TellClearResult(
            selector: $inspection->selector(),
            previousHead: $previousHead?->toString(),
            head: $result->head?->toString(),
        );
    }

    #[\Override]
    public function compact(TellRequest $request, string $hint = ''): TellCompactionResult {
        if (mb_strlen($hint) > self::MAX_HINT_CHARACTERS) {
            throw new InvalidArgumentException('Tell compact hint must be at most ' . self::MAX_HINT_CHARACTERS . ' characters.');
        }
        $request = $this->inBranch($request);
        $definition = $this->agents->definition($request);
        $this->agents->assertReady($request);
        $loop = $this->agents->build($request, definition: $definition);
        $this->tracer->attach($loop, $request);
        $result = (new CompactionRunner(
            arena: $this->workspace()->arena,
            ref: $this->refName(),
        ))->execute($loop, $definition, $hint);

        return new TellCompactionResult($this->inspection()->selector(), $result->toArray());
    }

    private function inspection(): ConversationInspection {
        $workspace = $this->workspace();
        $arena = $workspace->arena;
        $selection = (new BranchResolver($arena, $workspace->branchSelection))->resolve(
            $this->invocationLocal ? $this->name : null,
            allowTellOwned: true,
        );

        return (new ConversationReader($arena))->read(branch: $selection);
    }

    private function workspace(): TellWorkspaceContext {
        return $this->workspaces->open($this->directory);
    }

    private function refName(): string {
        return $this->name === 'main' ? 'main' : 'branches/' . $this->name;
    }

    private function inBranch(TellRequest $request): TellRequest {
        $request = $request->directory === '' ? $request->withDirectory($this->directory) : $request;

        return $this->invocationLocal ? $request->branch($this->name) : $request;
    }
}
