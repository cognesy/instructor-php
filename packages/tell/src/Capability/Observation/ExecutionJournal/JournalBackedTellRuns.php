<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Observation\ExecutionJournal;

use Cognesy\Agents\Capability\ExecutionJournal\ExecutionJournalRecord;
use Cognesy\Tell\Core\Contract\Observation\CanInspectTellRuns;
use Cognesy\Tell\Core\Paths\TellPaths;
use DateTimeImmutable;

/** Projects runs from the semantic journal and follows optional trace references. */
final readonly class JournalBackedTellRuns implements CanInspectTellRuns
{
    public function __construct(private TellPaths $paths) {}

    #[\Override]
    public function list(array $filters, int $limit): array {
        $runs = $this->runProjections();
        foreach ($runs as $executionId => $record) {
            if (!$this->matches($record, $filters)) {
                unset($runs[$executionId]);
                continue;
            }
        }
        usort($runs, static function (array $left, array $right): int {
            $timestamp = ($right['timestamp'] ?? '') <=> ($left['timestamp'] ?? '');
            if ($timestamp !== 0) {
                return $timestamp;
            }

            return ($left['executionId'] ?? '') <=> ($right['executionId'] ?? '');
        });

        return array_slice($runs, 0, $limit);
    }

    #[\Override]
    public function show(string $executionId, bool $includePayloads = false): ?array {
        $summary = $this->runProjections()[$executionId] ?? null;
        if ($summary === null) {
            return null;
        }
        $journal = array_values(array_map(
            static fn (ExecutionJournalRecord $record): array => $record->toArray(),
            array_filter(
                $this->journal()->all(),
                static fn (ExecutionJournalRecord $record): bool => $record->executionId === $executionId,
            ),
        ));
        $path = $this->safeTracePath($summary['path'] ?? null);
        if ($path === null) {
            return [
                'run' => $summary,
                'journal' => $journal,
                'trace' => ['status' => $this->missingTraceStatus($summary), 'events' => []],
                'explanation' => $this->explanation($summary, []),
            ];
        }
        $trace = $this->readTrace($path, $executionId, $includePayloads);

        return [
            'run' => $summary,
            'journal' => $journal,
            'trace' => $trace,
            'explanation' => $this->explanation($summary, $trace['events']),
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $filters
     */
    private function matches(array $record, array $filters): bool {
        foreach (['status', 'reason', 'agent', 'session', 'branch'] as $key) {
            if (isset($filters[$key]) && ($record[$key] ?? null) !== $filters[$key]) {
                return false;
            }
        }
        if (isset($filters['workspace'])) {
            $workspace = realpath($filters['workspace']) ?: $filters['workspace'];
            if (($record['workspace'] ?? null) !== $workspace) {
                return false;
            }
        }
        if (isset($filters['since'])) {
            $timestamp = $record['timestamp'] ?? null;
            if (!is_string($timestamp) || new DateTimeImmutable($timestamp) < new DateTimeImmutable($filters['since'])) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, array<string, mixed>> */
    private function runProjections(): array {
        $runs = [];
        foreach ($this->journal()->all() as $record) {
            $status = match ($record->status) {
                null => 'unknown',
                default => $record->status->value,
            };
            $run = $runs[$record->executionId] ?? [
                'executionId' => $record->executionId,
                'timestamp' => $record->occurredAt->format(DateTimeImmutable::ATOM),
                'status' => $status,
                'reason' => $record->reason,
                'source' => null,
                'context' => [],
                'steps' => $record->stepCount,
                'lastOperation' => null,
                'errorCount' => $record->errorCount,
                'errorCode' => null,
                'errorCategory' => null,
                'errorPhase' => null,
                'agent' => $record->agentId,
                'session' => null,
                'branch' => null,
                'workspace' => null,
                'requestedMode' => null,
                'publication' => 'unknown',
                'baseHead' => null,
                'publishedHead' => null,
                'storageKind' => null,
                'path' => null,
                'resolved' => false,
            ];
            $run['timestamp'] = $record->occurredAt->format(DateTimeImmutable::ATOM);
            $run['status'] = match ($record->status) {
                null => $run['status'],
                default => $status,
            };
            $run['reason'] = $record->reason ?? $run['reason'];
            $run['source'] = $record->facts['source'] ?? $run['source'];
            $currentContext = $run['context'];
            if (!is_array($currentContext)) {
                $currentContext = [];
            }
            $run['context'] = [
                ...$currentContext,
                ...$this->context($record),
            ];
            $run['steps'] = $record->stepCount;
            $run['errorCount'] = max($record->errorCount, is_int($run['errorCount']) ? $run['errorCount'] : 0);
            if ($record->kind === 'step.completed') {
                $run['lastOperation'] = 'step.completed';
            }
            if ($record->kind === 'execution.abandoned') {
                $run['status'] = 'abandoned';
            }
            if ($record->kind === 'tell.outcome') {
                $run = array_replace($run, [
                    'agent' => $record->facts['agent'] ?? $run['agent'],
                    'session' => $record->facts['session'] ?? null,
                    'branch' => $record->facts['branch'] ?? null,
                    'workspace' => $record->facts['workspace'] ?? null,
                    'requestedMode' => $record->facts['requestedMode'] ?? null,
                    'publication' => $record->facts['publication'] ?? 'unknown',
                    'baseHead' => $record->facts['baseHead'] ?? null,
                    'publishedHead' => $record->facts['publishedHead'] ?? null,
                    'errorCode' => $record->facts['errorCode'] ?? null,
                    'errorCategory' => $record->facts['errorCategory'] ?? null,
                    'errorPhase' => $record->facts['errorPhase'] ?? null,
                    'storageKind' => $record->facts['traceStorage'] ?? null,
                    'path' => $record->facts['tracePath'] ?? null,
                    'traceStatus' => $record->facts['traceStatus'] ?? 'unknown',
                    'resolved' => true,
                ]);
            }
            $runs[$record->executionId] = $run;
        }

        return $runs;
    }

    /** @return array<string, int|float|string|bool|null> */
    private function context(ExecutionJournalRecord $record): array {
        $context = [];
        foreach ($record->facts as $key => $value) {
            if (!str_starts_with($key, 'context.')) {
                continue;
            }
            $context[substr($key, 8)] = $value;
        }

        return $context;
    }

    private function journal(): FilesystemExecutionJournal {
        return new FilesystemExecutionJournal($this->paths->executionJournal);
    }

    /**
     * @return array{status: 'written', integrity: 'clean'|'trailing_corruption'|'interior_corruption', malformedLines: list<int>, path: string, events: list<array<string, mixed>>}
     */
    private function readTrace(string $path, string $executionId, bool $includePayloads): array {
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $lastContentLine = 0;
        foreach ($lines as $index => $line) {
            if ($line !== '') {
                $lastContentLine = $index + 1;
            }
        }
        $events = [];
        $malformed = [];
        foreach ($lines as $index => $line) {
            if ($line === '') {
                continue;
            }
            $record = json_decode($line, true);
            if (!is_array($record) || array_is_list($record)) {
                $malformed[] = $index + 1;
                continue;
            }
            if (($record['executionId'] ?? null) !== $executionId) {
                continue;
            }
            if (!$includePayloads) {
                unset($record['payload']);
            }
            $events[] = $record;
        }
        $interior = array_filter(
            $malformed,
            static fn (int $line): bool => $line < $lastContentLine,
        );
        $integrity = match (true) {
            $interior !== [] => 'interior_corruption',
            $malformed !== [] => 'trailing_corruption',
            default => 'clean',
        };

        return [
            'status' => 'written',
            'integrity' => $integrity,
            'malformedLines' => $malformed,
            'path' => $path,
            'events' => $events,
        ];
    }

    /** @param array<string, mixed> $summary */
    private function missingTraceStatus(array $summary): string {
        $status = $summary['traceStatus'] ?? null;

        return match (true) {
            is_string($status) && in_array($status, ['disabled', 'pending', 'failed'], true) => $status,
            default => 'missing',
        };
    }

    private function safeTracePath(mixed $path): ?string {
        if (!is_string($path) || !is_file($path)) {
            return null;
        }
        $resolved = realpath($path);
        if ($resolved === false) {
            return null;
        }
        foreach ([$this->paths->executionTraces, $this->paths->sessionTraces] as $root) {
            $resolvedRoot = realpath($root);
            if ($resolvedRoot !== false && str_starts_with($resolved, $resolvedRoot . DIRECTORY_SEPARATOR)) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $summary
     * @param list<array<string, mixed>> $events
     * @return array<string, mixed>
     */
    private function explanation(array $summary, array $events): array {
        $terminal = [];
        $lastOperation = null;
        foreach ($events as $event) {
            if (($event['kind'] ?? null) === 'execution.settled') {
                $terminal = $event['metadata'] ?? [];
                continue;
            }
            if (in_array($event['kind'] ?? null, ['tool.completed', 'step.completed', 'inference.completed'], true)) {
                $lastOperation = $event['kind'];
            }
        }

        return [
            'summary' => $this->summary($summary),
            'reason' => $summary['reason'] ?? $terminal['reason'] ?? 'unknown',
            'source' => $summary['source'] ?? $terminal['source'] ?? 'unknown',
            'context' => $summary['context'] ?? [],
            'lastOperation' => $summary['lastOperation'] ?? $lastOperation ?? 'unknown',
            'steps' => $summary['steps'] ?? $terminal['steps'] ?? 'unknown',
            'errors' => [
                'count' => $summary['errorCount'] ?? $terminal['errorCount'] ?? 0,
                'code' => $summary['errorCode'] ?? $terminal['errorCode'] ?? 'unknown',
                'category' => $summary['errorCategory'] ?? $terminal['errorCategory'] ?? 'unknown',
                'phase' => $summary['errorPhase'] ?? $terminal['errorPhase'] ?? 'unknown',
            ],
            'publication' => [
                'status' => $summary['publication'] ?? 'unknown',
                'baseHead' => $summary['baseHead'] ?? $terminal['baseHead'] ?? 'unknown',
                'publishedHead' => $summary['publishedHead'] ?? $terminal['publishedHead'] ?? 'unknown',
            ],
        ];
    }

    /** @param array<string, mixed> $summary */
    private function summary(array $summary): string {
        $status = is_string($summary['status'] ?? null) ? $summary['status'] : 'unknown';
        $reason = is_string($summary['reason'] ?? null) ? $summary['reason'] : null;
        $publication = is_string($summary['publication'] ?? null) ? $summary['publication'] : 'unknown';

        return match ($reason) {
            null => "Execution {$status}; publication {$publication}.",
            default => "Execution {$status} because {$reason}; publication {$publication}.",
        };
    }
}
