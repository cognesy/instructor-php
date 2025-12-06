<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Services\BeadsJsonlFileService;
use RuntimeException;

class TbdIssueStore
{
    public function __construct(
        private readonly BeadsJsonlFileService $files,
    ) {}

    /**
     * @return IssueDTO[]
     */
    public function load(string $filePath, bool $allowMissing = false): array {
        if (!file_exists($filePath)) {
            if ($allowMissing) {
                return [];
            }
            throw new RuntimeException("File not found: {$filePath}. Run `tbd init --file={$filePath}` first.");
        }
        return $this->files->readFile($filePath);
    }

    /**
     * @param IssueDTO[] $issues
     */
    public function save(string $filePath, array $issues): void {
        $this->files->writeIssues($filePath, $issues);
    }

    public function ensureFileExists(string $filePath): void {
        if (file_exists($filePath)) {
            return;
        }
        $this->files->writeIssues($filePath, []);
    }
}
