<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;

class CompactAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
    ) {}

    /**
     * Sort issues by id and rewrite the file.
     *
     * @return IssueDTO[]
     */
    public function __invoke(string $filePath): array {
        $issues = $this->store->load($filePath, allowMissing: false);
        usort($issues, fn(IssueDTO $a, IssueDTO $b) => $a->id <=> $b->id);
        $this->store->save($filePath, $issues);
        return $issues;
    }
}
