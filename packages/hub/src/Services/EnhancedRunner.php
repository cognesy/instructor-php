<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionResult;
use Cognesy\InstructorHub\Data\ExecutionError;

class EnhancedRunner implements CanExecuteExample
{
    private ?CanTrackExecution $tracker = null;
    private bool $interrupted = false;

    public function __construct(
        private int $timeoutSeconds = 300,
    ) {
        $this->registerSignalHandlers();
    }

    #[\Override]
    public function setTracker(?CanTrackExecution $tracker): void
    {
        $this->tracker = $tracker;
    }

    #[\Override]
    public function execute(Example $example): ExecutionResult
    {
        if (!$this->canExecute($example)) {
            return ExecutionResult::failure(
                0.0,
                ExecutionError::fromException(new \RuntimeException("Cannot execute example: {$example->name}"))
            );
        }

        $startTime = microtime(true);

        // Check for prior interruption
        if ($this->interrupted) {
            return ExecutionResult::interrupted(0.0);
        }

        $this->tracker?->recordStart($example);

        try {
            [$output, $exitCode] = $this->executeWithTimeout($example->runPath, $this->timeoutSeconds);
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;

            // Check for interruption after execution (can be changed by signal handler)
            /** @phpstan-ignore-next-line - $this->interrupted can be changed by pcntl signal handler */
            if ($this->interrupted) {
                $result = ExecutionResult::interrupted($executionTime);
                $this->tracker?->recordResult($example, $result);
                return $result;
            }

            // Use exit code to determine success/failure
            if ($exitCode !== 0) {
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

    #[\Override]
    public function canExecute(Example $example): bool
    {
        return file_exists($example->runPath) && is_readable($example->runPath);
    }

    public function isInterrupted(): bool
    {
        return $this->interrupted;
    }

    public function resetInterruption(): void
    {
        $this->interrupted = false;
    }

    private function registerSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        pcntl_signal(SIGINT, function() {
            $this->interrupted = true;
        });

        pcntl_signal(SIGTERM, function() {
            $this->interrupted = true;
        });
    }

    private function executeWithTimeout(string $runPath, int $timeoutSeconds): array
    {
        $command = sprintf(
            'php %s 2>&1',
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

        // Set streams to non-blocking mode
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $error = '';
        $startTime = time();

        while (true) {
            // Check timeout
            if ((time() - $startTime) >= $timeoutSeconds) {
                proc_terminate($process, SIGKILL);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new \RuntimeException("Process timed out after {$timeoutSeconds} seconds");
            }

            // Check for interruption
            if ($this->interrupted) {
                proc_terminate($process, SIGTERM);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                return [$output . $error, 130]; // 130 = SIGTERM exit code
            }

            $status = proc_get_status($process);

            // Read available output
            $stdoutContent = stream_get_contents($pipes[1]);
            $stderrContent = stream_get_contents($pipes[2]);

            if ($stdoutContent !== false) {
                $output .= $stdoutContent;
            }
            if ($stderrContent !== false) {
                $error .= $stderrContent;
            }

            if (!$status['running']) {
                break;
            }

            // Small delay to prevent CPU spinning
            usleep(10000); // 10ms
        }

        // Read any remaining output
        $stdoutContent = stream_get_contents($pipes[1]);
        $stderrContent = stream_get_contents($pipes[2]);
        if ($stdoutContent !== false) {
            $output .= $stdoutContent;
        }
        if ($stderrContent !== false) {
            $error .= $stderrContent;
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [trim($output . $error), $exitCode];
    }

}
