<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use JsonSerializable;
use Override;
use RuntimeException;

final class ArtifactEvalReporter implements CanReportAgentEvals
{
    private string $runDirectory = '';

    /** @var list<EvalResult> */
    private array $results = [];

    public function __construct(private readonly string $root = '.instructor/evals') {}

    #[Override]
    public function id(): string {
        return 'artifacts:' . $this->root;
    }

    #[Override]
    public function onRunStarted(int $caseCount): void {
        $this->runDirectory = rtrim($this->root, '/') . '/' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd-His-u') . '-' . bin2hex(random_bytes(3));
        if (!mkdir($this->runDirectory, 0777, true) && !is_dir($this->runDirectory)) {
            throw new RuntimeException('Cannot create eval artifact directory.');
        }
        $this->results = [];
    }

    #[Override]
    public function onEvalCompleted(EvalResult $result): void {
        $this->results[] = $result;
        $directory = $this->runDirectory . '/evals/' . self::sanitize($result->id());
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create per-eval artifact directory.');
        }
        self::json($directory . '/details.json', $result->toArray());
        self::write($directory . '/events.ndjson', self::eventLines($result->run()->events()));
    }

    #[Override]
    public function onRunCompleted(EvalRunResult $result): void {
        self::json($this->runDirectory . '/summary.json', $result->toArray());
        $lines = array_map(static fn (EvalResult $eval): string => json_encode($eval->toArray(), JSON_THROW_ON_ERROR), $this->results);
        self::write($this->runDirectory . '/results.jsonl', implode("\n", $lines) . ($lines !== [] ? "\n" : ''));
    }

    public function runDirectory(): string {
        return $this->runDirectory;
    }

    private static function sanitize(string $id): string {
        return trim(preg_replace('/[^A-Za-z0-9._\/-]+/', '_', str_replace('..', '_', $id)) ?? '_', '/');
    }

    /** @throws JsonException */
    private static function eventLines(EvalEvents $events): string {
        $lines = [];
        foreach ($events as $event) {
            $data = $event instanceof JsonSerializable ? $event->jsonSerialize() : get_object_vars($event);
            $lines[] = json_encode(['type' => $event::class, 'data' => $data], JSON_THROW_ON_ERROR);
        }
        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /** @param array<string, mixed> $data @throws JsonException */
    private static function json(string $path, array $data): void {
        self::write($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private static function write(string $path, string $content): void {
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
    }
}
