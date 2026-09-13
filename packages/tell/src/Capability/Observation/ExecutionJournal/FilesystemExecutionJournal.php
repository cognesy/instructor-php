<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\ExecutionJournal;

use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournal;
use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournalRecord;
use RuntimeException;

/** Append-only private filesystem journal for semantic execution transitions. */
final readonly class FilesystemExecutionJournal implements ExecutionJournal
{
    public function __construct(private string $path) {}

    #[\Override]
    public function append(ExecutionJournalRecord $record): void {
        $directory = dirname($this->path);
        if (file_exists($directory) && !is_dir($directory)) {
            throw new RuntimeException("Tell execution journal directory is unavailable: {$directory}");
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Tell execution journal directory could not be created: {$directory}");
        }
        $line = json_encode($record->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        if (@file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Tell execution journal could not be written.');
        }
        @chmod($directory, 0700);
        @chmod($this->path, 0600);
    }

    #[\Override]
    public function all(): array {
        if (!is_file($this->path)) {
            return [];
        }
        $records = [];
        foreach (file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $data = json_decode($line, true);
            if (!is_array($data) || array_is_list($data)) {
                continue;
            }
            $record = ExecutionJournalRecord::fromArray($data);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }
}
