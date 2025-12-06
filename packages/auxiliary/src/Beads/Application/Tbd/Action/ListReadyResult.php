<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;

final class ListReadyResult
{
    /**
     * @param IssueDTO[] $issues
     */
    public function __construct(
        public array $issues,
    ) {}
}
