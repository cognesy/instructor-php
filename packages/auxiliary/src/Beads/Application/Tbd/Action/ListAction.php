<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum;

class ListAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
    ) {}

    /**
     * @return IssueDTO[]
     */
    public function __invoke(
        string $filePath,
        ?StatusEnum $status = null,
        ?string $assignee = null,
        array $labels = [],
        ?int $limit = null,
    ): array {
        $issues = $this->store->load($filePath, allowMissing: false);

        $filtered = array_values(array_filter(
            $issues,
            function (IssueDTO $issue) use ($status, $assignee, $labels): bool {
                if ($status !== null && $issue->status !== $status) {
                    return false;
                }
                if ($assignee !== null && $assignee !== '' && $issue->assignee !== $assignee) {
                    return false;
                }
                if (!empty($labels)) {
                    $hasAll = empty(array_diff($labels, $issue->labels));
                    if (!$hasAll) {
                        return false;
                    }
                }
                return true;
            }
        ));

        if ($limit !== null && $limit >= 0) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }
}
