<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

use Cognesy\Tell\Workspace\Arena\CanUseArena;
use Cognesy\Tell\Workspace\Arena\HistoryCompiler;
use Cognesy\Tell\Workspace\Branch\Storage\BranchConfigStore;
use InvalidArgumentException;

/**
 * Read-only branch views over verified refs and immutable canonical history.
 */
final readonly class BranchCatalog
{
    public function __construct(
        private CanUseArena $arena,
        private ?BranchConfigStore $config = null,
        private HistoryCompiler $history = new HistoryCompiler(),
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(bool $full = false): array {
        $branches = [];
        foreach ($this->arena->refNames('branches') as $ref) {
            $branches[] = $this->describe(BranchName::fromStored(substr($ref, 9)), $full);
        }

        return $branches;
    }

    /** @return array<string, mixed> */
    public function show(BranchName $name): array {
        return $this->describe($name, true);
    }

    /** @return array<string, mixed> */
    private function describe(BranchName $name, bool $full): array {
        $ref = $this->arena->readOptionalRef($this->refName($name));
        if ($ref === null) {
            throw new InvalidArgumentException("Tell branch '{$name->toString()}' does not exist.");
        }
        $history = $this->history->compile($this->arena, $ref->head);
        $branch = [
            'name' => $name->toString(),
            'head' => $ref->head?->toString(),
            'empty' => $ref->head === null,
            'turnCount' => count($history->turns),
            'configuration' => $this->configuration($name),
        ];
        if ($full) {
            $branch['created'] = $ref->provenance?->toArray();
        }

        return $branch;
    }

    private function refName(BranchName $name): string {
        return 'branches/' . $name->toString();
    }

    /** @return array{status: 'configured'|'default'|'unavailable', version?: int} */
    private function configuration(BranchName $name): array {
        if ($this->config === null) {
            return ['status' => 'unavailable'];
        }
        $config = $this->config->read($name->toString());

        return $config['version'] === 0
            ? ['status' => 'default']
            : ['status' => 'configured', 'version' => $config['version']];
    }
}
