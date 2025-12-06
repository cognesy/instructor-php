<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;

class ReadyAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
    ) {}

    /**
     * Ready = no dependencies marked as blocking.
     *
     * @return IssueDTO[]
     */
    public function __invoke(string $filePath, ?int $limit = null): array {
        $issues = $this->store->load($filePath, allowMissing: false);

        $ready = array_values(array_filter(
            $issues,
            fn(IssueDTO $issue) => empty($issue->dependencies)
        ));

        if ($limit !== null && $limit >= 0) {
            $ready = array_slice($ready, 0, $limit);
        }

        return $ready;
    }
}
