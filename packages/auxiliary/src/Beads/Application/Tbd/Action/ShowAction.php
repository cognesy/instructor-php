<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use RuntimeException;

class ShowAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
    ) {}

    public function __invoke(string $filePath, string $id): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: false);
        foreach ($issues as $issue) {
            if ($issue->id === $id) {
                return $issue;
            }
        }
        throw new RuntimeException("Issue not found: {$id}");
    }
}
