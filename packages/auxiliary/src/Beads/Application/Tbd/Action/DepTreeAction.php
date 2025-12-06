<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\DependencyDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use RuntimeException;

class DepTreeAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
    ) {}

    public function __invoke(string $filePath, string $issueId, string $direction = 'down'): array {
        $issues = $this->store->load($filePath, allowMissing: false);
        $byId = [];
        foreach ($issues as $issue) {
            $byId[$issue->id] = $issue;
        }
        if (!isset($byId[$issueId])) {
            throw new RuntimeException("Issue not found: {$issueId}");
        }

        return match ($direction) {
            'up' => $this->collectUp($issues, $issueId),
            'both' => [
                'down' => $this->collectDown($issues, $issueId),
                'up' => $this->collectUp($issues, $issueId),
            ],
            default => $this->collectDown($issues, $issueId),
        };
    }

    private function collectDown(array $issues, string $root): array {
        $edges = [];
        foreach ($issues as $issue) {
            foreach ($issue->dependencies ?? [] as $dep) {
                if ($dep->issueId === $root) {
                    $edges[] = ['from' => $issue->id, 'to' => $dep->dependsOnId, 'type' => $dep->type->value];
                }
            }
        }
        return $edges;
    }

    private function collectUp(array $issues, string $root): array {
        $edges = [];
        foreach ($issues as $issue) {
            foreach ($issue->dependencies ?? [] as $dep) {
                if ($dep->dependsOnId === $root) {
                    $edges[] = ['from' => $issue->id, 'to' => $dep->dependsOnId, 'type' => $dep->type->value];
                }
            }
        }
        return $edges;
    }
}
