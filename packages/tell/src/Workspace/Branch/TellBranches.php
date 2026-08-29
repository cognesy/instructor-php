<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Provenance;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Turn;
use Cognesy\Tell\Workspace\Arena\Ref;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use Cognesy\Tell\Workspace\Branch\Storage\BranchCurrentSelectionStore;
use Cognesy\Tell\Workspace\Branch\Storage\BranchStore;
use Cognesy\Tell\Workspace\WorkspaceState as StoredWorkspace;
use InvalidArgumentException;

/**
 * Developer-facing branch controls for an initialized Tell workspace.
 *
 * These operations retain immutable history and use the same validated refs
 * and compare-and-swap rules as the Tell CLI, without exposing storage paths.
 */
final readonly class TellBranches
{
    private const int MAX_RESET_STEPS = 1_000;
    private const int MAX_LINEAGE_DEPTH = 1_000;

    public function __construct(
        private TellAgentFactory $agents,
        private string $directory,
    ) {}

    /** @return list<TellBranchInfo> */
    public function list(bool $full = false): array {
        $workspace = $this->workspace();
        $store = new FilesystemArena($workspace);
        $current = (new BranchResolver($store, $workspace))->resolve()->branch;
        $catalog = new BranchCatalog($store);

        return array_map(
            fn (array $branch): TellBranchInfo => $this->info($branch, $current, $full),
            $catalog->list($full),
        );
    }

    public function show(string $name): TellBranchInfo {
        $workspace = $this->workspace();
        $store = new FilesystemArena($workspace);
        $current = (new BranchResolver($store, $workspace))->resolve()->branch;

        if ($name === 'main') {
            $reference = $store->readRef();
            $history = (new HistoryCompiler())->compile($store, $reference->head);

            return new TellBranchInfo(
                name: 'main',
                head: $reference->head?->toString(),
                empty: $reference->head === null,
                turnCount: count($history->turns),
                current: $current === 'main',
            );
        }
        $branch = BranchName::fromStored($name);

        return $this->info((new BranchCatalog($store))->show($branch), $current, true);
    }

    public function current(): TellBranchSelection {
        $workspace = $this->workspace();
        $selection = (new BranchResolver(new FilesystemArena($workspace), $workspace))->resolve();

        return new TellBranchSelection($selection->branch, 'current');
    }

    public function create(string $name, ?string $from = null, bool $empty = false): TellBranchInfo {
        if ($empty && $from !== null) {
            throw new InvalidArgumentException('An empty Tell branch cannot also specify a source branch.');
        }
        $workspace = $this->workspace();
        $store = new FilesystemArena($workspace);
        $branch = BranchName::from($name);
        $sourceName = match (true) {
            $empty => null,
            $from !== null => BranchName::from($from)->toString(),
            default => (new BranchResolver($store, $workspace))->resolve()->branch,
        };
        $source = match (true) {
            $empty => new Ref(null, new Provenance('empty', null, null)),
            $from !== null => $this->fromBranch($store, BranchName::from($from)),
            default => $this->fromCurrent($store, $workspace),
        };
        (new BranchStore($store, new BranchCurrentSelectionStore($workspace)))->create($branch, $source);
        if ($sourceName !== null) {
            (new BranchConfigStore($workspace))->inherit($sourceName, $branch->toString());
        }

        return $this->show($branch->toString());
    }

    public function checkout(string $name): TellBranchSelection {
        $workspace = $this->workspace();
        $store = new FilesystemArena($workspace);
        $selection = (new BranchStore($store, new BranchCurrentSelectionStore($workspace)))->checkout($name);

        return new TellBranchSelection($selection->branch, 'current');
    }

    /**
     * Move a branch ref backwards without deleting any immutable history.
     * Create a recovery branch first when the current head must remain addressable.
     */
    public function reset(string $name, int $steps): TellBranchReset {
        if ($steps < 1 || $steps > self::MAX_RESET_STEPS) {
            throw new InvalidArgumentException('Tell reset steps must be between 1 and ' . self::MAX_RESET_STEPS . '.');
        }
        $workspace = $this->workspace();
        $store = new FilesystemArena($workspace);
        $selection = (new BranchResolver($store, $workspace))->resolve($name);
        $before = $store->readRef($selection->ref)->head;
        $lineage = $this->lineage($store, $before);
        if (!array_key_exists($steps, $lineage)) {
            throw new InvalidArgumentException('Tell reset steps exceed the selected branch ancestry.');
        }
        $target = $lineage[$steps];
        $after = $target === null
            ? $store->compareAndSwapToEmpty($selection->ref, $before)
            : $store->compareAndSwap($selection->ref, $before, $target);

        return new TellBranchReset(
            branch: $selection->branch,
            previousHead: $before?->toString(),
            head: $after->head?->toString(),
            distance: $steps,
            changed: $before?->toString() !== $after->head?->toString(),
        );
    }

    private function workspace(): StoredWorkspace {
        $workspace = $this->agents->workspace()->discover($this->directory);
        if ($workspace === null) {
            throw new InvalidArgumentException('Tell branch controls require an initialized workspace; call workspace()->initialize() first.');
        }

        return $workspace;
    }

    private function fromCurrent(FilesystemArena $store, StoredWorkspace $workspace): Ref {
        $selection = (new BranchResolver($store, $workspace))->resolve();
        $current = $store->readRef($selection->ref);

        return new Ref($current->head, new Provenance('current', $selection->branch, $current->head));
    }

    private function fromBranch(FilesystemArena $store, BranchName $source): Ref {
        $reference = $store->readOptionalRef('branches/' . $source->toString());
        if ($reference === null) {
            throw new InvalidArgumentException("Tell branch '{$source->toString()}' does not exist.");
        }

        return new Ref($reference->head, new Provenance('branch', $source->toString(), $reference->head));
    }

    /** @param array<string, mixed> $branch */
    private function info(array $branch, string $current, bool $full): TellBranchInfo {
        return new TellBranchInfo(
            name: $branch['name'],
            head: $branch['head'],
            empty: $branch['empty'],
            turnCount: $branch['turnCount'],
            current: $branch['name'] === $current,
            created: $full ? ($branch['created'] ?? null) : null,
        );
    }

    /** @return array<int, ?ObjectHash> */
    private function lineage(FilesystemArena $arena, ?ObjectHash $head): array {
        $lineage = [0 => $head];
        $seen = [];
        $cursor = $head;
        for ($distance = 1; $cursor !== null; $distance++) {
            if ($distance > self::MAX_LINEAGE_DEPTH) {
                throw new InvalidArgumentException('Tell branch lineage exceeds the reset safety limit.');
            }
            $id = $cursor->toString();
            if (isset($seen[$id])) {
                throw new InvalidArgumentException('Tell branch lineage contains a cycle.');
            }
            $seen[$id] = true;
            $record = $arena->get($cursor);
            $cursor = match (true) {
                $record instanceof Turn => $record->lineage()->parent(),
                $record instanceof ConversationRoot => null,
                default => throw new InvalidArgumentException('Tell branch head is not canonical conversation history.'),
            };
            $lineage[$distance] = $cursor;
        }

        return $lineage;
    }
}
