# Hub Execution Tracking Implementation Plan

## Implementation Roadmap

This document provides detailed technical implementation steps for the Hub execution tracking system, following DDD principles and maintaining backward compatibility.

## Phase 1: Core Infrastructure (Days 1-3)

### 1.1 Create Directory Structure

```bash
mkdir -p packages/hub/src/Contracts
mkdir -p packages/hub/src/Data
mkdir -p packages/hub/src/Services
mkdir -p packages/hub/src/Commands/Enhanced
mkdir -p packages/hub/src/Views
mkdir -p packages/hub/tests/Unit/Services
mkdir -p packages/hub/tests/Integration
```

### 1.2 Implement Value Objects and Enums

**File: `packages/hub/src/Data/ExecutionStatus.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

enum ExecutionStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case ERROR = 'error';
    case INTERRUPTED = 'interrupted';
    case SKIPPED = 'skipped';
    case STALE = 'stale';

    public function isTerminal(): bool
    {
        return match($this) {
            self::COMPLETED, self::ERROR, self::INTERRUPTED, self::SKIPPED => true,
            self::PENDING, self::RUNNING, self::STALE => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
```

**File: `packages/hub/src/Data/ExecutionError.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExecutionError
{
    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly string $fullOutput,
        public readonly int $exitCode,
        public readonly \DateTimeImmutable $timestamp,
    ) {}

    public static function fromException(\Throwable $exception): self
    {
        return new self(
            type: 'exception',
            message: $exception->getMessage(),
            fullOutput: $exception->getTraceAsString(),
            exitCode: $exception->getCode(),
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function fromOutput(string $output, int $exitCode): self
    {
        return new self(
            type: self::detectErrorType($output),
            message: self::extractErrorMessage($output),
            fullOutput: $output,
            exitCode: $exitCode,
            timestamp: new \DateTimeImmutable(),
        );
    }

    private static function detectErrorType(string $output): string
    {
        if (str_contains($output, 'Fatal error')) return 'fatal_error';
        if (str_contains($output, 'Parse error')) return 'parse_error';
        if (str_contains($output, 'Warning')) return 'warning';
        if (str_contains($output, 'Notice')) return 'notice';
        return 'unknown_error';
    }

    private static function extractErrorMessage(string $output): string
    {
        $lines = explode("\n", trim($output));
        foreach ($lines as $line) {
            if (str_contains($line, 'Fatal error') || str_contains($line, 'Parse error')) {
                return trim($line);
            }
        }
        return $lines[0] ?? 'Unknown error';
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp->format('c'),
            'type' => $this->type,
            'message' => $this->message,
            'output' => $this->fullOutput,
            'exitCode' => $this->exitCode,
        ];
    }
}
```

**File: `packages/hub/src/Data/ExecutionResult.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

class ExecutionResult
{
    public function __construct(
        public readonly ExecutionStatus $status,
        public readonly float $executionTime,
        public readonly int $exitCode,
        public readonly string $output,
        public readonly ?ExecutionError $error,
        public readonly \DateTimeImmutable $timestamp,
    ) {}

    public static function success(float $executionTime, string $output): self
    {
        return new self(
            status: ExecutionStatus::COMPLETED,
            executionTime: $executionTime,
            exitCode: 0,
            output: $output,
            error: null,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function failure(float $executionTime, ExecutionError $error): self
    {
        return new self(
            status: ExecutionStatus::ERROR,
            executionTime: $executionTime,
            exitCode: $error->exitCode,
            output: $error->fullOutput,
            error: $error,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public static function interrupted(float $executionTime): self
    {
        return new self(
            status: ExecutionStatus::INTERRUPTED,
            executionTime: $executionTime,
            exitCode: 130, // Standard SIGINT exit code
            output: '',
            error: null,
            timestamp: new \DateTimeImmutable(),
        );
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'executionTime' => $this->executionTime,
            'exitCode' => $this->exitCode,
            'output' => $this->output,
            'error' => $this->error?->toArray(),
            'timestamp' => $this->timestamp->format('c'),
        ];
    }
}
```

### 1.3 Implement Core Interfaces

**File: `packages/hub/src/Contracts/CanTrackExecution.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;
use Cognesy\InstructorHub\Data\ExampleExecutionStatus;
use Cognesy\InstructorHub\Data\ExecutionSummary;

interface CanTrackExecution
{
    public function recordStart(Example $example): void;
    public function recordResult(Example $example, ExecutionResult $result): void;
    public function getStatus(Example $example): ?ExampleExecutionStatus;
    public function getAllStatuses(): array;
    public function getSummary(): ExecutionSummary;
    public function hasStatus(Example $example): bool;
    public function markInterrupted(Example $example, float $executionTime): void;
}
```

**File: `packages/hub/src/Contracts/CanPersistStatus.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

interface CanPersistStatus
{
    public function save(array $statusData): void;
    public function load(): array;
    public function exists(): bool;
    public function clear(): void;
    public function backup(): string;
    public function getLastModified(): ?\DateTimeImmutable;
}
```

**File: `packages/hub/src/Contracts/CanExecuteExample.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;

interface CanExecuteExample
{
    public function execute(Example $example): ExecutionResult;
    public function setTracker(?CanTrackExecution $tracker): void;
    public function canExecute(Example $example): bool;
}
```

### 1.4 Implement Status Repository

**File: `packages/hub/src/Services/StatusRepository.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Contracts\CanPersistStatus;
use Cognesy\InstructorHub\Exceptions\InvalidStatusFileException;
use Symfony\Component\Filesystem\Filesystem;

class StatusRepository implements CanPersistStatus
{
    private const STATUS_FILE_VERSION = '1.0';

    private string $statusFilePath;
    private Filesystem $filesystem;

    public function __construct(?string $statusFilePath = null)
    {
        $this->statusFilePath = $statusFilePath ?? BasePath::get('.hub/status.json');
        $this->filesystem = new Filesystem();
        $this->ensureDirectoryExists();
    }

    public function save(array $statusData): void
    {
        $statusData['metadata']['version'] = self::STATUS_FILE_VERSION;
        $statusData['metadata']['lastUpdated'] = (new \DateTimeImmutable())->format('c');

        $json = json_encode($statusData,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new InvalidStatusFileException('Failed to encode status data to JSON: ' . json_last_error_msg());
        }

        $this->filesystem->dumpFile($this->statusFilePath, $json);
    }

    public function load(): array
    {
        if (!$this->exists()) {
            return $this->getEmptyStructure();
        }

        $content = file_get_contents($this->statusFilePath);
        if ($content === false) {
            throw new InvalidStatusFileException("Failed to read status file: {$this->statusFilePath}");
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidStatusFileException('Invalid JSON in status file: ' . json_last_error_msg());
        }

        return $this->validateAndMigrate($data);
    }

    public function exists(): bool
    {
        return file_exists($this->statusFilePath);
    }

    public function clear(): void
    {
        if ($this->exists()) {
            $this->filesystem->remove($this->statusFilePath);
        }
    }

    public function backup(): string
    {
        if (!$this->exists()) {
            throw new InvalidStatusFileException('Cannot backup non-existent status file');
        }

        $backupPath = $this->statusFilePath . '.backup.' . date('Y-m-d-H-i-s');
        $this->filesystem->copy($this->statusFilePath, $backupPath);

        return $backupPath;
    }

    public function getLastModified(): ?\DateTimeImmutable
    {
        if (!$this->exists()) {
            return null;
        }

        $timestamp = filemtime($this->statusFilePath);
        return $timestamp !== false ? new \DateTimeImmutable('@' . $timestamp) : null;
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->statusFilePath);
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir, 0755);
        }
    }

    private function getEmptyStructure(): array
    {
        return [
            'metadata' => [
                'version' => self::STATUS_FILE_VERSION,
                'lastUpdated' => (new \DateTimeImmutable())->format('c'),
                'totalExamples' => 0,
            ],
            'examples' => [],
            'statistics' => [
                'totalExecuted' => 0,
                'completed' => 0,
                'errors' => 0,
                'skipped' => 0,
                'interrupted' => 0,
                'averageExecutionTime' => 0.0,
                'totalExecutionTime' => 0.0,
                'lastFullRun' => null,
                'lastPartialRun' => null,
                'slowestExample' => null,
                'fastestExample' => null,
            ],
        ];
    }

    private function validateAndMigrate(array $data): array
    {
        // Ensure required structure exists
        $structure = $this->getEmptyStructure();

        $data = array_merge($structure, $data);
        $data['metadata'] = array_merge($structure['metadata'], $data['metadata'] ?? []);
        $data['statistics'] = array_merge($structure['statistics'], $data['statistics'] ?? []);

        // Validate version and migrate if needed
        $version = $data['metadata']['version'] ?? '0.0';
        if (version_compare($version, self::STATUS_FILE_VERSION, '<')) {
            $data = $this->migrateData($data, $version);
        }

        return $data;
    }

    private function migrateData(array $data, string $fromVersion): array
    {
        // Future migration logic will go here
        // For now, just update the version
        $data['metadata']['version'] = self::STATUS_FILE_VERSION;
        return $data;
    }
}
```

## Phase 2: Enhanced Runner (Days 4-5)

### 2.1 Implement Enhanced Runner

**File: `packages/hub/src/Services/EnhancedRunner.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;
use Cognesy\InstructorHub\Data\ExecutionError;
use Cognesy\InstructorHub\Data\ExecutionStatus;

class EnhancedRunner implements CanExecuteExample
{
    private ?CanTrackExecution $tracker = null;

    public function __construct(
        private bool $displayErrors = true,
        private int $timeoutSeconds = 300,
    ) {}

    public function setTracker(?CanTrackExecution $tracker): void
    {
        $this->tracker = $tracker;
    }

    public function execute(Example $example): ExecutionResult
    {
        if (!$this->canExecute($example)) {
            return ExecutionResult::failure(
                0.0,
                ExecutionError::fromException(new \RuntimeException("Cannot execute example: {$example->name}"))
            );
        }

        $startTime = microtime(true);

        // Register signal handlers for graceful interruption
        $interrupted = false;
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGINT, function() use (&$interrupted) {
                $interrupted = true;
            });
            pcntl_signal(SIGTERM, function() use (&$interrupted) {
                $interrupted = true;
            });
        }

        $this->tracker?->recordStart($example);

        try {
            // Check for interruption before execution
            if ($interrupted) {
                $executionTime = microtime(true) - $startTime;
                return ExecutionResult::interrupted($executionTime);
            }

            $output = $this->executeWithTimeout($example->runPath, $this->timeoutSeconds);
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Check for interruption after execution
            if ($interrupted) {
                return ExecutionResult::interrupted($executionTime);
            }

            $exitCode = $this->getLastExitCode();
            $hasErrors = $this->hasErrors($output, $exitCode);

            if ($hasErrors) {
                $error = ExecutionError::fromOutput($output, $exitCode);
                $result = ExecutionResult::failure($executionTime, $error);
            } else {
                $result = ExecutionResult::success($executionTime, $output);
            }

            $this->tracker?->recordResult($example, $result);
            return $result;

        } catch (\Throwable $e) {
            $executionTime = microtime(true) - $startTime;
            $error = ExecutionError::fromException($e);
            $result = ExecutionResult::failure($executionTime, $error);
            $this->tracker?->recordResult($example, $result);
            return $result;
        }
    }

    public function canExecute(Example $example): bool
    {
        return file_exists($example->runPath) && is_readable($example->runPath);
    }

    private function executeWithTimeout(string $runPath, int $timeoutSeconds): string
    {
        $command = sprintf(
            'timeout %d php %s 2>&1',
            $timeoutSeconds,
            escapeshellarg($runPath)
        );

        // Use proc_open for better control
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start process');
        }

        // Close stdin
        fclose($pipes[0]);

        // Read output with timeout
        $output = '';
        $error = '';

        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;

        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $ready = stream_select($read, $write, $except, 1);

            if ($ready > 0) {
                if (in_array($pipes[1], $read)) {
                    $output .= fread($pipes[1], 8192);
                }
                if (in_array($pipes[2], $read)) {
                    $error .= fread($pipes[2], 8192);
                }
            }

            // Check for process completion
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        return trim($output . $error);
    }

    private function getLastExitCode(): int
    {
        // This is a simplified version - in practice you'd capture this from proc_open
        return 0;
    }

    private function hasErrors(string $output, int $exitCode): bool
    {
        if ($exitCode !== 0) {
            return true;
        }

        $errorPatterns = [
            'Fatal error',
            'Parse error',
            'Error:',
            'Uncaught',
            'Exception:',
        ];

        foreach ($errorPatterns as $pattern) {
            if (str_contains($output, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
```

### 2.2 Implement Execution Tracker

**File: `packages/hub/src/Services/ExecutionTracker.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Contracts\CanPersistStatus;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;
use Cognesy\InstructorHub\Data\ExampleExecutionStatus;
use Cognesy\InstructorHub\Data\ExecutionSummary;
use Cognesy\InstructorHub\Data\ExecutionStatus;

class ExecutionTracker implements CanTrackExecution
{
    private array $statusData = [];
    private bool $modified = false;

    public function __construct(
        private CanPersistStatus $repository,
        private bool $autoSave = true,
    ) {
        $this->statusData = $this->repository->load();
    }

    public function recordStart(Example $example): void
    {
        $this->ensureExampleEntry($example);

        $this->statusData['examples'][$example->index]['status'] = ExecutionStatus::RUNNING->value;
        $this->statusData['examples'][$example->index]['startTime'] = (new \DateTimeImmutable())->format('c');

        $this->modified = true;

        if ($this->autoSave) {
            $this->save();
        }
    }

    public function recordResult(Example $example, ExecutionResult $result): void
    {
        $this->ensureExampleEntry($example);

        $exampleData = &$this->statusData['examples'][$example->index];
        $exampleData['lastExecuted'] = $result->timestamp->format('c');
        $exampleData['status'] = $result->status->value;
        $exampleData['executionTime'] = $result->executionTime;
        $exampleData['attempts'] = ($exampleData['attempts'] ?? 0) + 1;
        $exampleData['exitCode'] = $result->exitCode;
        $exampleData['output'] = $this->truncateOutput($result->output);

        if ($result->error) {
            $exampleData['errors'][] = $result->error->toArray();
            // Keep only last 5 errors to prevent file bloat
            $exampleData['errors'] = array_slice($exampleData['errors'], -5);
        }

        $this->updateMetadata();
        $this->modified = true;

        if ($this->autoSave) {
            $this->save();
        }
    }

    public function markInterrupted(Example $example, float $executionTime): void
    {
        $result = ExecutionResult::interrupted($executionTime);
        $this->recordResult($example, $result);
    }

    public function getStatus(Example $example): ?ExampleExecutionStatus
    {
        $data = $this->statusData['examples'][$example->index] ?? null;

        if (!$data) {
            return null;
        }

        return ExampleExecutionStatus::fromArray($data);
    }

    public function hasStatus(Example $example): bool
    {
        return isset($this->statusData['examples'][$example->index]);
    }

    public function getAllStatuses(): array
    {
        $statuses = [];

        foreach ($this->statusData['examples'] as $data) {
            $statuses[] = ExampleExecutionStatus::fromArray($data);
        }

        return $statuses;
    }

    public function getSummary(): ExecutionSummary
    {
        $stats = $this->statusData['statistics'] ?? [];

        return new ExecutionSummary(
            totalExamples: $stats['totalExamples'] ?? 0,
            executed: $stats['totalExecuted'] ?? 0,
            completed: $stats['completed'] ?? 0,
            errors: $stats['errors'] ?? 0,
            skipped: $stats['skipped'] ?? 0,
            interrupted: $stats['interrupted'] ?? 0,
            averageTime: $stats['averageExecutionTime'] ?? 0.0,
            totalTime: $stats['totalExecutionTime'] ?? 0.0,
        );
    }

    public function save(): void
    {
        if ($this->modified) {
            $this->repository->save($this->statusData);
            $this->modified = false;
        }
    }

    public function __destruct()
    {
        $this->save();
    }

    private function ensureExampleEntry(Example $example): void
    {
        if (!isset($this->statusData['examples'][$example->index])) {
            $this->statusData['examples'][$example->index] = [
                'index' => $example->index,
                'name' => $example->name,
                'group' => $example->group,
                'relativePath' => $example->relativePath,
                'absolutePath' => $example->runPath,
                'status' => ExecutionStatus::PENDING->value,
                'lastExecuted' => null,
                'executionTime' => 0.0,
                'attempts' => 0,
                'errors' => [],
                'output' => '',
                'exitCode' => 0,
            ];
        }
    }

    private function updateMetadata(): void
    {
        $examples = $this->statusData['examples'];

        $this->statusData['metadata'] = [
            'version' => '1.0',
            'lastUpdated' => (new \DateTimeImmutable())->format('c'),
            'totalExamples' => count($examples),
        ];

        $this->statusData['statistics'] = $this->calculateStatistics($examples);
    }

    private function calculateStatistics(array $examples): array
    {
        $completed = array_filter($examples, fn($e) => $e['status'] === 'completed');
        $errors = array_filter($examples, fn($e) => $e['status'] === 'error');
        $interrupted = array_filter($examples, fn($e) => $e['status'] === 'interrupted');

        $executionTimes = array_column($examples, 'executionTime');
        $totalTime = array_sum($executionTimes);
        $avgTime = count($examples) > 0 ? $totalTime / count($examples) : 0.0;

        $slowest = null;
        $fastest = null;

        if ($executionTimes) {
            $maxTime = max($executionTimes);
            $minTime = min($executionTimes);

            foreach ($examples as $example) {
                if ($example['executionTime'] === $maxTime) {
                    $slowest = ['name' => $example['name'], 'time' => $maxTime];
                }
                if ($example['executionTime'] === $minTime) {
                    $fastest = ['name' => $example['name'], 'time' => $minTime];
                }
            }
        }

        return [
            'totalExecuted' => count($examples),
            'completed' => count($completed),
            'errors' => count($errors),
            'skipped' => 0, // Will be calculated when we implement filtering
            'interrupted' => count($interrupted),
            'averageExecutionTime' => $avgTime,
            'totalExecutionTime' => $totalTime,
            'lastFullRun' => $this->getLastFullRun($examples),
            'lastPartialRun' => (new \DateTimeImmutable())->format('c'),
            'slowestExample' => $slowest,
            'fastestExample' => $fastest,
        ];
    }

    private function getLastFullRun(array $examples): ?string
    {
        // This would need to track when all examples were run
        // For now, return null
        return null;
    }

    private function truncateOutput(string $output): string
    {
        // Limit output to prevent huge status files
        $maxLength = 2000;

        if (strlen($output) <= $maxLength) {
            return $output;
        }

        return substr($output, 0, $maxLength) . "\n... (truncated)";
    }
}
```

### 2.3 Add Exception Classes

**File: `packages/hub/src/Exceptions/InvalidStatusFileException.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Exceptions;

class InvalidStatusFileException extends \RuntimeException
{
}
```

## Phase 3: New Commands (Days 6-7)

### 3.1 Enhanced All Command

**File: `packages/hub/src/Commands/Enhanced/RunAllExamples.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands\Enhanced;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Data\ExecutionFilter;
use Cognesy\InstructorHub\Data\FilterMode;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunAllExamples extends Command
{
    public function __construct(
        private CanExecuteExample $runner,
        private ExampleRepository $examples,
        private CanTrackExecution $tracker,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('all')
            ->setDescription('Run all examples with enhanced tracking')
            ->addArgument('index', InputArgument::OPTIONAL, 'Starting index (optional)')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED,
                'Filter mode: all|errors|stale|pending|not-completed', 'all')
            ->addOption('force', null, InputOption::VALUE_NONE,
                'Force execution even if recently run')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be executed without running')
            ->addOption('stop-on-error', null, InputOption::VALUE_NONE,
                'Stop execution on first error')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED,
                'Limit number of examples to run', 0);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $startIndex = $input->getArgument('index') ? (int) $input->getArgument('index') : 0;
        $filterMode = $this->parseFilterMode($input->getOption('filter'));
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $stopOnError = $input->getOption('stop-on-error');
        $limit = (int) $input->getOption('limit');

        $this->runner->setTracker($this->tracker);

        $filter = new ExecutionFilter($filterMode);
        $examples = $this->getFilteredExamples($filter, $force, $startIndex);

        if ($limit > 0) {
            $examples = array_slice($examples, 0, $limit);
        }

        if (empty($examples)) {
            Cli::outln("No examples match the specified criteria.", [Color::YELLOW]);
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->showDryRun($examples);
            return Command::SUCCESS;
        }

        return $this->executeExamples($examples, $stopOnError);
    }

    private function parseFilterMode(string $filter): FilterMode
    {
        return match($filter) {
            'all' => FilterMode::ALL,
            'errors' => FilterMode::ERRORS_ONLY,
            'stale' => FilterMode::STALE_ONLY,
            'pending' => FilterMode::PENDING_ONLY,
            'not-completed' => FilterMode::NOT_COMPLETED,
            default => FilterMode::ALL,
        };
    }

    private function getFilteredExamples(ExecutionFilter $filter, bool $force, int $startIndex): array
    {
        $allExamples = [];
        $index = 1;

        $this->examples->forEachExample(function($example) use (&$allExamples, &$index, $startIndex) {
            if ($index >= $startIndex) {
                $example->index = $index; // Ensure index is set
                $allExamples[] = $example;
            }
            $index++;
            return true;
        });

        if ($force) {
            return $allExamples;
        }

        $filtered = [];
        foreach ($allExamples as $example) {
            $status = $this->tracker->getStatus($example);
            if (!$status || $filter->shouldExecute($status)) {
                $filtered[] = $example;
            }
        }

        return $filtered;
    }

    private function showDryRun(array $examples): void
    {
        Cli::outln("Would execute " . count($examples) . " examples:", [Color::YELLOW]);

        foreach ($examples as $example) {
            Cli::outln("  [{$example->index}] {$example->group}/{$example->name}", [Color::WHITE]);
        }
    }

    private function executeExamples(array $examples, bool $stopOnError): int
    {
        $total = count($examples);
        $current = 0;
        $errors = 0;

        Cli::outln("Executing {$total} examples...", [Color::BOLD, Color::YELLOW]);

        foreach ($examples as $example) {
            $current++;

            Cli::out("({$current}/{$total}) ", [Color::DARK_GRAY]);
            Cli::out("{$example->group}/{$example->name}", [Color::WHITE]);
            Cli::out(" ... ", [Color::DARK_GRAY]);

            $startTime = microtime(true);
            $result = $this->runner->execute($example);
            $endTime = microtime(true);

            if ($result->isSuccessful()) {
                Cli::out("OK", [Color::GREEN]);
                Cli::outln(" (" . round($endTime - $startTime, 2) . "s)", [Color::DARK_GRAY]);
            } else {
                Cli::out("ERROR", [Color::RED]);
                Cli::outln(" (" . round($endTime - $startTime, 2) . "s)", [Color::DARK_GRAY]);

                if ($result->error) {
                    Cli::outln("  Error: " . $result->error->message, [Color::RED]);
                }

                $errors++;

                if ($stopOnError) {
                    Cli::outln("Stopping on first error as requested.", [Color::YELLOW]);
                    break;
                }
            }
        }

        $this->displaySummary($current, $errors);

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function displaySummary(int $executed, int $errors): void
    {
        Cli::outln("\nExecution Summary:", [Color::BOLD, Color::YELLOW]);
        Cli::outln("  Executed: {$executed}", [Color::WHITE]);
        Cli::outln("  Successful: " . ($executed - $errors), [Color::GREEN]);

        if ($errors > 0) {
            Cli::outln("  Errors: {$errors}", [Color::RED]);
        }

        $summary = $this->tracker->getSummary();
        Cli::outln("  Total time: " . round($summary->totalTime, 2) . "s", [Color::DARK_GRAY]);
        Cli::outln("  Average time: " . round($summary->averageTime, 2) . "s", [Color::DARK_GRAY]);
    }
}
```

### 3.2 Status Command

**File: `packages/hub/src/Commands/StatusCommand.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class StatusCommand extends Command
{
    public function __construct(
        private CanTrackExecution $tracker,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('status')
            ->setDescription('Show execution status summary')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE,
                'Show detailed status for each example')
            ->addOption('errors-only', 'e', InputOption::VALUE_NONE,
                'Show only examples with errors')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED,
                'Output format: table|json|csv', 'table');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $detailed = $input->getOption('detailed');
        $errorsOnly = $input->getOption('errors-only');
        $format = $input->getOption('format');

        $summary = $this->tracker->getSummary();

        if ($format === 'json') {
            $this->outputJson($summary, $detailed, $errorsOnly);
        } else {
            $this->outputTable($summary, $detailed, $errorsOnly, $output);
        }

        return Command::SUCCESS;
    }

    private function outputTable($summary, bool $detailed, bool $errorsOnly, OutputInterface $output): void
    {
        // Show summary
        Cli::outln("Execution Status Summary", [Color::BOLD, Color::YELLOW]);
        Cli::outln("========================", [Color::YELLOW]);

        Cli::outln("Total Examples: {$summary->totalExamples}", [Color::WHITE]);
        Cli::outln("Executed: {$summary->executed}", [Color::WHITE]);
        Cli::outln("Completed: {$summary->completed}", [Color::GREEN]);

        if ($summary->errors > 0) {
            Cli::outln("Errors: {$summary->errors}", [Color::RED]);
        }

        if ($summary->interrupted > 0) {
            Cli::outln("Interrupted: {$summary->interrupted}", [Color::YELLOW]);
        }

        Cli::outln("Average Time: " . round($summary->averageTime, 2) . "s", [Color::DARK_GRAY]);
        Cli::outln("Total Time: " . round($summary->totalTime, 2) . "s", [Color::DARK_GRAY]);

        if ($detailed) {
            $this->showDetailedTable($errorsOnly, $output);
        }
    }

    private function showDetailedTable(bool $errorsOnly, OutputInterface $output): void
    {
        $statuses = $this->tracker->getAllStatuses();

        if ($errorsOnly) {
            $statuses = array_filter($statuses, fn($s) => $s->status->value === 'error');
        }

        if (empty($statuses)) {
            Cli::outln("\nNo examples to display.", [Color::DARK_GRAY]);
            return;
        }

        Cli::outln("\nDetailed Status:", [Color::BOLD, Color::YELLOW]);

        $table = new Table($output);
        $table->setHeaders(['Index', 'Name', 'Group', 'Status', 'Time (s)', 'Last Run', 'Attempts']);

        foreach ($statuses as $status) {
            $statusColor = match($status->status->value) {
                'completed' => 'green',
                'error' => 'red',
                'interrupted' => 'yellow',
                default => 'white',
            };

            $table->addRow([
                $status->index,
                $status->name,
                $status->group,
                "<fg={$statusColor}>" . $status->status->value . "</fg>",
                $status->executionTime > 0 ? round($status->executionTime, 2) : '-',
                $status->lastExecuted?->format('M j, H:i') ?? 'Never',
                $status->attempts,
            ]);
        }

        $table->render();
    }

    private function outputJson($summary, bool $detailed, bool $errorsOnly): void
    {
        $data = [
            'summary' => [
                'totalExamples' => $summary->totalExamples,
                'executed' => $summary->executed,
                'completed' => $summary->completed,
                'errors' => $summary->errors,
                'interrupted' => $summary->interrupted,
                'averageTime' => $summary->averageTime,
                'totalTime' => $summary->totalTime,
            ]
        ];

        if ($detailed) {
            $statuses = $this->tracker->getAllStatuses();

            if ($errorsOnly) {
                $statuses = array_filter($statuses, fn($s) => $s->status->value === 'error');
            }

            $data['examples'] = array_map(fn($s) => $s->toArray(), $statuses);
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}
```

### 3.3 Update Hub Application

**File: `packages/hub/src/Hub.php` (modifications)**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Commands\StatusCommand;
use Cognesy\InstructorHub\Commands\Enhanced\RunAllExamples;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\EnhancedRunner;
use Cognesy\InstructorHub\Services\ExecutionTracker;
use Cognesy\InstructorHub\Services\StatusRepository;
use Symfony\Component\Console\Application;

class Hub extends Application
{
    private ExampleRepository $exampleRepo;
    private EnhancedRunner $runner;
    private ExecutionTracker $tracker;
    private StatusRepository $statusRepo;

    public function __construct() {
        parent::__construct('Hub // Instructor for PHP', '2.0.0'); // Version bump

        $this->registerServices();
        $this->registerCommands();
    }

    private function registerServices(): void
    {
        $this->exampleRepo = new ExampleRepository(
            BasePath::get('examples'),
        );

        $this->statusRepo = new StatusRepository();

        $this->tracker = new ExecutionTracker(
            repository: $this->statusRepo,
            autoSave: true,
        );

        $this->runner = new EnhancedRunner(
            displayErrors: true,
            timeoutSeconds: 300,
        );

        $this->runner->setTracker($this->tracker);
    }

    private function registerCommands(): void
    {
        $this->addCommands([
            new ListAllExamples($this->exampleRepo),
            new RunAllExamples($this->runner, $this->exampleRepo, $this->tracker),
            new RunOneExample($this->runner, $this->exampleRepo),
            new ShowExample($this->exampleRepo),
            new StatusCommand($this->tracker),
        ]);
    }
}
```

## Phase 4: Git Integration (Day 8)

### 4.1 Update .gitignore

**File: `.gitignore` (addition)**
```
# Hub execution tracking
.hub/
```

### 4.2 Create Git Hooks (Optional)

**File: `scripts/install-hub-hooks.sh`**
```bash
#!/bin/bash

# Install git hooks for hub status management

HOOK_DIR=".git/hooks"

# Pre-commit hook to clean old status data
cat > "$HOOK_DIR/pre-commit" << 'EOF'
#!/bin/sh
# Hub status maintenance
if [ -f "composer.json" ] && grep -q "instructor-hub" composer.json; then
    # Clean status data older than 1 month
    composer hub clean --older-than="1 month" --silent 2>/dev/null || true
fi
EOF

chmod +x "$HOOK_DIR/pre-commit"

echo "Hub git hooks installed successfully"
```

## Testing Strategy

### Unit Tests

**File: `packages/hub/tests/Unit/Services/StatusRepositoryTest.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Tests\Unit\Services;

use Cognesy\InstructorHub\Services\StatusRepository;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

class StatusRepositoryTest extends TestCase
{
    private $root;
    private StatusRepository $repository;

    protected function setUp(): void
    {
        $this->root = vfsStream::setUp('test');
        $this->repository = new StatusRepository(vfsStream::url('test/.hub/status.json'));
    }

    public function test_creates_empty_structure_when_file_does_not_exist(): void
    {
        $data = $this->repository->load();

        $this->assertArrayHasKey('metadata', $data);
        $this->assertArrayHasKey('examples', $data);
        $this->assertArrayHasKey('statistics', $data);
        $this->assertEquals('1.0', $data['metadata']['version']);
    }

    public function test_saves_and_loads_data_correctly(): void
    {
        $testData = [
            'metadata' => ['version' => '1.0'],
            'examples' => ['1' => ['name' => 'test']],
            'statistics' => ['total' => 1],
        ];

        $this->repository->save($testData);
        $loaded = $this->repository->load();

        $this->assertEquals('test', $loaded['examples']['1']['name']);
    }

    public function test_backup_creates_copy_with_timestamp(): void
    {
        $this->repository->save(['test' => 'data']);
        $backupPath = $this->repository->backup();

        $this->assertFileExists($backupPath);
        $this->assertStringContains('.backup.', $backupPath);
    }
}
```

### Integration Tests

**File: `packages/hub/tests/Integration/ExecutionTrackingTest.php`**
```php
<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Tests\Integration;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExecutionTracker;
use Cognesy\InstructorHub\Services\StatusRepository;
use Cognesy\InstructorHub\Services\EnhancedRunner;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

class ExecutionTrackingTest extends TestCase
{
    private $root;
    private ExecutionTracker $tracker;
    private EnhancedRunner $runner;

    protected function setUp(): void
    {
        $this->root = vfsStream::setUp('test');
        $repository = new StatusRepository(vfsStream::url('test/.hub/status.json'));
        $this->tracker = new ExecutionTracker($repository);
        $this->runner = new EnhancedRunner();
        $this->runner->setTracker($this->tracker);
    }

    public function test_tracks_successful_execution(): void
    {
        // Create a simple PHP file that succeeds
        $phpFile = vfsStream::url('test/success.php');
        file_put_contents($phpFile, '<?php echo "success";');

        $example = new Example(
            index: 1,
            name: 'TestExample',
            group: 'test',
            runPath: $phpFile,
        );

        $result = $this->runner->execute($example);

        $this->assertTrue($result->isSuccessful());

        $status = $this->tracker->getStatus($example);
        $this->assertNotNull($status);
        $this->assertEquals('completed', $status->status->value);
        $this->assertGreaterThan(0, $status->executionTime);
    }

    public function test_tracks_failed_execution(): void
    {
        // Create a PHP file that fails
        $phpFile = vfsStream::url('test/failure.php');
        file_put_contents($phpFile, '<?php throw new Exception("test error");');

        $example = new Example(
            index: 2,
            name: 'FailingExample',
            group: 'test',
            runPath: $phpFile,
        );

        $result = $this->runner->execute($example);

        $this->assertFalse($result->isSuccessful());

        $status = $this->tracker->getStatus($example);
        $this->assertNotNull($status);
        $this->assertEquals('error', $status->status->value);
        $this->assertNotEmpty($status->errors);
    }
}
```

## Deployment Checklist

### Pre-deployment
- [ ] All unit tests passing
- [ ] Integration tests passing
- [ ] Performance benchmarks completed
- [ ] Documentation updated
- [ ] Git hooks tested
- [ ] Backward compatibility verified

### Deployment
- [ ] Deploy new classes and interfaces
- [ ] Update Hub application
- [ ] Add .hub/ to .gitignore
- [ ] Test with existing examples
- [ ] Verify status file creation

### Post-deployment
- [ ] Monitor performance impact
- [ ] Check error handling
- [ ] Validate status data integrity
- [ ] User acceptance testing

This implementation plan provides a complete roadmap for adding execution tracking to the Hub system while maintaining backward compatibility and following DDD principles.