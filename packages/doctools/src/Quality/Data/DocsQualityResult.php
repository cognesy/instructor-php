<?php declare(strict_types=1);

namespace Cognesy\Doctools\Quality\Data;

final readonly class DocsQualityResult
{
    /**
     * @param list<DocsQualityIssue> $issues
     */
    public function __construct(
        public array $issues,
        public int $checkedSnippets,
        public int $skippedSnippets,
    ) {}

    public function hasErrors(): bool
    {
        return $this->issues !== [];
    }

    public function issueCount(): int
    {
        return count($this->issues);
    }
}
